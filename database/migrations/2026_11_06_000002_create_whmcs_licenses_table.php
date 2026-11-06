<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whmcs_licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('license_key')->unique()->index();
            $table->string('client_name');
            $table->string('whmcs_domain')->default('*');
            $table->string('status')->default('active'); // active, suspended, expired
            $table->string('tier')->default('growth'); // starter, growth, enterprise
            $table->unsignedInteger('monthly_limit')->default(1000);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whmcs_licenses');
    }
};
