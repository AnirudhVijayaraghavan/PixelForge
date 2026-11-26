<?php

use App\Domain\Billing\ReportPeriod;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** @return array<string, mixed> */
function documentedUsageFixture(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/Pixelforge/usage.json'), true, flags: JSON_THROW_ON_ERROR);
}

test('fetches exact key-scoped usage with authenticated inclusive UTC query parameters', function () {
    config(['services.pixelforge.api_key' => 'test-production-key']);
    $transactionLevel = DB::transactionLevel();
    Http::fake(function (Request $request) use ($transactionLevel) {
        expect(DB::transactionLevel())->toBe($transactionLevel);
        expect($request->method())->toBe('GET');
        expect($request->hasHeader('Authorization', 'Bearer test-production-key'))->toBeTrue();
        expect($request->url())->toBe('https://pixelforge.promptedit.dev/v1/usage?start_date=2026-11-01&end_date=2026-11-02');

        return Http::response(documentedUsageFixture());
    });

    $data = app(PixelforgeClient::class)->usage(new ReportPeriod('2026-11-01', '2026-11-02'));

    expect($data->days['2026-11-01']['provider_cost_nanodollars'])->toBe('169080000');
    expect($data->total['provider_cost_usd'])->toBe('0.169080000');
    expect($data->days['2026-11-02']['generations'])->toBe('0');
    Http::assertSentCount(1);
});

test('rejects malformed or inconsistent usage instead of fabricating matched totals', function (string $case) {
    config(['services.pixelforge.api_key' => 'test-key']);
    $data = documentedUsageFixture();
    match ($case) {
        'object' => $data['object'] = 'generation',
        'range' => $data['start_date'] = '2026-10-01',
        'missing day' => array_pop($data['data']),
        'duplicate day' => $data['data'][1] = $data['data'][0],
        'unexpected day' => $data['data'][1]['date'] = '2026-11-03',
        'negative tokens' => $data['data'][0]['input_text_tokens'] = -1,
        'float tokens' => $data['data'][0]['input_text_tokens'] = 41.5,
        'string count' => $data['data'][0]['generations'] = '3',
        'negative cost' => $data['data'][0]['cost_usd'] = '-0.169080000',
        'float cost' => $data['data'][0]['cost_usd'] = 0.16908,
        'excess precision' => $data['data'][0]['cost_usd'] = '0.1690800001',
        'short precision' => $data['data'][0]['cost_usd'] = '0.16908',
        'scientific cost' => $data['data'][0]['cost_usd'] = '1e-9',
        'cost total mismatch' => $data['total']['cost_usd'] = '0.169080001',
        'token total mismatch' => $data['total']['reasoning_tokens'] = 2951,
        'count total mismatch' => $data['total']['generations'] = 4,
        'missing category' => $data['data'][0]['output_tokens'] = null,
    };
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response($data)]);

    expect(fn () => app(PixelforgeClient::class)->usage(new ReportPeriod('2026-11-01', '2026-11-02')))
        ->toThrow(PixelforgeException::class, 'invalid or inconsistent');
})->with(['object', 'range', 'missing day', 'duplicate day', 'unexpected day', 'negative tokens', 'float tokens', 'string count', 'negative cost', 'float cost', 'excess precision', 'short precision', 'scientific cost', 'cost total mismatch', 'token total mismatch', 'count total mismatch', 'missing category']);

test('validates UTC calendar ranges including the inclusive 92-day boundary', function () {
    expect((new ReportPeriod('2026-08-01', '2026-10-31'))->dates())->toHaveCount(92);
    expect(fn () => new ReportPeriod('2026-08-01', '2026-11-01'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ReportPeriod('2026-11-02', '2026-11-01'))->toThrow(InvalidArgumentException::class);
    expect(fn () => new ReportPeriod('2026-02-30', '2026-03-01'))->toThrow(InvalidArgumentException::class);
});

test('preserves provider aggregate costs larger than a signed BIGINT nanodollar amount', function () {
    config(['services.pixelforge.api_key' => 'test-key']);
    $data = documentedUsageFixture();
    $data['data'][0]['cost_usd'] = $data['total']['cost_usd'] = '18446744073.709551614';
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response($data)]);

    expect(app(PixelforgeClient::class)->usage(new ReportPeriod('2026-11-01', '2026-11-02'))->total['provider_cost_nanodollars'])
        ->toBe('18446744073709551614');
});

test('usage connection failures remain explicit and make no ambiguous creation claim', function () {
    config(['services.pixelforge.api_key' => 'test-key']);
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => fn () => throw new ConnectionException('Simulated timeout')]);

    try {
        app(PixelforgeClient::class)->usage(new ReportPeriod('2026-11-01', '2026-11-02'));
        $this->fail('Expected a provider exception.');
    } catch (PixelforgeException $exception) {
        expect($exception->providerCode)->toBe('connection_failure');
        expect($exception->uncertain)->toBeFalse();
    }
});

test('usage preserves documented HTTP errors and Retry-After', function (int $status, string $code) {
    config(['services.pixelforge.api_key' => 'test-key']);
    Http::fake(['https://pixelforge.promptedit.dev/v1/usage*' => Http::response([
        'error' => ['type' => 'api_error', 'code' => $code, 'message' => 'Private provider diagnostics'],
    ], $status, ['Retry-After' => '120'])]);

    try {
        app(PixelforgeClient::class)->usage(new ReportPeriod('2026-11-01', '2026-11-02'));
        $this->fail('Expected an error.');
    } catch (PixelforgeException $exception) {
        expect($exception->providerCode)->toBe($code);
        expect($exception->retryAfterSeconds)->toBe(120);
        expect($exception->getMessage())->not->toContain('Private provider diagnostics');
    }
})->with([[401, 'invalid_api_key'], [429, 'rate_limit_exceeded'], [503, 'overloaded']]);
