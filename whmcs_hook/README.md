# WHMCS AI Ticket Resolver — Client WHMCS Hook (v2.0)

This lightweight hook integrates your WHMCS installation with **Anthropic Claude**, **OpenAI**, or **Google Gemini**—supporting **100% On-Premise (Zero-Data-Leakage)** execution inside your own WHMCS server.

---

## Privacy-First Architecture (Two Deployment Modes)

1. **`on_premise` Mode (Default — GDPR Safe):**
   - Ticket bodies, customer names, email addresses, and invoice details **never** leave your WHMCS server to any external SaaS middleware.
   - Passwords, credit card numbers, and prompt injections are masked locally inside WHMCS before calling your own LLM API key (`AI_PROVIDER_API_KEY`).
   - Searches your own WHMCS `tblknowledgebase` table directly via `Capsule`.
   - Only pings the license server (`GET /api/v1/license/status`) once every 24 hours with your `license_key` and `domain`.
2. **`managed_cloud` Mode:**
   - Strips `client_email` and masks sensitive credentials locally, then routes to the managed Supabase `pgvector` RAG endpoint.

---

## 3-Minute Installation Guide

### Step 1: Upload Hook to WHMCS
Copy `ai_ticket_resolver.php` into your WHMCS directory:
```text
<whmcs_root>/includes/hooks/ai_ticket_resolver.php
```

### Step 2: Configure Provider, API Key & License
Open `ai_ticket_resolver.php` and configure the constants at the top:
```php
define('AI_DEPLOYMENT_MODE', 'on_premise'); // 'on_premise' (default) or 'managed_cloud'
define('AI_PROVIDER', 'claude');            // 'claude', 'openai', or 'gemini'
define('AI_PROVIDER_API_KEY', 'YOUR_CLAUDE_OR_OPENAI_OR_GEMINI_API_KEY');
define('AI_TICKET_RESOLVER_LICENSE', 'YOUR-WAT-LICENSE-KEY');
define('AI_TICKET_BOT_ADMIN', 'admin');     // Valid WHMCS administrator username
```

### Step 3: Test Integration
Open a test support ticket in your WHMCS client area (e.g. `"502 Bad Gateway after PHP update"`).  
Within 1–2 seconds, check the ticket in your WHMCS Admin Area — an internal staff note (`noemail: true`) containing the AI diagnosis and draft reply will appear.

---

## Fail-Safe Design
- **Non-blocking Execution:** If your API key quota runs out or a network call times out, WHMCS ticket creation continues completely unaffected.
- **Activity Log:** Errors and status warnings are recorded in WHMCS **System Logs -> Activity Log**.
