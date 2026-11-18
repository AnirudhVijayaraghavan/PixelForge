<?php

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
        Schema::table('generations', function (Blueprint $table): void {
            $table->unsignedInteger('reconciliation_attempts')->default(0);
            $table->timestamp('last_reconciliation_attempt_at')->nullable();
            $table->timestamp('next_reconciliation_attempt_at')->nullable();
            $table->string('reconciliation_error_code')->nullable();
            $table->string('reconciliation_error_message')->nullable();
            $table->timestamp('reconciliation_stopped_at')->nullable();
            $table->timestamp('recovery_queued_until')->nullable();
            $table->unsignedInteger('recovery_dispatches')->default(0);
            $table->index(['provider_status', 'next_reconciliation_attempt_at'], 'generations_reconciliation_due');
            $table->index(['submission_status', 'next_submission_attempt_at', 'recovery_queued_until'], 'generations_submission_recovery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table): void {
            $table->dropIndex('generations_reconciliation_due');
            $table->dropIndex('generations_submission_recovery');
            $table->dropColumn(['reconciliation_attempts', 'last_reconciliation_attempt_at', 'next_reconciliation_attempt_at', 'reconciliation_error_code', 'reconciliation_error_message', 'reconciliation_stopped_at', 'recovery_queued_until', 'recovery_dispatches']);
        });
    }
};
