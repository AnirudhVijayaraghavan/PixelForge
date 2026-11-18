<?php

namespace App\Actions\Generations;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class CreateGeneration
{
    public function __construct(private QuoteGeneration $quoteGeneration) {}

    /** @param list<string> $referenceImages */
    public function handle(User $user, string $prompt, GenerationSize $size, GenerationQuality $quality, array $referenceImages): Generation
    {
        Gate::forUser($user)->authorize('create', Generation::class);
        $quote = $this->quoteGeneration->handle($prompt, $size, $quality, $referenceImages);

        $id = (string) Str::uuid7();
        $idempotencyKey = (string) Str::uuid();
        $payload = [
            'prompt' => $prompt,
            'size' => $size->value,
            'quality' => $quality->value,
            'reference_images' => $referenceImages,
            'metadata' => ['local_generation_id' => $id],
        ];

        return DB::transaction(function () use ($user, $id, $idempotencyKey, $payload, $prompt, $size, $quality, $referenceImages, $quote): Generation {
            $generation = new Generation;
            $generation->id = $id;
            $generation->user_id = $user->id;
            $generation->idempotency_key = $idempotencyKey;
            $generation->request_payload = $payload;
            $generation->prompt = $prompt;
            $generation->size = $size;
            $generation->quality = $quality;
            $generation->reference_images = $referenceImages;
            $generation->submission_status = SubmissionStatus::Pending;
            $generation->recovery_queued_until = now()->addMinutes(5);
            $generation->estimated_provider_cost_nanodollars = (string) $quote->estimate->providerCost->nanodollars();
            $generation->estimated_customer_charge_nanodollars = (string) $quote->estimate->customerCharge->nanodollars();
            $generation->maximum_customer_charge_nanodollars = (string) $quote->maximum->customerCharge->nanodollars();
            $generation->quote_snapshot = $quote->toArray();
            $generation->save();

            SubmitGeneration::dispatch($generation->id)->afterCommit();

            return $generation;
        });
    }
}
