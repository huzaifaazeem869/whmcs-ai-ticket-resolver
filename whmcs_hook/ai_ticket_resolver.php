<?php
/**
 * WHMCS AI Support Ticket Auto-Resolver & RAG Hook
 * 
 * Drop this file into your WHMCS installation under:
 *   /includes/hooks/ai_ticket_resolver.php
 * 
 * Developed by: Huzaifa Azeem (AI & Infrastructure Automation Engineer)
 * Website: https://huzaifaazeem.com/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('TicketOpen', 1, function ($vars) {
    $ticketId = (int) $vars['ticketid'];
    $userId   = (int) $vars['userid'];
    $subject  = (string) $vars['subject'];
    $message  = (string) $vars['message'];

    // 1. Fetch recent client invoices from WHMCS database
    $invoicesSummary = "No invoices found";
    if ($userId > 0) {
        $invoices = Capsule::table('tblinvoices')
            ->where('userid', $userId)
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get(['invoicenum', 'total', 'status']);

        if ($invoices->isNotEmpty()) {
            $invoicesSummary = $invoices->map(function ($inv) {
                return ($inv->invoicenum ?: 'INV') . ": $" . $inv->total . " (" . $inv->status . ")";
            })->implode(', ');
        }
    }

    // 2. Client Details
    $client = Capsule::table('tblclients')->where('id', $userId)->first(['firstname', 'lastname', 'email']);
    $clientName = $client ? trim("{$client->firstname} {$client->lastname}") : ($vars['name'] ?? 'Guest');
    $clientEmail = $client ? $client->email : ($vars['email'] ?? '');

    // 3. Configure API Endpoint & License Key
    // Replace with your self-hosted API URL or your Managed License endpoint
    $apiEndpoint = "http://127.0.0.1:8000/api/tickets/{$ticketId}/analyze";
    $licenseKey  = "YOUR_LICENSE_KEY_HERE";

    $payload = json_encode([
        'license_key'      => $licenseKey,
        'ticket_id'        => $ticketId,
        'client_name'      => $clientName,
        'client_email'     => $clientEmail,
        'subject'          => $subject,
        'message'          => $message,
        'invoices_summary' => $invoicesSummary,
    ]);

    $ch = curl_init($apiEndpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-AI-License-Key: ' . $licenseKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        logActivity("AI Ticket Resolver API call failed for Ticket #{$ticketId} (HTTP {$httpCode})");
        return;
    }

    $data = json_decode($response, true);
    $analysis = $data['ticket']['ai_analysis'] ?? null;

    if (!$analysis || empty($analysis['suggested_reply'])) {
        return;
    }

    // 4. Inject Verified Solution as an Internal Staff Note in WHMCS
    $staffNote = "🤖 [AI Auto-Analysis & Verified KB Solution]\n"
        . "--------------------------------------------------\n"
        . "Reasoning: " . ($analysis['reasoning'] ?? 'N/A') . "\n"
        . "Matched KB: " . ($analysis['matched_kb_article'] ?? 'None') . "\n"
        . "Client Mood: " . ($analysis['customer_mood'] ?? 'Calm') . "\n\n"
        . "Suggested Customer Reply:\n"
        . $analysis['suggested_reply'];

    localAPI('AddTicketReply', [
        'ticketid'      => $ticketId,
        'message'       => $staffNote,
        'adminusername' => 'AI Bot',
        'status'        => $data['ticket']['status'] ?? 'Open',
        'noemail'       => true, // Human-in-the-loop review before sending
    ]);

    // 5. Update Ticket Urgency / Department if provided
    if (!empty($data['ticket']['urgency'])) {
        localAPI('UpdateTicket', [
            'ticketid' => $ticketId,
            'urgency'  => $data['ticket']['urgency'],
        ]);
    }

    logActivity("AI Ticket Resolver successfully analyzed Ticket #{$ticketId} via RAG knowledge base.");
});
