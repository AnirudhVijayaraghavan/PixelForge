<?php

namespace App\Models;

use App\Domain\Generations\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationSettlementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $generation_id
 * @property GenerationStatus $provider_status
 * @property string $provider_cost_nanodollars
 * @property string $customer_charge_nanodollars
 * @property CarbonImmutable $settled_at
 * @property-read Generation $generation
 */
class GenerationSettlement extends Model
{
    /** @use HasFactory<GenerationSettlementFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A settlement is an immutable financial fact.');
        });

        static::deleting(function (): never {
            throw new LogicException('A settlement cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider_status' => GenerationStatus::class,
            'provider_cost_nanodollars' => 'string',
            'customer_charge_nanodollars' => 'string',
            'settled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
