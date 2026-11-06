<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations matching official WHMCS schema.
     */
    public function up(): void
    {
        // 1. Official WHMCS Clients Table (tblclients)
        Schema::create('tblclients', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->default('');
            $table->string('companyname')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        // 2. Official WHMCS Invoices Table (tblinvoices)
        Schema::create('tblinvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userid')->constrained('tblclients')->cascadeOnDelete();
            $table->string('invoicenum')->nullable();
            $table->date('date')->nullable();
            $table->date('duedate')->nullable();
            $table->decimal('total', 16, 2)->default(0.00);
            $table->string('status')->default('Unpaid'); // Paid, Unpaid, Cancelled, Overdue
            $table->timestamps();
        });

        // 3. Official WHMCS Ticket Departments (tblticketdepartments)
        Schema::create('tblticketdepartments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 4. Official WHMCS Tickets Table (tbltickets)
        Schema::create('tbltickets', function (Blueprint $table) {
            $table->id();
            $table->string('tid', 128)->nullable(); // Ticket Masking ID (e.g. ABC-102938)
            $table->foreignId('did')->constrained('tblticketdepartments'); // Department ID
            $table->foreignId('userid')->constrained('tblclients')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->text('title'); // WHMCS Ticket Subject
            $table->text('message'); // Ticket Body
            $table->string('status', 64)->default('Open'); // Open, Answered, Customer-Reply, Closed
            $table->string('urgency', 64)->default('Medium'); // Low, Medium, High, Critical (WHMCS Priority)
            $table->dateTime('date')->nullable();
            $table->json('ai_analysis')->nullable(); // Extension column for storing AI JSON & RAG metadata
            $table->timestamps();
        });

        // 5. Official WHMCS Ticket Replies Table (tblticketreplies)
        Schema::create('tblticketreplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tid')->constrained('tbltickets')->cascadeOnDelete();
            $table->foreignId('userid')->default(0);
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->dateTime('date')->nullable();
            $table->text('message');
            $table->string('admin')->nullable(); // Admin name if reply is by staff or AI Assistant
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tblticketreplies');
        Schema::dropIfExists('tbltickets');
        Schema::dropIfExists('tblticketdepartments');
        Schema::dropIfExists('tblinvoices');
        Schema::dropIfExists('tblclients');
    }
};
