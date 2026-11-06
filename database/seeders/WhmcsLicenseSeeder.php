<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\WhmcsLicense;
use Illuminate\Database\Seeder;

class WhmcsLicenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        WhmcsLicense::updateOrCreate(
            ['license_key' => 'HZ-AI-SUB-2026-LIVE'],
            [
                'client_name'   => 'Live WHMCS Partner (Huzaifa Enterprise)',
                'whmcs_domain'  => '*',
                'status'        => 'active',
                'tier'          => 'enterprise',
                'monthly_limit' => 5000,
                'usage_count'   => 0,
                'expires_at'    => now()->addYear(),
            ]
        );

        WhmcsLicense::updateOrCreate(
            ['license_key' => 'HZ-AI-DEMO-2026'],
            [
                'client_name'   => 'Demo & Testing WHMCS Client',
                'whmcs_domain'  => '*',
                'status'        => 'active',
                'tier'          => 'growth',
                'monthly_limit' => 500,
                'usage_count'   => 0,
                'expires_at'    => now()->addMonths(6),
            ]
        );

        WhmcsLicense::updateOrCreate(
            ['license_key' => 'HZ-AI-EXPIRED-TEST'],
            [
                'client_name'   => 'Expired Test Account',
                'whmcs_domain'  => '*',
                'status'        => 'expired',
                'tier'          => 'starter',
                'monthly_limit' => 100,
                'usage_count'   => 100,
                'expires_at'    => now()->subDay(),
            ]
        );
    }
}
