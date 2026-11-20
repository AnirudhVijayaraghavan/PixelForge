<?php

use App\Integrations\Pixelforge\PixelforgeClient;
use App\Models\User;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Support/PixelforgeFixtures.php';

/** @return array<string, mixed> */
function studioQuoteInput(): array
{
    return ['prompt' => str_repeat('A', 400), 'size' => '1536x1024', 'quality' => 'high', 'reference_images' => ['https://example.com/a.jpg', 'https://example.com/b.jpg']];
}

test('repeated quotes stay available with serialized database caching', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    app()->instance(PixelforgeClient::class, new PixelforgeClient(
        app(Factory::class),
        Cache::store('database'),
        app(ConfigRepository::class),
    ));
    $this->actingAs(User::factory()->create());

    $initial = $this->postJson(route('generations.quote'), array_replace(studioQuoteInput(), [
        'size' => '1024x1024', 'quality' => 'low',
    ]))->assertOk();
    $updated = $this->postJson(route('generations.quote'), studioQuoteInput())->assertOk();

    expect($updated->json('estimate.customer_charge_usd'))->not->toBe($initial->json('estimate.customer_charge_usd'));
    $this->postJson(route('generations.quote'), studioQuoteInput())
        ->assertOk()
        ->assertJsonPath('estimate.customer_charge_usd', $updated->json('estimate.customer_charge_usd'));
    Http::assertSentCount(1);
});

test('quotes exact server prices and bounds without submitting or writing domain records', function () {
    Queue::fake();
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), studioQuoteInput());
    $response->assertOk()->assertJsonPath('estimate.provider_cost_usd', '0.330050000')->assertJsonPath('estimate.customer_charge_usd', '0.412562500')
        ->assertJsonPath('minimum.customer_charge_usd', '0.357781250')->assertJsonPath('maximum.customer_charge_usd', '0.467343750')
        ->assertJsonPath('estimated_usage.input_text_tokens', 100)->assertJsonPath('currency', 'usd')->assertJsonStructure(['provenance', 'quoted_at']);
    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('generation_settlements', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => $request->method() === 'GET' && ! $request->hasHeader('Authorization'));
});

test('quotes quality size and references independently while reusing the pricing cache', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $this->actingAs(User::factory()->create());
    $base = ['prompt' => 'test', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => []];
    $this->postJson(route('generations.quote'), $base)->assertOk()->assertJsonPath('estimate.customer_charge_usd', '0.017912500');
    $this->postJson(route('generations.quote'), array_replace($base, ['quality' => 'high']))->assertOk()->assertJsonPath('estimate.customer_charge_usd', '0.285318750');
    $this->postJson(route('generations.quote'), array_replace($base, ['size' => '1536x1024']))->assertOk()->assertJsonPath('estimate.customer_charge_usd', '0.024912500');
    $this->postJson(route('generations.quote'), array_replace($base, ['reference_images' => ['https://example.com/image']]))->assertOk()->assertJsonPath('estimate.customer_charge_usd', '0.027475000');
    Http::assertSentCount(1);
});

test('rejects invalid quote inputs and client financial fields before provider access', function (array $changes, string $field) {
    Queue::fake();
    $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), array_replace(studioQuoteInput(), $changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with([
    [['prompt' => '   '], 'prompt'], [['prompt' => str_repeat('🎨', 4001)], 'prompt'],
    [['size' => '512x512'], 'size'], [['quality' => 'ultra'], 'quality'],
    [['reference_images' => ['javascript:alert(1)']], 'reference_images.0'],
    [['reference_images' => array_fill(0, 5, 'https://example.com/image')], 'reference_images'],
    [['customer_charge_usd' => '0.000000001'], 'customer_charge_usd'], [['provider_status' => 'succeeded'], 'provider_status'],
]);

test('normalizes Unicode quote input using the same request as creation', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), array_replace(studioQuoteInput(), ['prompt' => "\u{200B} ".str_repeat('🎨', 4000)."\n\u{200B}"]))
        ->assertOk()->assertJsonPath('estimated_usage.input_text_tokens', 1000);
    Http::assertSentCount(1);
});

test('reports unavailable pricing without inventing an estimate or leaking provider diagnostics', function () {
    Queue::fake();
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgeErrorFixture('overloaded'), 503)]);
    $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), studioQuoteInput())
        ->assertServiceUnavailable()->assertJsonPath('code', 'pricing_unavailable')->assertJsonMissingPath('estimate')->assertDontSee('Sensitive');
    $this->assertDatabaseCount('generations', 0);
    Queue::assertNothingPushed();
});

test('retains normal CSRF protection on quotes before pricing access', function () {
    $middleware = new class(app(), app(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };
    app()->instance(PreventRequestForgery::class, $middleware);
    $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), studioQuoteInput())->assertStatus(419);
    $this->assertDatabaseCount('generations', 0);
    Http::assertNothingSent();
});

test('normalizes case-insensitive HTTP reference schemes at the shared request boundary', function () {
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricingFixture())]);
    $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), array_replace(studioQuoteInput(), ['reference_images' => [' HTTPS://example.com/CaseSensitivePath.jpg ']]))
        ->assertOk()->assertJsonPath('estimated_usage.input_image_tokens', 765);
    Http::assertSentCount(1);
});
