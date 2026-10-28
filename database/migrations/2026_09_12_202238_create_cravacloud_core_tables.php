<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_specs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->integer('cpu');
            $table->integer('ram');
            $table->integer('disk');
            $table->integer('bandwidth');
            $table->decimal('cost_price', 12, 2);
            $table->decimal('sell_price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vps_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('hostname')->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->string('private_ip', 45)->nullable();
            $table->string('os', 50)->nullable();
            $table->enum('status', ['running', 'stopped', 'provisioning', 'error'])->default('provisioning');
            $table->integer('cpu')->nullable();
            $table->integer('ram')->nullable();
            $table->integer('disk')->nullable();
            $table->enum('control_panel', ['coolify', 'dokploy', 'cpanel', 'hermes_omniroute', 'openclaw_omniroute'])->nullable();
            $table->float('uptime_percent')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vps_spec_id')->constrained('vps_specs');
            $table->enum('control_panel', ['coolify', 'dokploy', 'cpanel', 'hermes_omniroute', 'openclaw_omniroute']);
            $table->enum('datacenter_location', ['singapore', 'indonesia']);
            $table->enum('os', ['ubuntu2404', 'ubuntu2204']);
            $table->enum('status', ['pending', 'provisioning', 'active', 'cancelled', 'expired'])->default('pending');
            $table->enum('channel', ['shopee', 'website']);
            $table->string('shopee_order_id')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vps_instance_id')->nullable()->constrained('vps_instances')->nullOnDelete();
            $table->string('subject');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_admin_reply')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('vps_instances');
        Schema::dropIfExists('vps_specs');
    }
};
