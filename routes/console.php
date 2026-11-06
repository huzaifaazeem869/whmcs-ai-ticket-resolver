<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use App\DTOs\TicketAnalysisDTO;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('Huzaifa', function () {
    $arr = [
        'name' => 'Huzaifa',
        'role' => 'Developer',
        'skills' => ['PHP', 'Laravel', 'JavaScript'],
    ];
    $this->info("User Details:");
    print_r($arr);
});


Artisan::command('ai:test-ticket', function () {
    $apiKey = env('GEMINI_API_KEY');
    // $ticketMessage = "My website cyberhost.pk is showing 502 Bad Gateway since morning and my invoice #402 is already paid!";
    $ticketMessage = "Hi! I want to know about your Product Email Verification Extended! Can you please provide me with the details and pricing?";
    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite-preview:generateContent?key={$apiKey}", [
        'contents' => [
            [
                'parts' => [
                    ['text' => "Analyze this WHMCS support ticket: {$ticketMessage}"]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1, // Strict & logical response
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'department' => [
                        'type' => 'STRING',
                        'enum' => ['Billing', 'Technical Support', 'Sales'],
                    ],
                    'priority' => [
                        'type' => 'STRING',
                        'enum' => ['Low', 'Medium', 'High', 'Critical'],
                    ],
                    'is_invoice_mentioned' => [
                        'type' => 'BOOLEAN',
                    ],
                    'suggested_reply' => [
                        'type' => 'STRING',
                    ],
                    'customer_mood' => [
                        'type' => 'STRING',
                        'enum' => ['Calm', 'Frustrated', 'Angry'],
                    ],
                ],
                'required' => ['department', 'priority', 'is_invoice_mentioned', 'suggested_reply', 'customer_mood'],
            ],
        ],
    ]);

    if ($response->failed()) {
        $this->error("❌ API Error (" . $response->status() . "):");
        $this->line($response->body());
        return;
    }

    // Gemini ka strict JSON jawab extract karein:
    $jsonText = $response->json('candidates.0.content.parts.0.text'); // ask ai about this
    $data = json_decode((string) $jsonText, true);

    $dto = TicketAnalysisDTO::fromArray($data);
    $this->info("✅ Strict DTO Output:");
    $this->line("Department: " . $dto->department);
    $this->line("Priority:   " . $dto->priority);
    $this->line("Mood:       " . $dto->customerMood);
    $this->line("Reply:      " . $dto->suggestedReply);



    $this->info("✅ Gemini Strict JSON Output:");
    print_r($data);
});

// Artisan command to test Gemini Embedding API

Artisan::command('ai:test-embedding1', function () {
    $apiKey = env('GEMINI_API_KEY');
    $text = "How to reset my cPanel password";

    $this->info("Sending text to Gemini Embedding API: '{$text}'");

    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}", [
        'model' => 'models/gemini-embedding-001',
        'content' => [
            'parts' => [
                ['text' => $text]
            ]
        ]
    ]);

    if ($response->failed()) {
        $this->error("API Error: " . $response->body());
        return;
    }

    $vector = $response->json('embedding.values');
    $count = count($vector);

    $this->info("Success! Vector received.");
    $this->line("Total Numbers (Dimensions): {$count}");
    $this->line("First 5 Numbers: " . json_encode(array_slice($vector, 0, 5)));
});


Artisan::command('ai:test-embedding', function () {
    $apiKey = env('GEMINI_API_KEY');

    // Helper function: Kisi bhi text ka vector nikalne ke liye
    $getEmbedding = function ($text) use ($apiKey) {
        $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$apiKey}", [
            'model' => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => $text]]]
        ]);
        return $response->json('embedding.values');
    };

    // Helper function: Do vectors ke darmiyan match (Cosine Similarity) nikalne ke liye
    $cosineSimilarity = function (array $vecA, array $vecB): float {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($vecA as $i => $val) {
            $dotProduct += $val * $vecB[$i];
            $normA += $val * $val;
            $normB += $vecB[$i] * $vecB[$i];
        }
        return ($normA && $normB) ? $dotProduct / (sqrt($normA) * sqrt($normB)) : 0.0;
    };

    $this->info("Fetching embeddings for comparison...\n");

    $userQuestion = "I forgot my hosting login password";
    $article1     = "How to reset your cPanel credentials";
    $article2     = "How to configure billing credit card payment";

    $vecQuestion = $getEmbedding($userQuestion);
    $vecArticle1 = $getEmbedding($article1);
    $vecArticle2 = $getEmbedding($article2);

    $sim1 = $cosineSimilarity($vecQuestion, $vecArticle1);
    $sim2 = $cosineSimilarity($vecQuestion, $vecArticle2);

    $this->line("User Question: '{$userQuestion}'\n");
    $this->info("Article 1: '{$article1}'");
    $this->line("Similarity Score: " . round($sim1 * 100, 2) . "% Match\n");

    $this->info("Article 2: '{$article2}'");
    $this->line("Similarity Score: " . round($sim2 * 100, 2) . "% Match\n");
});


// Supabase API ke zariye embedding store aur search karne ke liye command

// 1. Articles ko Vectors ke sath Supabase Cloud par Seed karne ka command
Artisan::command('ai:seed-kb', function () {
    $geminiKey = env('GEMINI_API_KEY');
    $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
    if (str_ends_with($supabaseUrl, '/rest/v1')) {
        $supabaseUrl = substr($supabaseUrl, 0, -8);
    }
    $supabaseKey = env('SUPABASE_KEY');

    $articles = [
        [
            'title' => 'How to Fix 502 Bad Gateway Errors',
            'category' => 'Technical Support',
            'content' => 'A 502 Bad Gateway usually indicates that the PHP-FPM process pool has crashed or Nginx request timeout has expired. Check /var/log/php-fpm.log, increase max_execution_time in php.ini, and restart PHP-FPM service.',
        ],
        [
            'title' => 'Resetting cPanel and Hosting Credentials',
            'category' => 'cPanel',
            'content' => 'To reset your cPanel login, navigate to WHMCS Client Area -> Active Services -> Change Password. Alternatively, request a root password reset through the hosting support portal.',
        ],
        [
            'title' => 'Resolving Email Delivery Failures and Bounces',
            'category' => 'Email',
            'content' => 'If emails are bouncing or going to Spam, verify that your domain has valid SPF, DKIM, and DMARC TXT records configured in cPanel Zone Editor. Check server IP against Spamhaus blacklist.',
        ],
    ];

    $this->info("Seeding " . count($articles) . " articles to Supabase with Gemini Embeddings...\n");

    foreach ($articles as $art) {
        $this->line("Processing: '{$art['title']}'...");

        // Gemini se 3072 numbers ka vector lein
        $embedRes = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$geminiKey}", [
            'model' => 'models/gemini-embedding-001',
            'content' => ['parts' => [['text' => "{$art['title']}. {$art['content']}"]]]
        ]);

        if ($embedRes->failed()) {
            $this->error("Gemini failed for '{$art['title']}': " . $embedRes->body());
            continue;
        }

        $vector = $embedRes->json('embedding.values');

        // Supabase REST API ko HTTP POST karein
        $supaRes = Http::withHeaders([
            'apikey' => $supabaseKey,
            'Authorization' => "Bearer {$supabaseKey}",
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->post("{$supabaseUrl}/rest/v1/knowledge_base", [
            'title' => $art['title'],
            'category' => $art['category'],
            'content' => $art['content'],
            'embedding' => $vector,
        ]);

        if ($supaRes->failed()) {
            $this->error("Supabase insert error: " . $supaRes->body());
        } else {
            $this->info("  -> Saved in Supabase Cloud! (ID: " . $supaRes->json('0.id') . ")");
        }
    }

    $this->info("\nSeeding completed successfully!");
});

// 2. Supabase Cloud par Meaning-based Vector Search karne ka command

// Artisan::command('ai:search-kb {query=mera website 502 gateway error de raha he}', function ($query) {
Artisan::command('ai:search-kb {query=I am eating an apple}', function ($query) {
    $geminiKey = env('GEMINI_API_KEY');
    $supabaseUrl = rtrim(env('SUPABASE_URL'), '/');
    if (str_ends_with($supabaseUrl, '/rest/v1')) {
        $supabaseUrl = substr($supabaseUrl, 0, -8);
    }
    $supabaseKey = env('SUPABASE_KEY');

    $this->info("Search Query: '{$query}'");
    $this->line("1. Converting query to Vector via Gemini...");

    $embedRes = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key={$geminiKey}", [
        'model' => 'models/gemini-embedding-001',
        'content' => ['parts' => [['text' => $query]]]
    ]);

    $queryVector = $embedRes->json('embedding.values');

    $this->line("2. Querying Supabase pgvector stored procedure 'match_articles'...");

    // Supabase RPC (Remote Procedure Call) call karein
    $rpcRes = Http::withHeaders([
        'apikey' => $supabaseKey,
        'Authorization' => "Bearer {$supabaseKey}",
        'Content-Type' => 'application/json',
    ])->post("{$supabaseUrl}/rest/v1/rpc/match_articles", [
        'query_embedding' => $queryVector,
        'match_threshold' => 0.65,
        'match_count' => 2,
    ]);

    if ($rpcRes->failed()) {
        $this->error("RPC Search failed: " . $rpcRes->body());
        return;
    }

    $results = $rpcRes->json();

    if (empty($results)) {
        $this->warn("\n⚠️  No relevant knowledge base articles found for this query (Threshold: 65%).");
        return;
    }

    $this->info("\n--- MATCHING ARTICLES FOUND IN CLOUD ---");
    foreach ($results as $i => $row) {
        $score = round($row['similarity'] * 100, 2);
        $this->info("#" . ($i + 1) . " [{$row['category']}] {$row['title']} (Match: {$score}%)");
        $this->line("   Excerpt: " . substr($row['content'], 0, 110) . "...");
    }
});

Artisan::command('ai:test-issue {number?}', function ($number = null) {
    if ($number === null) {
        $this->info("======================================================================");
        $this->info("   WHMCS AI Support Ticket Auto-Resolver — 70 Issues CLI Tester       ");
        $this->info("======================================================================");
        $number = (int) $this->ask("Enter issue number to test (1 - 70)", "49");
    }

    $issue = \App\Services\HostingIssuesService::find((int) $number);
    if (!$issue) {
        $this->error("Issue #{$number} not found! Please choose between 1 and 70.");
        return 1;
    }

    $this->info("\n[TESTING ISSUE #{$issue['id']}] {$issue['category']}");
    $this->line("Title:   {$issue['title']}");
    $this->line("Subject: {$issue['subject']}");
    $this->line("Message: {$issue['message']}");
    $this->line("Invoice: {$issue['invoices']}");
    $this->comment("\nSending ticket to AI & Supabase RAG Pipeline...");

    $request = \Illuminate\Http\Request::create('/api/analyze', 'POST', [
        'license_key'      => 'HZ-AI-SUB-2026-LIVE',
        'ticket_id'        => $issue['id'],
        'client_name'      => $issue['client_name'],
        'client_email'     => $issue['client_email'],
        'subject'          => $issue['subject'],
        'message'          => $issue['message'],
        'invoices_summary' => $issue['invoices'],
    ]);
    $request->headers->set('X-AI-License-Key', 'HZ-AI-SUB-2026-LIVE');

    $startTime = microtime(true);
    $controller = app(\App\Http\Controllers\Api\WhmcsMockController::class);
    $middleware = new \App\Http\Middleware\VerifyWhmcsLicense();
    $res = $middleware->handle($request, fn ($req) => $controller->analyzeTicket($req));
    $duration = round(microtime(true) - $startTime, 2);

    $data = json_decode($res->getContent(), true);
    if ($res->getStatusCode() !== 200 || !isset($data['ticket']['ai_analysis'])) {
        $this->error("Analysis failed (HTTP {$res->getStatusCode()}): " . $res->getContent());
        return 1;
    }

    $analysis = $data['ticket']['ai_analysis'];

    $this->info("\n======================================================================");
    $this->info("                       AI RESOLUTION REPORT ({$duration}s)             ");
    $this->info("======================================================================");
    $this->table(['Metric', 'AI Output'], [
        ['Department', $analysis['department']],
        ['Urgency / Priority', $analysis['priority']],
        ['Customer Mood', $analysis['customer_mood']],
        ['Invoice Mentioned?', $analysis['is_invoice_mentioned'] ? 'Yes' : 'No'],
        ['Matched KB Doc', $analysis['matched_kb_article']],
        ['Remaining Quota', $data['license']['remaining_quota'] ?? 'N/A'],
    ]);

    $this->info("\n[STEP-BY-STEP REASONING]");
    $this->line($analysis['reasoning']);

    $this->info("\n[SUGGESTED CUSTOMER REPLY]");
    $this->comment($analysis['suggested_reply']);

    $this->info("\n[WHMCS INTERNAL STAFF NOTE PREVIEW]");
    $this->line("AddTicketReply (adminusername: AI Bot, noemail: true)");
    $this->line("Status set to: " . $data['ticket']['status'] . ", Urgency: " . $data['ticket']['urgency']);
    return 0;
});
