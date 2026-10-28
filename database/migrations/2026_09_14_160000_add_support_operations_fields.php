<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('customer_id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
            $table->timestamp('sla_due_at')->nullable()->after('priority');
            $table->timestamp('first_response_at')->nullable()->after('sla_due_at');
            $table->timestamp('last_customer_reply_at')->nullable()->after('first_response_at');
            $table->timestamp('resolved_at')->nullable()->after('last_customer_reply_at');
            $table->index(['organization_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('is_admin_reply');
            $table->index(['ticket_id', 'is_internal']);
        });

        DB::table('support_tickets')
            ->whereNull('organization_id')
            ->orderBy('id')
            ->chunkById(250, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $organizationId = DB::table('users')->where('id', $ticket->customer_id)->value('current_organization_id');
                    if ($organizationId) {
                        DB::table('support_tickets')->where('id', $ticket->id)->update(['organization_id' => $organizationId]);
                    }
                }
            });

        $slaExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "datetime(created_at, '+4 hours')"
            : 'DATE_ADD(created_at, INTERVAL 4 HOUR)';
        DB::table('support_tickets')->whereNull('sla_due_at')->update([
            'sla_due_at' => DB::raw($slaExpression),
        ]);
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['ticket_id', 'is_internal']);
            $table->dropColumn('is_internal');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status']);
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['sla_due_at', 'first_response_at', 'last_customer_reply_at', 'resolved_at']);
        });
    }
};
