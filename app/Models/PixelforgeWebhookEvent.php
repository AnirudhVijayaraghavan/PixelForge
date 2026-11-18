<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PixelforgeWebhookEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $provider_event_id
 * @property string|null $generation_id
 * @property string $event_type
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $processed_at
 * @property array<string, string|int>|null $diagnostic_metadata
 * @property-read Generation|null $generation
 */
class PixelforgeWebhookEvent extends Model
{
    /** @use HasFactory<PixelforgeWebhookEventFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'diagnostic_metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
