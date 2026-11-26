<?php

use App\Domain\Billing\Money;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Domain\Generations\Enums\SubmissionStatus;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\PixelforgeWebhookEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

test('persists local identity and owner relationships before provider submission', function () {
    $owner = User::factory()->create();
    $generation = Generation::factory()->for($owner)->create();
    $event = PixelforgeWebhookEvent::factory()->for($generation)->create();

    expect($generation->id)->toBeUuid();
    expect($generation->submission_status)->toBe(SubmissionStatus::Pending);
    expect($generation->provider_generation_id)->toBeNull();
    expect($generation->provider_status)->toBeNull();
    expect($generation->accepted_provider_rates)->toBeNull();
    expect($generation->actual_token_usage)->toBeNull();
    expect($generation->settled_at)->toBeNull();
    expect($generation->user->is($owner))->toBeTrue();
    expect($owner->generations->sole()->is($generation))->toBeTrue();
    expect($generation->webhookEvents->sole()->is($event))->toBeTrue();
    expect($event->generation->is($generation))->toBeTrue();
    expect($generation->settlement)->toBeNull();
    expect($generation->request_payload['metadata']['local_generation_id'])->toBe($generation->id);
});

test('allows an event to arrive before its local association is known', function () {
    $event = PixelforgeWebhookEvent::factory()->create();

    expect($event->generation_id)->toBeNull();
    expect($event->generation)->toBeNull();
    expect($event->processed_at)->toBeNull();
    expect($event->diagnostic_metadata)->toBe(['delivery_attempt' => 1]);
});

test('casts accepted state snapshots without converting money into numbers', function () {
    $generation = Generation::factory()->accepted()->create();

    expect($generation->size)->toBe(GenerationSize::Square);
    expect($generation->quality)->toBe(GenerationQuality::Medium);
    expect($generation->submission_status)->toBe(SubmissionStatus::Submitted);
    expect($generation->provider_status)->toBe(GenerationStatus::Queued);
    expect($generation->provider_created_at)->toBeInstanceOf(CarbonImmutable::class);
    expect($generation->reference_images)->toBe([]);
    expect($generation->accepted_provider_rates['input_text_tokens'])->toBe('5.00');
    expect($generation->toArray()['estimated_provider_cost_nanodollars'])->toBeString();
    expect($generation->toArray()['estimated_customer_charge_nanodollars'])->toBeString();
    expect($generation->toArray()['maximum_customer_charge_nanodollars'])->toBeString();
    expect($generation->quote_snapshot['estimate']['customer_charge_usd'])->toBeString();
});

test('supports an ambiguous submission and an early provider identity independently', function () {
    $uncertain = Generation::factory()->create(['submission_status' => SubmissionStatus::Uncertain]);
    $early = Generation::factory()->accepted()->create(['submission_status' => SubmissionStatus::Submitting]);

    expect($uncertain->provider_generation_id)->toBeNull();
    expect($early->submission_status)->toBe(SubmissionStatus::Submitting);
    expect($early->provider_status)->toBe(GenerationStatus::Queued);
});

test('rejects duplicate generation identities in SQL', function (string $attribute) {
    $first = Generation::factory()->accepted()->create();

    expect(fn () => Generation::factory()->accepted()->create([$attribute => $first->{$attribute}]))
        ->toThrow(QueryException::class);
})->with(['provider_generation_id', 'idempotency_key']);

test('rejects duplicate webhook events in SQL', function () {
    $event = PixelforgeWebhookEvent::factory()->create();

    expect(fn () => PixelforgeWebhookEvent::factory()->create(['provider_event_id' => $event->provider_event_id]))
        ->toThrow(QueryException::class);
});

test('keeps a single authoritative successful settlement and precise string amounts', function () {
    $generation = Generation::factory()->succeeded()->create();
    $settlement = GenerationSettlement::factory()->for($generation)->create([
        'provider_cost_nanodollars' => '36028797018963972',
        'customer_charge_nanodollars' => '45035996273704965',
    ]);

    expect($generation->settlement->is($settlement))->toBeTrue();
    expect($settlement->generation->is($generation))->toBeTrue();
    expect($settlement->provider_cost_nanodollars)->toBe('36028797018963972');
    expect($settlement->toArray()['customer_charge_nanodollars'])->toBe('45035996273704965');
    expect(Money::fromNanodollars($settlement->customer_charge_nanodollars)->toUsd())->toBe('45035996.273704965');
    expect($settlement->settled_at)->toBeInstanceOf(CarbonImmutable::class);

    expect(fn () => GenerationSettlement::factory()->for($generation)->create())->toThrow(QueryException::class);
});

test('SQL rejects settlement of a generation that has not succeeded', function (string $state) {
    $generation = Generation::factory()->{$state}()->create();

    expect(fn () => GenerationSettlement::factory()->for($generation)->create())->toThrow(QueryException::class);
    $this->assertDatabaseCount('generation_settlements', 0);
})->with(['accepted', 'failed']);

test('SQL rejects settlement of a local-only pending generation', function () {
    $generation = Generation::factory()->create();

    expect(fn () => GenerationSettlement::factory()->for($generation)->create())->toThrow(QueryException::class);
});

test('SQL cannot bypass the success sentinel or move a settled generation back to processing', function () {
    $generation = Generation::factory()->succeeded()->create();
    $settlement = GenerationSettlement::factory()->for($generation)->create();

    expect(fn () => DB::table('generation_settlements')->where('id', $settlement->id)->update(['provider_status' => 'failed']))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('generations')->where('id', $generation->id)->update(['provider_status' => 'processing', 'actual_token_usage' => null]))
        ->toThrow(QueryException::class);
});

test('SQL rejects invalid generation state and amount combinations', function (array $changes) {
    $generation = Generation::factory()->create();

    expect(fn () => DB::table('generations')->where('id', $generation->id)->update($changes))->toThrow(QueryException::class);
})->with([
    'provider ID without state and rates' => [['provider_generation_id' => 'gen_unknown']],
    'provider state without ID' => [['provider_status' => 'queued']],
    'accepted rates without ID' => [['accepted_provider_rates' => '{}']],
    'submitted without provider identity' => [['submission_status' => 'submitted']],
    'usage before success' => [['actual_token_usage' => '{}']],
    'settled before success' => [['settled_at' => '2026-11-17 00:00:00']],
    'invalid local state' => [['submission_status' => 'queued']],
    'invalid provider state' => [['provider_status' => 'pending']],
    'invalid quality' => [['quality' => 'ultra']],
    'invalid size' => [['size' => '1x1']],
    'negative provider estimate' => [['estimated_provider_cost_nanodollars' => -1]],
    'fractional provider estimate' => [['estimated_provider_cost_nanodollars' => '1.5']],
    'negative customer estimate' => [['estimated_customer_charge_nanodollars' => -1]],
    'maximum below estimate' => [['maximum_customer_charge_nanodollars' => 0]],
    'overflowed maximum' => [['maximum_customer_charge_nanodollars' => '9223372036854775808']],
]);

test('SQL requires usage and a completion time for successful provider state', function (string $attribute) {
    $generation = Generation::factory()->succeeded()->create();

    expect(fn () => DB::table('generations')->where('id', $generation->id)->update([$attribute => null]))
        ->toThrow(QueryException::class);
})->with(['actual_token_usage', 'provider_completed_at']);

test('SQL rejects invalid settlement amounts', function (array $changes) {
    expect(fn () => GenerationSettlement::factory()->create($changes))->toThrow(QueryException::class);
})->with([
    'negative provider cost' => [['provider_cost_nanodollars' => '-1']],
    'negative customer charge' => [['customer_charge_nanodollars' => '-1']],
    'customer charge below provider cost' => [['customer_charge_nanodollars' => '1']],
    'fractional nanodollars' => [['provider_cost_nanodollars' => '1.5']],
    'overflowed customer charge' => [['customer_charge_nanodollars' => '9223372036854775808']],
]);

test('does not accept mass-assigned provider or financial data', function (string $model, array $input) {
    expect(fn () => (new $model)->fill($input))->toThrow(MassAssignmentException::class);
})->with([
    'generation provider fields' => [Generation::class, ['provider_status' => 'succeeded', 'accepted_provider_rates' => [], 'actual_token_usage' => [], 'settled_at' => '2026-11-17']],
    'settlement amounts' => [GenerationSettlement::class, ['customer_charge_nanodollars' => '0']],
    'webhook processing state' => [PixelforgeWebhookEvent::class, ['processed_at' => '2026-11-17']],
]);

test('preserves immutable submission intent and accepted rate snapshots', function () {
    $generation = Generation::factory()->accepted()->create();
    $originalPrompt = $generation->prompt;
    $generation->prompt = 'Replace the historical prompt';

    expect(fn () => $generation->save())->toThrow(LogicException::class, 'immutable');

    $generation->refresh();
    $generation->accepted_provider_rates = ['input_text_tokens' => '999.00'];

    expect(fn () => $generation->save())->toThrow(LogicException::class, 'immutable');
    expect($generation->fresh()->prompt)->toBe($originalPrompt);
    expect($generation->fresh()->accepted_provider_rates['input_text_tokens'])->toBe('5.00');

    $generation->refresh();
    $generation->provider_generation_id = 'gen_replacement';

    expect(fn () => $generation->save())->toThrow(LogicException::class, 'immutable');
});

test('preserves a settlement as an immutable financial fact', function () {
    $settlement = GenerationSettlement::factory()->create();
    $settlement->customer_charge_nanodollars = '0';

    expect(fn () => $settlement->save())->toThrow(LogicException::class, 'immutable');
    expect(fn () => $settlement->delete())->toThrow(LogicException::class, 'cannot be deleted');
    expect($settlement->fresh()->customer_charge_nanodollars)->toBe('72225000');
});

test('authorizes only the owner and conceals cross-user generation existence', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $generation = Generation::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows('view', $generation))->toBeTrue();
    expect(Gate::forUser($other)->inspect('view', $generation)->status())->toBe(404);
    expect(Gate::forUser($owner)->allows('update', $generation))->toBeFalse();
    expect(Gate::forUser($other)->allows('update', $generation))->toBeFalse();
});

test('settlement facts also reject bulk SQL mutations', function (string $operation) {
    $settlement = GenerationSettlement::factory()->create();
    $query = DB::table('generation_settlements')->where('id', $settlement->id);
    expect(fn () => $operation === 'delete' ? $query->delete() : $query->increment('provider_cost_nanodollars'))
        ->toThrow(QueryException::class);
    expect($settlement->fresh()->provider_cost_nanodollars)->toBe('57780000');
})->with(['update', 'delete']);
