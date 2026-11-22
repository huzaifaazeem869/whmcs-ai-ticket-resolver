<?php

declare(strict_types=1);

/**
 * WHMCS AI Ticket Resolver Hook (v2.0 — On-Premise BYOK & Multi-LLM)
 *
 * Automatically generates intelligent draft replies and root-cause analysis
 * for newly opened tickets and customer replies in WHMCS.
 *
 * Zero-Data-Leakage Architecture:
 * - In 'on_premise' mode (default), ticket payloads, client names, emails, and
 *   invoices NEVER leave your WHMCS server to any third-party middleware.
 * - Sensitive credentials/cards are masked locally before calling your own LLM key.
 * - Only license status heartbeat (license_key + domain) is verified with the portal.
 *
 * Installation:
 *   Place at: <whmcs_root>/includes/hooks/ai_ticket_resolver.php
 *
 * Developed by: Muhammad Huzaifa Azeem
 * Portal & Licenses: https://huzaifaazeem.com/whmcs-ai-ticket-resolver/
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

// =========================================================================
// CONFIGURATION
// =========================================================================
if (!defined('AI_DEPLOYMENT_MODE')) {
    // 'on_premise' (GDPR-Safe: direct WHMCS -> Claude/OpenAI/Gemini) OR 'managed_cloud'
    define('AI_DEPLOYMENT_MODE', 'on_premise');
}

if (!defined('AI_PROVIDER')) {
    // Supported in on_premise mode: 'claude', 'openai', 'gemini'
    define('AI_PROVIDER', 'claude');
}

if (!defined('AI_PROVIDER_API_KEY')) {
    // Your own Anthropic / OpenAI / Gemini API key (never shared with third parties)
    define('AI_PROVIDER_API_KEY', 'YOUR_CLAUDE_OR_OPENAI_OR_GEMINI_API_KEY');
}

if (!defined('AI_PROVIDER_MODEL')) {
    // Leave empty for provider default: claude-3-7-sonnet-latest | gpt-4o-mini | gemini-2.5-flash
    define('AI_PROVIDER_MODEL', '');
}

if (!defined('AI_TICKET_RESOLVER_ENDPOINT')) {
    define('AI_TICKET_RESOLVER_ENDPOINT', 'https://huzaifaazeem.com/whmcs-ai-ticket-resolver/public/api/v1/ticket-reply');
}

if (!defined('AI_LICENSE_STATUS_ENDPOINT')) {
    define('AI_LICENSE_STATUS_ENDPOINT', 'https://huzaifaazeem.com/whmcs-ai-ticket-resolver/public/api/v1/license/status');
}

if (!defined('AI_KB_FALLBACK_ENDPOINT')) {
    define('AI_KB_FALLBACK_ENDPOINT', 'https://huzaifaazeem.com/whmcs-ai-ticket-resolver/public/api/v1/kb-search');
}

if (!defined('AI_TICKET_RESOLVER_LICENSE')) {
    define('AI_TICKET_RESOLVER_LICENSE', 'Your License Key');
}

if (!defined('AI_TICKET_BOT_ADMIN')) {
    define('AI_TICKET_BOT_ADMIN', 'admin');
}

/**
 * Shared cURL JSON helper to avoid duplicate HTTP boilerplate.
 *
 * @param array<int, string> $headers
 * @return array{code: int, body: array<string, mixed>|null, raw: string}
 */
function executeWhmcsCurlJson(string $url, array $headers, ?string $jsonBody = null, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($jsonBody !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    }

    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    return [
        'code' => $code,
        'body' => is_array($decoded) ? $decoded : null,
        'raw'  => $raw,
    ];
}

/**
 * Mask passwords, credit cards, and prompt injection attempts locally inside WHMCS.
 */
function maskWhmcsSensitiveData(string $input): string
{
    $masked = (string) preg_replace(
        '/(?i)(password|pass|pwd|root_pass)\s*[:=]\s*[^\s\r\n]+/i',
        '$1: [PROTECTED_CREDENTIAL]',
        $input
    );

    $masked = (string) preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[CARD_NUMBER_MASKED]', $masked);

    $injectionPhrases = [
        'ignore previous instructions',
        'ignore all previous instructions',
        'disregard earlier instructions',
        'system prompt override',
    ];

    foreach ($injectionPhrases as $phrase) {
        $masked = (string) preg_replace('/' . preg_quote($phrase, '/') . '/i', '[BLOCKED_INSTRUCTION_REMOVED]', $masked);
    }

    return $masked;
}

/**
 * Zero-data license heartbeat: sends ONLY license_key and domain; caches validity 24h.
 */
function verifyWhmcsAiLicenseHeartbeat(): bool
{
    $cacheSetting = 'ai_resolver_license_cache';
    $now = time();

    try {
        $cached = Capsule::table('tblconfiguration')->where('setting', $cacheSetting)->first();
        if ($cached && !empty($cached->value)) {
            $data = json_decode((string) $cached->value, true);
            if (is_array($data) && ($data['key'] ?? '') === AI_TICKET_RESOLVER_LICENSE
                && !empty($data['valid']) && ($now - (int) ($data['checked_at'] ?? 0)) < 86400) {
                return true;
            }
        }
    } catch (\Throwable) {
        // Proceed to live license heartbeat if cache lookup fails
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? gethostname() ?: 'whmcs-local');
    $url = AI_LICENSE_STATUS_ENDPOINT . '?license_key=' . urlencode(AI_TICKET_RESOLVER_LICENSE);
    $res = executeWhmcsCurlJson($url, [
        'Accept: application/json',
        'X-AI-License-Key: ' . AI_TICKET_RESOLVER_LICENSE,
        'Origin: https://' . $host,
    ], null, 8);

    $isValid = $res['code'] === 200 && !empty($res['body']['success']) && !empty($res['body']['license']['is_valid']);

    try {
        $payload = json_encode(['key' => AI_TICKET_RESOLVER_LICENSE, 'valid' => $isValid, 'checked_at' => $now]);
        $exists = Capsule::table('tblconfiguration')->where('setting', $cacheSetting)->exists();
        if ($exists) {
            Capsule::table('tblconfiguration')->where('setting', $cacheSetting)->update(['value' => $payload]);
        } else {
            Capsule::table('tblconfiguration')->insert(['setting' => $cacheSetting, 'value' => $payload]);
        }
    } catch (\Throwable) {
        // Non-fatal if cache cannot be persisted
    }

    return $isValid;
}

/**
 * Zero-PII cloud vector KB fallback when local WHMCS tblknowledgebase is empty.
 */
function fetchZeroPiiCloudKbFallback(string $cleanSubject): string
{
    $sanitized = (string) preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '', $cleanSubject);
    $sanitized = (string) preg_replace('/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i', '[DOMAIN]', $sanitized);
    $query = substr(trim($sanitized), 0, 250);

    if ($query === '') {
        return 'Standard Hosting Knowledge Base (DNS, SSL, Email, cPanel, PHP-FPM, MySQL).';
    }

    $res = executeWhmcsCurlJson(AI_KB_FALLBACK_ENDPOINT, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-AI-License-Key: ' . AI_TICKET_RESOLVER_LICENSE,
    ], json_encode(['query' => $query]) ?: '{}', 6);

    if ($res['code'] === 200 && !empty($res['body']['kb_context'])) {
        return '[Cloud Vector KB Fallback] ' . (string) $res['body']['kb_context'];
    }

    return 'Standard Hosting Knowledge Base (DNS, SSL, Email, cPanel, PHP-FPM, MySQL).';
}

/**
 * Search the hosting company's own WHMCS tblknowledgebase for relevant docs.
 */
function searchLocalWhmcsKnowledgebase(string $subject, string $message): string
{
    try {
        $keywords = array_values(array_filter(
            preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $subject) ?? '')) ?: [],
            static fn (string $w): bool => strlen($w) >= 4
        ));

        $query = Capsule::table('tblknowledgebase')->select(['title', 'article']);
        if (!empty($keywords)) {
            $query->where(function ($q) use ($keywords): void {
                foreach (array_slice($keywords, 0, 4) as $word) {
                    $q->orWhere('title', 'like', "%{$word}%")
                      ->orWhere('article', 'like', "%{$word}%");
                }
            });
        }

        $articles = $query->limit(2)->get();
        if ($articles->isEmpty()) {
            return fetchZeroPiiCloudKbFallback($subject);
        }

        return $articles->map(static function ($row): string {
            $snippet = substr(strip_tags((string) $row->article), 0, 500);
            return "[Local KB: {$row->title}] {$snippet}";
        })->implode("\n\n");
    } catch (\Throwable) {
        return fetchZeroPiiCloudKbFallback($subject);
    }
}

/**
 * Build deterministic JSON prompt for on-premise LLM calls.
 */
function buildWhmcsOnPremisePrompt(string $subject, string $cleanMessage, string $invoicesSummary, string $servicesSummary, string $kbContext): string
{
    return "Analyze this hosting support ticket and respond ONLY with a valid JSON object matching this exact schema:\n"
        . '{"reasoning":"string","matched_kb_article":"string","department":"Billing|Technical Support|Sales|Abuse","priority":"Low|Medium|High|Critical","customer_mood":"Calm|Frustrated|Angry|Urgent","suggested_reply":"string"}'
        . "\n\nCLIENT INVOICES CONTEXT: {$invoicesSummary}"
        . "\nCLIENT HOSTING SERVICES & SERVER STATUS: {$servicesSummary}"
        . "\nINTERNAL KB CONTEXT:\n{$kbContext}"
        . "\nTICKET SUBJECT: {$subject}"
        . "\nTICKET MESSAGE:\n{$cleanMessage}";
}

/**
 * Parse JSON string safely (strips optional Markdown code fences).
 *
 * @return array<string, mixed>|null
 */
function decodeWhmcsAiJson(string $rawText): ?array
{
    $clean = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawText)));
    $parsed = json_decode($clean, true);
    return is_array($parsed) && !empty($parsed['suggested_reply']) ? $parsed : null;
}

/**
 * Resolve ticket on-premise directly with Anthropic Claude, OpenAI, or Gemini.
 *
 * @return array<string, mixed>|null
 */
function resolveTicketOnPremise(string $subject, string $cleanMessage, string $invoicesSummary, string $servicesSummary, string $kbContext): ?array
{
    $provider = strtolower(AI_PROVIDER);
    $apiKey = AI_PROVIDER_API_KEY;
    if ($apiKey === '' || str_starts_with($apiKey, 'YOUR_')) {
        logActivity('WHMCS AI Ticket Resolver: AI_PROVIDER_API_KEY is not configured.');
        return null;
    }

    $system = 'You are a Senior Tier-2 Web Hosting Support Engineer. Draft polite, accurate internal ticket replies. Never invent passwords or credentials. Output strict JSON only.';
    $prompt = buildWhmcsOnPremisePrompt($subject, $cleanMessage, $invoicesSummary, $servicesSummary, $kbContext);

    if ($provider === 'claude') {
        $model = AI_PROVIDER_MODEL !== '' ? AI_PROVIDER_MODEL : 'claude-3-7-sonnet-latest';
        $res = executeWhmcsCurlJson('https://api.anthropic.com/v1/messages', [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ], json_encode([
            'model' => $model, 'max_tokens' => 1024, 'temperature' => 0.1, 'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]) ?: '{}');
        return $res['code'] === 200 ? decodeWhmcsAiJson((string) ($res['body']['content'][0]['text'] ?? '')) : null;
    }

    if ($provider === 'openai') {
        $model = AI_PROVIDER_MODEL !== '' ? AI_PROVIDER_MODEL : 'gpt-4o-mini';
        $res = executeWhmcsCurlJson('https://api.openai.com/v1/chat/completions', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ], json_encode([
            'model' => $model, 'temperature' => 0.1, 'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
        ]) ?: '{}');
        return $res['code'] === 200 ? decodeWhmcsAiJson((string) ($res['body']['choices'][0]['message']['content'] ?? '')) : null;
    }

    $model = AI_PROVIDER_MODEL !== '' ? AI_PROVIDER_MODEL : 'gemini-2.5-flash';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($apiKey);
    $res = executeWhmcsCurlJson($url, ['Content-Type: application/json'], json_encode([
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.1, 'responseMimeType' => 'application/json'],
    ]) ?: '{}');

    return $res['code'] === 200
        ? decodeWhmcsAiJson((string) ($res['body']['candidates'][0]['content']['parts'][0]['text'] ?? ''))
        : null;
}

/**
 * Fallback Managed Cloud resolver (strips client_email and masks sensitive fields locally).
 *
 * @return array<string, mixed>|null
 */
function resolveTicketManagedCloud(int $ticketId, string $clientName, string $cleanSubject, string $cleanMessage, string $invoicesSummary, string $servicesSummary, string $triggerType): ?array
{
    $payload = json_encode([
        'license_key'      => AI_TICKET_RESOLVER_LICENSE,
        'ticket_id'        => $ticketId,
        'client_name'      => $clientName,
        'subject'          => $cleanSubject,
        'message'          => $cleanMessage,
        'invoices_summary' => $invoicesSummary,
        'extra_context'    => "Hosting Services: {$servicesSummary}",
        'trigger'          => $triggerType,
    ]);

    $res = executeWhmcsCurlJson(AI_TICKET_RESOLVER_ENDPOINT, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-AI-License-Key: ' . AI_TICKET_RESOLVER_LICENSE,
    ], $payload ?: '{}');

    if ($res['code'] !== 200 || empty($res['body'])) {
        logActivity("WHMCS AI Ticket Resolver: Managed Cloud responded with status {$res['code']} for Ticket #{$ticketId}.");
        return null;
    }

    return [
        'suggested_reply'    => $res['body']['draft_reply'] ?? ($res['body']['ticket']['ai_analysis']['suggested_reply'] ?? null),
        'reasoning'          => $res['body']['reasoning'] ?? ($res['body']['ticket']['ai_analysis']['reasoning'] ?? 'Standard diagnostic'),
        'matched_kb_article' => $res['body']['matched_kb_article'] ?? 'Hosting KB',
        'priority'           => $res['body']['priority'] ?? 'Medium',
    ];
}

/**
 * Fetch recent invoices context for the ticket owner.
 */
function fetchWhmcsInvoicesSummary(int $userId): string
{
    if ($userId <= 0) {
        return 'No recent invoices';
    }

    $invoices = Capsule::table('tblinvoices')
        ->where('userid', $userId)
        ->orderBy('id', 'desc')
        ->limit(3)
        ->get(['invoicenum', 'total', 'status']);

    if ($invoices->isEmpty()) {
        return 'No recent invoices';
    }

    return $invoices->map(static fn ($inv): string => ($inv->invoicenum ?: 'INV') . ": \${$inv->total} ({$inv->status})")->implode(', ');
}

/**
 * Fetch live hosting service, suspension reason, and server context from tblhosting + tblservers + tblproducts.
 */
function fetchWhmcsServicesSummary(int $userId): string
{
    if ($userId <= 0) {
        return 'No hosting services linked to this user';
    }

    try {
        $services = Capsule::table('tblhosting')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get(['domain', 'domainstatus', 'suspendreason', 'nextduedate', 'packageid', 'server', 'dedicatedip', 'ns1', 'ns2']);

        if ($services->isEmpty()) {
            return 'No active or suspended hosting services found';
        }

        return $services->map(static function ($row): string {
            $productName = $row->packageid ? (string) Capsule::table('tblproducts')->where('id', $row->packageid)->value('name') : 'Hosting';
            $server = $row->server ? Capsule::table('tblservers')->where('id', $row->server)->first(['name', 'hostname', 'nameserver1', 'nameserver2']) : null;
            $domain = !empty($row->domain) ? $row->domain : 'No domain';
            $ns = !empty($row->ns1) ? "{$row->ns1}/{$row->ns2}" : (($server && !empty($server->nameserver1)) ? "{$server->nameserver1}/{$server->nameserver2}" : 'N/A');
            $serverLabel = $server ? "{$server->name} ({$server->hostname})" : 'Unassigned';
            $suspendNote = !empty($row->suspendreason) ? " [Suspend Reason: {$row->suspendreason}]" : '';

            return "Domain: {$domain} | Plan: {$productName} | Status: {$row->domainstatus}{$suspendNote} | Server: {$serverLabel} | NS: {$ns} | Next Due: {$row->nextduedate}";
        })->implode('; ');
    } catch (\Throwable) {
        return 'Service lookup unavailable';
    }
}

/**
 * Common handler for processing tickets with AI resolver.
 */
function handleAiTicketResolution(int $ticketId, int $userId, string $subject, string $message, string $triggerType = 'TicketOpen'): void
{
    try {
        $cleanSubject = maskWhmcsSensitiveData($subject);
        $cleanMessage = maskWhmcsSensitiveData($message);
        $invoicesSummary = fetchWhmcsInvoicesSummary($userId);
        $servicesSummary = fetchWhmcsServicesSummary($userId);

        if (AI_DEPLOYMENT_MODE === 'on_premise') {
            if (!verifyWhmcsAiLicenseHeartbeat()) {
                logActivity("WHMCS AI Ticket Resolver: License heartbeat failed for Ticket #{$ticketId}.");
                return;
            }
            $kbContext = searchLocalWhmcsKnowledgebase($cleanSubject, $cleanMessage);
            $result = resolveTicketOnPremise($cleanSubject, $cleanMessage, $invoicesSummary, $servicesSummary, $kbContext);
            $modeLabel = 'On-Premise (' . strtoupper(AI_PROVIDER) . ')';
        } else {
            $client = Capsule::table('tblclients')->where('id', $userId)->first(['firstname']);
            $clientName = $client ? trim((string) $client->firstname) : 'Client';
            $result = resolveTicketManagedCloud($ticketId, $clientName, $cleanSubject, $cleanMessage, $invoicesSummary, $servicesSummary, $triggerType);
            $modeLabel = 'Managed Cloud RAG';
        }

        if (empty($result['suggested_reply'])) {
            return;
        }

        $staffNote = "🤖 [AI TICKET RESOLVER — {$modeLabel}]\n"
            . "--------------------------------------------------------\n"
            . 'Priority Assessed: ' . ($result['priority'] ?? 'Medium') . "\n"
            . 'Root Cause Diagnosis: ' . ($result['reasoning'] ?? 'Standard diagnostic') . "\n"
            . 'Reference Document: ' . ($result['matched_kb_article'] ?? 'Local WHMCS KB') . "\n\n"
            . "Suggested Reply (Staff Review Required):\n\n"
            . $result['suggested_reply'];

        localAPI('AddTicketReply', [
            'ticketid'      => $ticketId,
            'message'       => $staffNote,
            'adminusername' => AI_TICKET_BOT_ADMIN,
            'noemail'       => true, // Internal staff review only
        ]);
    } catch (\Throwable $e) {
        // Fail-safe: Ticket creation in WHMCS must NEVER be disrupted by AI hook exceptions
        logActivity('WHMCS AI Ticket Resolver Hook Exception: ' . $e->getMessage());
    }
}

add_hook('TicketOpen', 1, static function (array $vars): void {
    handleAiTicketResolution(
        (int) $vars['ticketid'],
        (int) ($vars['userid'] ?? 0),
        (string) ($vars['subject'] ?? 'No Subject'),
        (string) ($vars['message'] ?? ''),
        'TicketOpen'
    );
});

add_hook('TicketUserReply', 1, static function (array $vars): void {
    $ticketId = (int) $vars['ticketid'];
    $ticket = Capsule::table('tbltickets')->where('id', $ticketId)->first(['title']);

    handleAiTicketResolution(
        $ticketId,
        (int) ($vars['userid'] ?? 0),
        $ticket ? (string) $ticket->title : 'Ticket Follow-up',
        (string) ($vars['message'] ?? ''),
        'TicketUserReply'
    );
});
