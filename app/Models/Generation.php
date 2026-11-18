<?php

namespace App\Models;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $provider_generation_id
 * @property string $idempotency_key
 * @property array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array{local_generation_id: string}} $request_payload
 * @property string $prompt
 * @property GenerationSize $size
 * @property GenerationQuality $quality
 * @property list<string> $reference_images
 * @property SubmissionStatus $submission_status
 * @property GenerationStatus|null $provider_status
 * @property string $estimated_provider_cost_nanodollars
 * @property string $estimated_customer_charge_nanodollars
 * @property string $maximum_customer_charge_nanodollars
 * @property array<string, mixed> $quote_snapshot
 * @property array<string, string>|null $accepted_provider_rates
 * @property array<string, int>|null $actual_token_usage
 * @property string|null $image_url
 * @property CarbonImmutable|null $image_url_expires_at
 * @property CarbonImmutable|null $provider_created_at
 * @property CarbonImmutable|null $provider_started_at
 * @property CarbonImmutable|null $provider_completed_at
 * @property string|null $provider_error_code
 * @property string|null $provider_error_message
 * @property CarbonImmutable|null $settled_at
 * @property int $submission_attempts
 * @property CarbonImmutable|null $last_submission_attempt_at
 * @property CarbonImmutable|null $next_submission_attempt_at
 * @property string|null $submission_error_code
 * @property string|null $submission_error_message
 * @property int $reconciliation_attempts
 * @property CarbonImmutable|null $last_reconciliation_attempt_at
 * @property CarbonImmutable|null $next_reconciliation_attempt_at
 * @property string|null $reconciliation_error_code
 * @property string|null $reconciliation_error_message
 * @property CarbonImmutable|null $reconciliation_stopped_at
 * @property CarbonImmutable|null $recovery_queued_until
 * @property int $recovery_dispatches
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read User $user
 * @property-read GenerationSettlement|null $settlement
 */
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (Generation $generation): void {
            foreach (['user_id', 'idempotency_key', 'request_payload', 'prompt', 'size', 'quality', 'reference_images', 'quote_snapshot', 'estimated_provider_cost_nanodollars', 'estimated_customer_charge_nanodollars', 'maximum_customer_charge_nanodollars'] as $attribute) {
                if ($generation->isDirty($attribute)) {
                    throw new LogicException("Generation submission intent [{$attribute}] is immutable.");
                }
            }

            if ($generation->getRawOriginal('accepted_provider_rates') !== null && $generation->isDirty('accepted_provider_rates')) {
                throw new LogicException('Accepted provider rates are immutable.');
            }

            if ($generation->getRawOriginal('provider_generation_id') !== null && $generation->isDirty('provider_generation_id')) {
                throw new LogicException('The accepted provider identity is immutable.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'request_payload' => 'array',
            'size' => GenerationSize::class,
            'quality' => GenerationQuality::class,
            'reference_images' => 'array',
            'submission_status' => SubmissionStatus::class,
            'provider_status' => GenerationStatus::class,
            'estimated_provider_cost_nanodollars' => 'string',
            'estimated_customer_charge_nanodollars' => 'string',
            'maximum_customer_charge_nanodollars' => 'string',
            'quote_snapshot' => 'array',
            'accepted_provider_rates' => 'array',
            'actual_token_usage' => 'array',
            'image_url_expires_at' => 'immutable_datetime',
            'provider_created_at' => 'immutable_datetime',
            'provider_started_at' => 'immutable_datetime',
            'provider_completed_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'submission_attempts' => 'integer',
            'last_submission_attempt_at' => 'immutable_datetime',
            'next_submission_attempt_at' => 'immutable_datetime',
            'reconciliation_attempts' => 'integer',
            'last_reconciliation_attempt_at' => 'immutable_datetime',
            'next_reconciliation_attempt_at' => 'immutable_datetime',
            'reconciliation_stopped_at' => 'immutable_datetime',
            'recovery_queued_until' => 'immutable_datetime',
            'recovery_dispatches' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<PixelforgeWebhookEvent, $this> */
    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PixelforgeWebhookEvent::class);
    }

    /** @return HasOne<GenerationSettlement, $this> */
    public function settlement(): HasOne
    {
        return $this->hasOne(GenerationSettlement::class);
    }
}
