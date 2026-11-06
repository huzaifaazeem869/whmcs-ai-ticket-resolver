<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TicketAnalysisDTO;
use App\Http\Controllers\Controller;
use App\Models\WhmcsClient;
use App\Models\WhmcsDepartment;
use App\Models\WhmcsInvoice;
use App\Models\WhmcsTicket;
use App\Models\WhmcsTicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhmcsMockController extends Controller
{
    public function getClients(): JsonResponse
    {
        return response()->json(
            WhmcsClient::withCount(['invoices', 'tickets'])->get()
        );
    }

    public function getInvoices(Request $request): JsonResponse
    {
        $query = WhmcsInvoice::with('client');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->get());
    }

    public function getTickets(): JsonResponse
    {
        return response()->json(WhmcsTicket::with(['client', 'departmentRelation'])->get());
    }

    public function getTicket(int $id): JsonResponse
    {
        $ticket = WhmcsTicket::with(['client.invoices', 'departmentRelation', 'replies'])->findOrFail($id);
        return response()->json($ticket);
    }

    public function analyzeTicket(int $id): JsonResponse
    {
        // 1. Official WHMCS DB se Ticket + Client + Invoices lein
        $ticket = WhmcsTicket::with('client.invoices')->findOrFail($id);

        // 2. Client Invoices Context
        $invoiceContext = $ticket->client->invoices
            ->map(fn ($inv) => "{$inv->invoicenum}: \${$inv->total} ({$inv->status})")
            ->implode(', ');

        $apiKey = env('GEMINI_API_KEY');
        $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
        if (str_ends_with($supabaseUrl, '/rest/v1')) {
            $supabaseUrl = substr($supabaseUrl, 0, -8);
        }
        $supabaseKey = env('SUPABASE_KEY');

        // 3. RAG: Ticket text ka Vector bana kar Supabase Cloud se matched article dhoondein
        $ticketText = "{$ticket->title}. {$ticket->message}";
        $matchedKbDoc = "None (No relevant knowledge base doc found)";

        $embedRes = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}", [
            'model' => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => $ticketText]]]
        ]);

        if ($embedRes->successful()) {
            $queryVector = $embedRes->json('embedding.values');

            // Supabase Stored Procedure call
            $rpcRes = Http::withHeaders([
                'apikey' => $supabaseKey,
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type' => 'application/json',
            ])->post("{$supabaseUrl}/rest/v1/rpc/match_articles", [
                'query_embedding' => $queryVector,
                'match_threshold' => 0.60,
                'match_count' => 1,
            ]);

            if ($rpcRes->successful() && !empty($rpcRes->json())) {
                $topDoc = $rpcRes->json('0');
                $matchedKbDoc = "[{$topDoc['category']}] {$topDoc['title']}: {$topDoc['content']}";
            }
        }

        // 4. Prompt with RAG Knowledge Base Injection & Official WHMCS context
        $prompt = "Analyze this WHMCS support ticket using the provided verified Knowledge Base documentation and client billing context.\n\n"
            . "Client Name: {$ticket->client->name} ({$ticket->client->email})\n"
            . "Client Invoices in WHMCS: {$invoiceContext}\n"
            . "Matched Knowledge Base Doc: {$matchedKbDoc}\n"
            . "Ticket Masking ID: {$ticket->tid}\n"
            . "Ticket Subject (Title): {$ticket->title}\n"
            . "Ticket Message: {$ticket->message}\n\n"
            . "INSTRUCTION: If a Matched Knowledge Base Doc is provided, integrate its exact technical recommendations into your suggested_reply!";

        // 5. Gemini API Call with Strict Schema
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key={$apiKey}", [
            'systemInstruction' => [
                'parts' => [
                    ['text' => 'You are a Senior WHMCS Support & Billing Engineer. Analyze hosting tickets with strict logic. Always explain your reasoning and reference verified docs in your reply.']
                ]
            ],
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'reasoning' => [
                            'type' => 'STRING',
                            'description' => 'Step-by-step reasoning explaining why this priority and department were chosen.',
                        ],
                        'matched_kb_article' => [
                            'type' => 'STRING',
                            'description' => 'The title of the matched knowledge base article used, or None if no doc was relevant.',
                        ],
                        'department' => [
                            'type' => 'STRING',
                            'enum' => ['Billing', 'Technical Support', 'Sales'],
                        ],                
                        'priority' => [
                            'type' => 'STRING',
                            'enum' => ['Low', 'Medium', 'High', 'Critical'],
                        ],
                        'is_invoice_mentioned' => ['type' => 'BOOLEAN'],
                        'suggested_reply' => ['type' => 'STRING'],
                        'customer_mood' => [
                            'type' => 'STRING',
                            'enum' => ['Calm', 'Frustrated', 'Angry'],
                        ],
                    ],
                    'required' => ['reasoning', 'matched_kb_article', 'department', 'priority', 'is_invoice_mentioned', 'suggested_reply', 'customer_mood'],
                ],
            ],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json()], 502);
        }

        // 6. Validate via DTO
        $jsonText = $response->json('candidates.0.content.parts.0.text');
        $dto = TicketAnalysisDTO::fromArray(json_decode((string) $jsonText, true));

        // 7. Department ID resolve karein (tblticketdepartments)
        $department = WhmcsDepartment::firstOrCreate(['name' => $dto->department]);

        // 8. Update official WHMCS table (tbltickets)
        $ticket->update([
            'did' => $department->id,
            'urgency' => $dto->priority,
            'ai_analysis' => [
                'reasoning' => $dto->reasoning,
                'matched_kb_article' => $dto->matchedKbArticle,
                'customer_mood' => $dto->customerMood,
                'is_invoice_mentioned' => $dto->isInvoiceMentioned,
                'suggested_reply' => $dto->suggestedReply,
            ],
        ]);

        // 9. Auto-create WHMCS ticket reply / staff draft (tblticketreplies)
        WhmcsTicketReply::create([
            'tid' => $ticket->id,
            'userid' => 0,
            'name' => 'AI Support Assistant',
            'email' => 'ai-support@hosting.com',
            'date' => now(),
            'message' => $dto->suggestedReply,
            'admin' => 'AI Bot',
        ]);

        return response()->json([
            'message' => 'Ticket analyzed with official WHMCS schema and updated successfully!',
            'ticket' => $ticket->fresh(['client', 'departmentRelation', 'replies']),
        ]);
    }
}
