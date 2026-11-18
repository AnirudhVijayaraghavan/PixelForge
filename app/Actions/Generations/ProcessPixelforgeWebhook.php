<?php

namespace App\Actions\Generations;

use App\Integrations\Pixelforge\PixelforgeWebhookData;
use App\Models\Generation;
use App\Models\PixelforgeWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

final readonly class ProcessPixelforgeWebhook
{
    public function __construct(private ApplyGenerationSnapshot $applySnapshot) {}

    /** Returns false for an already processed delivery. No HTTP occurs in this transaction. */
    public function handle(PixelforgeWebhookData $event): bool
    {
        return DB::transaction(function () use ($event): bool {
            /** Insert without a generation FK first, avoiding shared-FK-lock upgrades between distinct events. */
            DB::table('pixelforge_webhook_events')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'provider_event_id' => $event->id,
                'event_type' => $event->type,
                'received_at' => now(),
                'diagnostic_metadata' => json_encode(['event_created_at' => $event->createdAt->toIso8601String()], JSON_THROW_ON_ERROR),
            ]);

            $record = PixelforgeWebhookEvent::query()->where('provider_event_id', $event->id)->lockForUpdate()->firstOrFail();

            if ($record->event_type !== $event->type) {
                throw new UnexpectedValueException('A webhook event identity cannot be reused.');
            }

            if ($record->processed_at !== null) {
                if ($event->generation !== null) {
                    if ($record->generation_id !== ($event->generation->metadata['local_generation_id'] ?? null)) {
                        throw new UnexpectedValueException('A webhook event association cannot be replaced.');
                    }

                    $this->applySnapshot->assertIdentity(Generation::query()->findOrFail($record->generation_id), $event->generation);
                }

                return false;
            }

            if ($event->generation !== null) {
                $generation = $this->applySnapshot->handle($event->generation->metadata['local_generation_id'], $event->generation);
                $record->generation_id = $generation->id;
            }

            $record->processed_at = now();
            $record->save();

            return true;
        }, 3);
    }
}
