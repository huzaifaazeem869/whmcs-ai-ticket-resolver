<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TicketAnalysisDTO;
use App\Http\Controllers\Controller;
use App\Models\WhmcsClient;
use App\Models\WhmcsDepartment;
use App\Models\WhmcsInvoice;
use App\Models\WhmcsLicense;
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

    /**
     * Check status and remaining quota of the verified SaaS license.
     */
    public function licenseStatus(Request $request): JsonResponse
    {
        /** @var WhmcsLicense|null $license */
        $license = $request->attributes->get('license');

        if (!$license) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No license context available.',
            ], 400);
        }

        return response()->json([
            'status'  => 'success',
            'license' => [
                'license_key'     => $license->license_key,
                'client_name'     => $license->client_name,
                'status'          => $license->status,
                'tier'            => $license->tier,
                'monthly_limit'   => $license->monthly_limit,
                'usage_count'     => $license->usage_count,
                'remaining_quota' => $license->remainingQuota(),
                'last_used_at'    => $license->last_used_at?->toIso8601String(),
                'expires_at'      => $license->expires_at?->toIso8601String(),
            ],
            'portal'  => 'https://huzaifaazeem.com/',
        ]);
    }

    /**
     * Analyze ticket with Supabase pgvector RAG + Gemini Chain-of-Thought.
     * Supports both local mock DB testing and remote production WHMCS Hooks.
     */
    public function analyzeTicket(Request $request, ?int $id = null): JsonResponse
    {
        /** @var WhmcsLicense|null $license */
        $license = $request->attributes->get('license');

        $isRemotePayload = $request->filled('subject') && $request->filled('message');
        $localTicket = null;

        if ($isRemotePayload) {
            // 1a. Payload from Live WHMCS Hook
            $subject        = (string) $request->input('subject');
            $message        = (string) $request->input('message');
            $clientName     = (string) $request->input('client_name', 'Valued Client');
            $clientEmail    = (string) $request->input('client_email', '');
            $invoiceContext = (string) $request->input('invoices_summary', 'No invoices provided');
            $ticketId       = (int) ($id ?? $request->input('ticket_id', 0));
            $ticketTid      = 'TICK-' . ($ticketId > 0 ? $ticketId : rand(100000, 999999));
        } else {
            // 1b. Payload from Local Testbed Mock DB
            $targetId = $id ?? (int) $request->input('ticket_id', 1);
            $localTicket = WhmcsTicket::with('client.invoices')->findOrFail($targetId);

            $subject        = $localTicket->title;
            $message        = $localTicket->message;
            $clientName     = $localTicket->client->name;
            $clientEmail    = $localTicket->client->email;
            $invoiceContext = $localTicket->client->invoices
                ->map(fn ($inv) => "{$inv->invoicenum}: \${$inv->total} ({$inv->status})")
                ->implode(', ');
            $ticketId       = $localTicket->id;
            $ticketTid      = $localTicket->tid;
        }

        $apiKey = env('GEMINI_API_KEY');
        $supabaseUrl = rtrim((string) env('SUPABASE_URL'), '/');
        if (str_ends_with($supabaseUrl, '/rest/v1')) {
            $supabaseUrl = substr($supabaseUrl, 0, -8);
        }
        $supabaseKey = env('SUPABASE_KEY');

        // 2. RAG: Vectorize ticket text and match verified KB article from Cloud Supabase pgvector
        $ticketText = "{$subject}. {$message}";
        $matchedKbDoc = "None (No relevant knowledge base doc found)";

        $embedRes = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}", [
            'model'   => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => $ticketText]]],
        ]);

        if ($embedRes->successful()) {
            $queryVector = $embedRes->json('embedding.values');

            $rpcRes = Http::withHeaders([
                'apikey'        => $supabaseKey,
                'Authorization' => "Bearer {$supabaseKey}",
                'Content-Type'  => 'application/json',
            ])->post("{$supabaseUrl}/rest/v1/rpc/match_articles", [
                'query_embedding' => $queryVector,
                'match_threshold' => 0.60,
                'match_count'     => 1,
            ]);

            if ($rpcRes->successful() && !empty($rpcRes->json())) {
                $topDoc = $rpcRes->json('0');
                $matchedKbDoc = "[{$topDoc['category']}] {$topDoc['title']}: {$topDoc['content']}";
            }
        }

        // 3. Prompt Construction with RAG & WHMCS Context
        $prompt = "Analyze this WHMCS support ticket using the provided verified Knowledge Base documentation and client billing context.\n\n"
            . "Client Name: {$clientName} ({$clientEmail})\n"
            . "Client Invoices in WHMCS: {$invoiceContext}\n"
            . "Matched Knowledge Base Doc: {$matchedKbDoc}\n"
            . "Ticket Masking ID: {$ticketTid}\n"
            . "Ticket Subject (Title): {$subject}\n"
            . "Ticket Message: {$message}\n\n"
            . "INSTRUCTION: If a Matched Knowledge Base Doc is provided, integrate its exact technical recommendations into your suggested_reply!";

        // 4. Gemini API Call with Chain-of-Thought & Strict JSON Schema
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key={$apiKey}", [
            'systemInstruction' => [
                'parts' => [
                    ['text' => 'You are a Senior WHMCS Support & Billing Engineer. Analyze hosting tickets with strict logic. Always explain your step-by-step reasoning and reference verified docs in your reply.']
                ]
            ],
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.1,
                'responseMimeType' => 'application/json',
                'responseSchema'   => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'reasoning' => [
                            'type'        => 'STRING',
                            'description' => 'Step-by-step reasoning explaining why this priority and department were chosen.',
                        ],
                        'matched_kb_article' => [
                            'type'        => 'STRING',
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
                        'suggested_reply'      => ['type' => 'STRING'],
                        'customer_mood'        => [
                            'type' => 'STRING',
                            'enum' => ['Calm', 'Frustrated', 'Angry'],
                        ],
                    ],
                    'required' => [
                        'reasoning',
                        'matched_kb_article',
                        'department',
                        'priority',
                        'is_invoice_mentioned',
                        'suggested_reply',
                        'customer_mood',
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            return response()->json(['error' => $response->json()], 502);
        }

        // 5. Validate through Type-Safe DTO
        $jsonText = $response->json('candidates.0.content.parts.0.text');
        $dto = TicketAnalysisDTO::fromArray(json_decode((string) $jsonText, true));

        // 6. Update local DB if local ticket exists
        if ($localTicket) {
            $dept = WhmcsDepartment::firstOrCreate(['name' => $dto->department]);
            $localTicket->did = $dept->id;
            $localTicket->urgency = $dto->priority;
            $localTicket->ai_analysis = $dto->toArray();
            $localTicket->save();

            WhmcsTicketReply::create([
                'tid'     => $localTicket->id,
                'userid'  => 0,
                'name'    => 'AI Assistant (Draft)',
                'email'   => 'ai-bot@huzaifaazeem.com',
                'date'    => now(),
                'message' => "[AI Auto-Draft - Internal Review Required]\n"
                    . "Reasoning: {$dto->reasoning}\n"
                    . "Matched KB: {$dto->matchedKbArticle}\n\n"
                    . $dto->suggestedReply,
                'admin'   => 'AI Bot',
            ]);
        }

        return response()->json([
            'message' => 'Ticket analyzed successfully via Supabase RAG and Gemini AI.',
            'license' => [
                'client'          => $license?->client_name,
                'tier'            => $license?->tier,
                'remaining_quota' => $license?->remainingQuota(),
            ],
            'ticket'  => [
                'id'          => $ticketId,
                'tid'         => $ticketTid,
                'status'      => 'Open',
                'urgency'     => $dto->priority,
                'department'  => $dto->department,
                'ai_analysis' => $dto->toArray(),
            ],
        ]);
    }
}
