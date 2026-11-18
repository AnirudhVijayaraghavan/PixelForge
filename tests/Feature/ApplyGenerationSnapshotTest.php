<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

test('applies accepted queued and processing snapshots without a settlement', function (GenerationStatus $status) {
    $generation = Generation::factory()->create();

    $applied = app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, $status)));

    expect($applied->submission_status)->toBe(SubmissionStatus::Submitted);
    expect($applied->provider_status)->toBe($status);
    expect($applied->accepted_provider_rates)->toBe(pixelforgePricingFixture()['rates']);
    expect($applied->settlement)->toBeNull();
    Http::assertNothingSent();
})->with([GenerationStatus::Queued, GenerationStatus::Processing]);

test('settles the official successful replay atomically and only once', function () {
    $this->freezeTime();
    $generation = Generation::factory()->create();
    $usage = ['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 16095];
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, ['usage' => $usage]), true);

    $first = app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);
    $second = app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);

    expect($first->provider_status)->toBe(GenerationStatus::Succeeded);
    expect($first->actual_token_usage)->toBe($usage);
    expect($first->settlement->provider_cost_nanodollars)->toBe('373875000');
    expect($first->settlement->customer_charge_nanodollars)->toBe('467343750');
    expect($first->settled_at->equalTo($first->settlement->settled_at))->toBeTrue();
    expect($first->provider_completed_at->toIso8601String())->toBe('2026-11-17T12:00:02+00:00');
    expect($first->image_url)->toBe($snapshot->imageUrl);
    expect($second->settlement->id)->toBe($first->settlement->id);
    $this->assertDatabaseCount('generation_settlements', 1);
    Http::assertNothingSent();
});

test('uses accepted rates rather than the public quote for actual cost', function () {
    $generation = Generation::factory()->create();
    $rates = ['input_text_tokens' => '10.00', 'input_image_tokens' => '20.00', 'output_tokens' => '80.00', 'reasoning_tokens' => '25.00'];
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, [
        'rates' => $rates,
        'usage' => ['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000, 'total_tokens' => 16095],
    ]));

    $applied = app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);

    expect($applied->accepted_provider_rates)->toBe($rates);
    expect($applied->quote_snapshot['rates']['input_text_tokens'])->toBe('5.00');
    expect($applied->settlement->provider_cost_nanodollars)->toBe('747750000');
    expect($applied->settlement->customer_charge_nanodollars)->toBe('934687500');
});

test('records failed provider completion as zero charge without a financial settlement', function () {
    $generation = Generation::factory()->create();
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Failed), true);

    $applied = app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);

    expect($applied->submission_status)->toBe(SubmissionStatus::Submitted);
    expect($applied->provider_status)->toBe(GenerationStatus::Failed);
    expect($applied->provider_error_code)->toBe('generation_failed');
    expect($applied->provider_error_message)->toBe('The provider could not produce an image.');
    expect($applied->actual_token_usage)->toBeNull();
    expect($applied->settled_at)->toBeNull();
    expect($applied->settlement)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 0);
});

test('does not regress established terminal states or processing back to queued', function (GenerationStatus $firstStatus, GenerationStatus $lateStatus) {
    $generation = Generation::factory()->create();
    $first = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, $firstStatus));
    $late = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, $lateStatus));
    app(ApplyGenerationSnapshot::class)->handle($generation->id, $first);

    $applied = app(ApplyGenerationSnapshot::class)->handle($generation->id, $late);

    expect($applied->provider_status)->toBe($firstStatus);
    expect(GenerationSettlement::query()->count())->toBe($firstStatus === GenerationStatus::Succeeded ? 1 : 0);
})->with([
    [GenerationStatus::Succeeded, GenerationStatus::Queued],
    [GenerationStatus::Succeeded, GenerationStatus::Failed],
    [GenerationStatus::Failed, GenerationStatus::Succeeded],
    [GenerationStatus::Processing, GenerationStatus::Queued],
]);

test('rejects mismatched identity metadata inputs and accepted rates without changing the record', function (string $mismatch) {
    $generation = Generation::factory()->accepted()->create();
    $fixture = pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded);
    $fixture = match ($mismatch) {
        'provider_id' => array_replace($fixture, ['id' => 'gen_other']),
        'metadata' => array_replace($fixture, ['metadata' => ['local_generation_id' => 'other-local-id']]),
        'prompt' => array_replace($fixture, ['prompt' => 'A different request']),
        'rates' => array_replace($fixture, ['rates' => ['input_text_tokens' => '999', 'input_image_tokens' => '10', 'output_tokens' => '40', 'reasoning_tokens' => '12.50']]),
    };

    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($generation->id, PixelforgeGenerationData::fromArray($fixture)))
        ->toThrow(UnexpectedValueException::class);

    expect($generation->fresh()->provider_status)->toBe(GenerationStatus::Queued);
    $this->assertDatabaseCount('generation_settlements', 0);
})->with(['provider_id', 'metadata', 'prompt', 'rates']);

test('rolls back successful state when exact settlement pricing is unsupported', function () {
    $generation = Generation::factory()->create();
    $snapshot = PixelforgeGenerationData::fromArray(pixelforgeGenerationFixture($generation, GenerationStatus::Succeeded, [
        'rates' => ['input_text_tokens' => '0.0001', 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'],
        'usage' => ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0, 'total_tokens' => 1],
    ]));

    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot))
        ->toThrow(InvalidArgumentException::class, 'exactly in nanodollars');

    expect($generation->fresh()->provider_generation_id)->toBeNull();
    expect($generation->fresh()->provider_status)->toBeNull();
    expect($generation->fresh()->settled_at)->toBeNull();
    $this->assertDatabaseCount('generation_settlements', 0);
});
