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
CREATE TABLE generation_settlements (
    id UUID PRIMARY KEY NOT NULL,
    generation_id UUID NOT NULL UNIQUE,
    provider_status VARCHAR(16) NOT NULL DEFAULT 'succeeded' CHECK (provider_status = 'succeeded'),
    provider_cost_nanodollars BIGINT NOT NULL,
    customer_charge_nanodollars BIGINT NOT NULL,
    settled_at TIMESTAMP NOT NULL,
    CONSTRAINT generation_settlements_success_foreign FOREIGN KEY (generation_id, provider_status)
        REFERENCES generations(id, provider_status) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT generation_settlements_amounts CHECK (
        provider_cost_nanodollars BETWEEN 0 AND 9223372036854775807
        AND provider_cost_nanodollars = CAST(provider_cost_nanodollars AS BIGINT)
        AND customer_charge_nanodollars BETWEEN provider_cost_nanodollars AND 9223372036854775807
        AND customer_charge_nanodollars = CAST(customer_charge_nanodollars AS BIGINT)
    )
)
SQL);

        Schema::table('generation_settlements', function (Blueprint $table): void {
            $table->index('settled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_settlements');
    }
};
