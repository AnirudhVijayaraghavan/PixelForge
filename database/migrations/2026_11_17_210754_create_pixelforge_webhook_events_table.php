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
CREATE TABLE pixelforge_webhook_events (
    id UUID PRIMARY KEY NOT NULL,
    provider_event_id VARCHAR(255) NOT NULL UNIQUE,
    generation_id UUID NULL REFERENCES generations(id) ON DELETE SET NULL,
    event_type VARCHAR(255) NOT NULL,
    received_at TIMESTAMP NOT NULL,
    processed_at TIMESTAMP NULL,
    diagnostic_metadata JSON NULL
)
SQL);

        Schema::table('pixelforge_webhook_events', function (Blueprint $table): void {
            $table->index('generation_id');
            $table->index(['processed_at', 'received_at'], 'pixelforge_events_processing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pixelforge_webhook_events');
    }
};
