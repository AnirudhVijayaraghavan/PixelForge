<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** Native CHECK constraints keep the same invariants in PostgreSQL and SQLite. */
        DB::statement(<<<'SQL'
CREATE TABLE generations (
    id UUID PRIMARY KEY NOT NULL,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    provider_generation_id VARCHAR(255) NULL UNIQUE,
    idempotency_key VARCHAR(255) NOT NULL UNIQUE,
    request_payload JSON NOT NULL,
    prompt TEXT NOT NULL CHECK (length(prompt) BETWEEN 1 AND 4000),
    size VARCHAR(16) NOT NULL CHECK (size IN ('1024x1024', '1536x1024', '1024x1536')),
    quality VARCHAR(16) NOT NULL CHECK (quality IN ('low', 'medium', 'high')),
    reference_images JSON NOT NULL,
    submission_status VARCHAR(16) NOT NULL DEFAULT 'pending'
        CHECK (submission_status IN ('pending', 'submitting', 'submitted', 'uncertain', 'rejected')),
    provider_status VARCHAR(16) NULL CHECK (provider_status IN ('queued', 'processing', 'succeeded', 'failed')),
    estimated_provider_cost_nanodollars BIGINT NOT NULL,
    estimated_customer_charge_nanodollars BIGINT NOT NULL,
    maximum_customer_charge_nanodollars BIGINT NOT NULL,
    quote_snapshot JSON NOT NULL,
    accepted_provider_rates JSON NULL,
    actual_token_usage JSON NULL,
    image_url TEXT NULL,
    image_url_expires_at TIMESTAMP NULL,
    provider_created_at TIMESTAMP NULL,
    provider_started_at TIMESTAMP NULL,
    provider_completed_at TIMESTAMP NULL,
    provider_error_code VARCHAR(255) NULL,
    provider_error_message TEXT NULL,
    settled_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    CONSTRAINT generations_provider_identity CHECK (
        (provider_generation_id IS NULL AND provider_status IS NULL AND accepted_provider_rates IS NULL)
        OR (provider_generation_id IS NOT NULL AND provider_status IS NOT NULL AND accepted_provider_rates IS NOT NULL)
    ),
    CONSTRAINT generations_submission_identity CHECK (
        (submission_status <> 'submitted' OR provider_generation_id IS NOT NULL)
        AND (submission_status <> 'rejected' OR provider_generation_id IS NULL)
    ),
    CONSTRAINT generations_terminal_completion CHECK (
        provider_status IS NULL OR provider_status NOT IN ('succeeded', 'failed') OR provider_completed_at IS NOT NULL
    ),
    CONSTRAINT generations_success_usage CHECK (
        (actual_token_usage IS NULL OR (provider_status IS NOT NULL AND provider_status = 'succeeded'))
        AND (provider_status IS NULL OR provider_status <> 'succeeded' OR actual_token_usage IS NOT NULL)
    ),
    CONSTRAINT generations_settlement_status CHECK (
        settled_at IS NULL OR (provider_status IS NOT NULL AND provider_status = 'succeeded')
    ),
    CONSTRAINT generations_quote_amounts CHECK (
        estimated_provider_cost_nanodollars BETWEEN 0 AND 9223372036854775807
        AND estimated_provider_cost_nanodollars = CAST(estimated_provider_cost_nanodollars AS BIGINT)
        AND estimated_customer_charge_nanodollars BETWEEN estimated_provider_cost_nanodollars AND 9223372036854775807
        AND estimated_customer_charge_nanodollars = CAST(estimated_customer_charge_nanodollars AS BIGINT)
        AND maximum_customer_charge_nanodollars BETWEEN estimated_customer_charge_nanodollars AND 9223372036854775807
        AND maximum_customer_charge_nanodollars = CAST(maximum_customer_charge_nanodollars AS BIGINT)
    ),
    CONSTRAINT generations_id_provider_status_unique UNIQUE (id, provider_status)
)
SQL);

        Schema::table('generations', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at', 'id'], 'generations_user_history_index');
            $table->index(['submission_status', 'updated_at'], 'generations_submission_recovery_index');
            $table->index(['provider_status', 'updated_at'], 'generations_provider_recovery_index');
            $table->index(['provider_completed_at', 'provider_status'], 'generations_completion_reporting_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
