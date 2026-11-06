<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WhmcsClient;
use App\Models\WhmcsDepartment;
use App\Models\WhmcsInvoice;
use App\Models\WhmcsTicket;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WhmcsMockSeeder extends Seeder
{
    /**
     * Run the database seeds matching official WHMCS schema.
     */
    public function run(): void
    {
        // 1. Seed official WHMCS ticket departments
        $techDept = WhmcsDepartment::create([
            'name' => 'Technical Support',
            'description' => 'Server, cPanel, and website technical issues',
        ]);

        $billingDept = WhmcsDepartment::create([
            'name' => 'Billing',
            'description' => 'Invoices, payments, and account renewals',
        ]);

        $salesDept = WhmcsDepartment::create([
            'name' => 'Sales',
            'description' => 'Pre-sales inquiries and upgrade requests',
        ]);

        // 2. Seed official WHMCS clients (tblclients)
        $clients = [
            ['firstname' => 'Alice', 'lastname' => 'Corp', 'companyname' => 'Alice Corp', 'email' => 'alice@example.com', 'status' => 'Active'],
            ['firstname' => 'Bob', 'lastname' => 'Inc', 'companyname' => 'Bob Inc', 'email' => 'bob@example.com', 'status' => 'Active'],
            ['firstname' => 'Charlie', 'lastname' => 'LLC', 'companyname' => 'Charlie LLC', 'email' => 'charlie@example.com', 'status' => 'Inactive'],
        ];

        foreach ($clients as $clientData) {
            $client = WhmcsClient::create($clientData);

            // 3. Seed official WHMCS invoices (tblinvoices)
            WhmcsInvoice::create([
                'userid' => $client->id,
                'invoicenum' => 'INV-' . rand(100, 499),
                'date' => Carbon::now()->subDays(30),
                'duedate' => Carbon::now()->subDays(15),
                'total' => 50.00,
                'status' => 'Paid',
            ]);

            WhmcsInvoice::create([
                'userid' => $client->id,
                'invoicenum' => 'INV-709',
                'date' => Carbon::now()->subDays(10),
                'duedate' => Carbon::now()->subDays(2),
                'total' => 120.50,
                'status' => 'Unpaid', // Overdue/Unpaid
            ]);

            // 4. Seed official WHMCS tickets (tbltickets)
            WhmcsTicket::create([
                'tid' => 'TICK-' . rand(100000, 999999),
                'did' => $techDept->id,
                'userid' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'title' => '502 Bad Gateway',
                'message' => 'My website is showing 502 error.',
                'status' => 'Open',
                'urgency' => 'High',
                'date' => Carbon::now()->subHours(2),
            ]);

            WhmcsTicket::create([
                'tid' => 'TICK-' . rand(100000, 999999),
                'did' => $billingDept->id,
                'userid' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'title' => 'Billing Dispute',
                'message' => 'Why was I charged twice for my hosting renewal?',
                'status' => 'Customer-Reply',
                'urgency' => 'Medium',
                'date' => Carbon::now()->subHours(5),
            ]);
        }
    }
}
