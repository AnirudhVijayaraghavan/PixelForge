<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Actions\Generations\CreateGeneration;
use App\Actions\Generations\ProcessPixelforgeWebhook;
use App\Domain\Billing\WalletAccounting;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeGenerationData;
use App\Integrations\Pixelforge\PixelforgeWebhookData;
use App\Jobs\ReconcileGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function rates(string $text = '2000000'): array
{
    return ['input_text_tokens' => $text, 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'];
}

function admit(User $user): Generation
{
    Http::fake(['*/pricing' => Http::response(array_replace(pixelforgePricingFixture(), ['rates' => rates()]))]);

    return app(CreateGeneration::class)->handle($user, '🎨', GenerationSize::Square, GenerationQuality::Low, [], (string) Str::uuid());
}

function apply(Generation $g, GenerationStatus $status, array $changes = []): void
{
    $data = pixelforgeGenerationFixture($g, $status, $changes + ['rates' => rates()]);
    app(ApplyGenerationSnapshot::class)->handle($g->id, PixelforgeGenerationData::fromArray($data));
}

function deliver(Generation $g, string $status, string $event): bool
{
    $body = pixelforgeWebhookFixture($g, GenerationStatus::from($status), ['id' => $event]);
    $body['data']['object']['rates'] = rates();

    return app(ProcessPixelforgeWebhook::class)->handle(PixelforgeWebhookData::fromArray($body));
}

function executeWorker(array $args): string
{
    [$operation, $id, $intent] = $args;
    if ($operation === 'purchase') {
        app(WalletAccounting::class)->purchase(User::findOrFail($id), '5', $intent);
    } elseif ($operation === 'admit') {
        try {
            admit(User::findOrFail($id));
        } catch (ValidationException $e) {
            check(isset($e->errors()['wallet']), 'Unexpected admission error.');

            return 'insufficient';
        }
    } elseif ($operation === 'poll') {
        $g = Generation::findOrFail($id);
        Http::fake(['*/generations/*' => function () use ($g) {
            check(DB::transactionLevel() === 0, 'Network call inside transaction.');
            // Hold the simulated GET response until the coordinator also has a webhook in flight.
            DB::select('select pg_advisory_lock(91)');
            DB::select('select pg_advisory_unlock(91)');

            return Http::response(pixelforgeGenerationFixture($g, GenerationStatus::Succeeded, ['rates' => rates()]));
        }]);
        (new ReconcileGeneration($id))->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
        Http::assertSentCount(1);
    } else {
        return deliver(Generation::findOrFail($id), $operation, $intent) ? 'processed' : 'duplicate';
    }

    return 'ok';
}
