# Specification: WHMCS Mock API Testbed

## 1. Goal
Build a lightweight WHMCS simulator API in Laravel so we can test AI ticket triage and billing automation without a paid WHMCS license.

## 2. Database Schema (Migrations & Models)

### Table 1: `whmcs_clients` (Model: `WhmcsClient`)
- `id` (bigint, PK)
- `name` (string)
- `email` (string, unique)
- `company_name` (string, nullable)
- `status` (string: 'Active', 'Inactive', 'Closed')
- `timestamps`

### Table 2: `whmcs_invoices` (Model: `WhmcsInvoice`)
- `id` (bigint, PK)
- `client_id` (foreignId -> `whmcs_clients.id`, cascadeOnDelete)
- `invoice_number` (string, unique, e.g. 'INV-402')
- `amount` (decimal 10,2)
- `status` (string: 'Paid', 'Unpaid', 'Overdue')
- `due_date` (date)
- `timestamps`

### Table 3: `whmcs_tickets` (Model: `WhmcsTicket`)
- `id` (bigint, PK)
- `client_id` (foreignId -> `whmcs_clients.id`, cascadeOnDelete)
- `subject` (string)
- `message` (text)
- `department` (string, default 'General')
- `priority` (string, default 'Medium')
- `status` (string: 'Open', 'Answered', 'Customer-Reply', 'Closed')
- `ai_analysis` (json, nullable)
- `timestamps`

## 3. Database Seeder (`WhmcsMockSeeder`)
Seed 3 realistic hosting clients, 5 invoices (some Paid, some Overdue), and 5 realistic web hosting support tickets (e.g., 502 Bad Gateway, DNS propagation, cPanel password reset, Invoice #402 billing dispute, Sales inquiry).

## 4. REST API Endpoints (`routes/api.php` & `App\Http\Controllers\Api\WhmcsMockController`)
- `GET /api/clients` -> List all clients with invoice & ticket counts.
- `GET /api/invoices` -> List all invoices with client info (filterable by `?status=Unpaid`).
- `GET /api/tickets` -> List all tickets with client info.
- `GET /api/tickets/{id}` -> Show single ticket with client and their recent invoices.