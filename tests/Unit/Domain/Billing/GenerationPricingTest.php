<?php

declare(strict_types=1);

use App\Domain\Billing\GenerationPricing;
use App\Domain\Billing\Money;
use App\Domain\Billing\PricingParameters;
use App\Domain\Billing\ProviderRates;
use App\Domain\Billing\TokenUsage;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use Brick\Math\Exception\IntegerOverflowException;

/** @return array<string, mixed> */
function documentedPricing(): array
{
    return json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Pixelforge/pricing.json'), true, flags: JSON_THROW_ON_ERROR);
}

function documentedRates(): ProviderRates
{
    return ProviderRates::fromArray(documentedPricing()['rates']);
}

function pricingEngine(): GenerationPricing
{
    return new GenerationPricing(PricingParameters::fromPricingResponse(documentedPricing()));
}

test('prices one token in every category at the accepted rates', function () {
    $price = pricingEngine()->calculateActual(new TokenUsage(1, 1, 1, 1), documentedRates());

    expect($price->providerCost->toUsd())->toBe('0.000067500');
    expect($price->customerCharge->toUsd())->toBe('0.000084375');
    expect($price->providerBreakdown)->toBe([
        'input_text_tokens' => '0.000005000',
        'input_image_tokens' => '0.000010000',
        'output_tokens' => '0.000040000',
        'reasoning_tokens' => '0.000012500',
    ]);
});

test('matches the official maximum-cost worked example exactly', function () {
    $quote = pricingEngine()->quote(str_repeat('a', 400), GenerationSize::Landscape, GenerationQuality::High, ['https://example.com/1.png', 'https://example.com/2.png'], documentedRates());
    $actual = pricingEngine()->calculateActual(new TokenUsage(115, 1530, 6450, 8000), documentedRates());

    expect($actual->providerCost->toUsd())->toBe('0.373875000');
    expect($actual->customerCharge->toUsd())->toBe('0.467343750');
    expect($quote->maximum->customerCharge->toUsd())->toBe('0.467343750');
    expect($quote->estimatedUsage->inputTextTokens)->toBe(100);
    expect($quote->estimatedUsage->reasoningTokens)->toBe(4500);
    expect($quote->estimate->providerCost->toUsd())->toBe('0.330050000');
    expect($quote->estimate->customerCharge->toUsd())->toBe('0.412562500');
    expect($quote->minimum->customerCharge->toUsd())->toBe('0.357781250');
    expect($actual->customerCharge->compareTo($quote->estimate->customerCharge))->toBe(1);
});

test('estimates every supported quality and size from provider parameters', function (GenerationQuality $quality, GenerationSize $size, int $output, int $reasoningMin, int $reasoningMax, int $midpoint) {
    $quote = pricingEngine()->quote('Paint a lake', $size, $quality, [], documentedRates());

    expect($quote->estimatedUsage->outputTokens)->toBe($output);
    expect($quote->minimumUsage->reasoningTokens)->toBe($reasoningMin);
    expect($quote->maximumUsage->reasoningTokens)->toBe($reasoningMax);
    expect($quote->estimatedUsage->reasoningTokens)->toBe($midpoint);
    expect($quote->estimatedUsage->inputImageTokens)->toBe(0);
    expect($quote->estimate->providerBreakdown['input_image_tokens'])->toBe('0.000000000');
})->with([
    'low square' => [GenerationQuality::Low, GenerationSize::Square, 280, 0, 500, 250],
    'low landscape' => [GenerationQuality::Low, GenerationSize::Landscape, 420, 0, 500, 250],
    'low portrait' => [GenerationQuality::Low, GenerationSize::Portrait, 420, 0, 500, 250],
    'medium square' => [GenerationQuality::Medium, GenerationSize::Square, 1100, 200, 2000, 1100],
    'medium landscape' => [GenerationQuality::Medium, GenerationSize::Landscape, 1650, 200, 2000, 1100],
    'medium portrait' => [GenerationQuality::Medium, GenerationSize::Portrait, 1650, 200, 2000, 1100],
    'high square' => [GenerationQuality::High, GenerationSize::Square, 4300, 1000, 8000, 4500],
    'high landscape' => [GenerationQuality::High, GenerationSize::Landscape, 6450, 1000, 8000, 4500],
    'high portrait' => [GenerationQuality::High, GenerationSize::Portrait, 6450, 1000, 8000, 4500],
]);

test('counts Unicode characters and rounds text uncertainty outward', function (string $prompt, int $estimated, int $minimum, int $maximum) {
    $quote = pricingEngine()->quote($prompt, GenerationSize::Square, GenerationQuality::Low, [], documentedRates());

    expect($quote->estimatedUsage->inputTextTokens)->toBe($estimated);
    expect($quote->minimumUsage->inputTextTokens)->toBe($minimum);
    expect($quote->maximumUsage->inputTextTokens)->toBe($maximum);
})->with([
    'single character' => ['a', 1, 0, 2],
    'ceil character division' => ['abcde', 2, 1, 3],
    'fractional variance bounds' => [str_repeat('a', 20), 5, 4, 6],
    'ASCII boundary' => [str_repeat('a', 4000), 1000, 850, 1150],
    'multibyte characters' => [str_repeat('🎨é汉', 100), 75, 63, 87],
    'Unicode boundary' => [str_repeat('🎨', 4000), 1000, 850, 1150],
]);

test('prices four references and serializes a reproducible quote with string money', function () {
    $references = ['https://example.com/1', 'https://example.com/2', 'https://example.com/3', 'https://example.com/4'];
    $first = pricingEngine()->quote('A lake', GenerationSize::Square, GenerationQuality::Medium, $references, documentedRates());
    $second = pricingEngine()->quote('A lake', GenerationSize::Square, GenerationQuality::Medium, $references, documentedRates());

    expect($first->estimatedUsage->inputImageTokens)->toBe(3060);
    expect($first->toArray())->toBe($second->toArray());
    expect($first->toArray()['estimate']['customer_charge_usd'])->toBeString();
    expect($first->toArray()['rates'])->toBe(documentedPricing()['rates']);
    expect($first->estimatedUsage->totalTokens)->toBe(5262);
});

test('uses supplied token parameters and rounds an odd reasoning midpoint upward', function () {
    $response = documentedPricing();
    $response['tokens']['characters_per_text_token'] = 2;
    $response['tokens']['per_reference_image'] = 99;
    $response['tokens']['text_token_variance'] = '0.20';
    $response['tokens']['output']['low']['1024x1024'] = 9;
    $response['tokens']['reasoning']['low'] = ['min' => 2, 'max' => 5];
    $engine = new GenerationPricing(PricingParameters::fromPricingResponse($response));

    $quote = $engine->quote('abcde', GenerationSize::Square, GenerationQuality::Low, ['https://example.com/a'], documentedRates());

    expect($quote->estimatedUsage->toArray())->toBe([
        'input_text_tokens' => 3, 'input_image_tokens' => 99, 'output_tokens' => 9, 'reasoning_tokens' => 4, 'total_tokens' => 115,
    ]);
    expect($quote->minimumUsage->inputTextTokens)->toBe(2);
    expect($quote->maximumUsage->inputTextTokens)->toBe(4);
});

test('rounds only the final markup HALF_UP at nanodollar precision', function (int $tokens, string $expected) {
    $price = pricingEngine()->calculateActual(new TokenUsage($tokens, 0, 0, 0), new ProviderRates('0.001', '0', '0', '0'));

    expect($price->customerCharge->toUsd())->toBe($expected);
})->with([
    'below half' => [1, '0.000000001'],
    'exactly half' => [2, '0.000000003'],
    'above half' => [3, '0.000000004'],
]);

test('aggregates sub-nanodollar category costs before checking provider precision and applying markup', function () {
    $price = pricingEngine()->calculateActual(new TokenUsage(1, 1, 1, 1), new ProviderRates('0.0005', '0.0005', '0.0005', '0.0005'));

    expect($price->providerCost->toUsd())->toBe('0.000000002');
    expect($price->customerCharge->toUsd())->toBe('0.000000003');
    expect($price->providerBreakdown['input_text_tokens'])->toBe('0.0000000005');
});

test('rejects an unrepresentable provider total instead of rounding it', function () {
    expect(fn () => pricingEngine()->calculateActual(new TokenUsage(1, 0, 0, 0), new ProviderRates('0.0001', '0', '0', '0')))
        ->toThrow(InvalidArgumentException::class, 'exactly in nanodollars');
});

test('uses each accepted rate snapshot rather than current pricing', function () {
    $usage = new TokenUsage(115, 1530, 6450, 8000);
    $accepted = documentedRates();
    $newRates = new ProviderRates('10.00', '20.00', '80.00', '25.00');

    expect(pricingEngine()->calculateActual($usage, $accepted)->customerCharge->toUsd())->toBe('0.467343750');
    expect(pricingEngine()->calculateActual($usage, $newRates)->customerCharge->toUsd())->toBe('0.934687500');
    expect($accepted->toArray())->toBe(documentedPricing()['rates']);
});

test('reads the documented successful usage and verifies its total', function () {
    $generation = json_decode(file_get_contents(dirname(__DIR__, 3).'/Fixtures/Pixelforge/succeeded-generation.json'), true, flags: JSON_THROW_ON_ERROR);

    $price = pricingEngine()->calculateActual(TokenUsage::fromArray($generation['usage']), ProviderRates::fromArray($generation['rates']));

    expect($price->providerCost->toUsd())->toBe('0.305510000');
    expect($price->customerCharge->toUsd())->toBe('0.381887500');
});

test('supports zero usage and aggregates exact charges without cent rounding', function () {
    $zero = pricingEngine()->calculateActual(new TokenUsage(0, 0, 0, 0), documentedRates());
    $small = pricingEngine()->calculateActual(new TokenUsage(1, 1, 1, 1), documentedRates());

    expect($zero->providerCost->toUsd())->toBe('0.000000000');
    expect($zero->customerCharge->toUsd())->toBe('0.000000000');
    expect(Money::zero()->add($small->customerCharge)->add($small->customerCharge)->add($small->customerCharge)->toUsd())->toBe('0.000253125');
});

test('rejects invalid prompt and reference inputs', function (string $prompt, array $references) {
    expect(fn () => pricingEngine()->quote($prompt, GenerationSize::Square, GenerationQuality::Low, $references, documentedRates()))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'empty prompt' => ['', []],
    'over Unicode limit' => [str_repeat('🎨', 4001), []],
    'invalid UTF-8' => ["\xFF", []],
    'five references' => ['a', array_fill(0, 5, 'https://example.com/a')],
    'non-HTTP reference' => ['a', ['file:///etc/passwd']],
    'malformed reference' => ['a', ['not a URL']],
]);

test('rejects invalid usage rather than coercing provider data', function (array $changes) {
    $usage = array_replace(['input_text_tokens' => 0, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0], $changes);

    expect(fn () => TokenUsage::fromArray($usage))->toThrow(InvalidArgumentException::class);
})->with([
    'negative text' => [['input_text_tokens' => -1]],
    'negative image' => [['input_image_tokens' => -1]],
    'negative output' => [['output_tokens' => -1]],
    'negative reasoning' => [['reasoning_tokens' => -1]],
    'numeric string' => [['output_tokens' => '1']],
    'fraction' => [['output_tokens' => 1.5]],
    'missing count' => [['output_tokens' => null]],
    'inconsistent total' => [['total_tokens' => 1]],
]);

test('rejects negative constructor usage and total overflow', function () {
    expect(fn () => new TokenUsage(-1, 0, 0, 0))->toThrow(InvalidArgumentException::class);
    expect(fn () => new TokenUsage(PHP_INT_MAX, 1, 0, 0))->toThrow(IntegerOverflowException::class);
});

test('rejects invalid provider rates', function (mixed $rate) {
    $rates = documentedPricing()['rates'];
    $rates['input_text_tokens'] = $rate;

    expect(fn () => ProviderRates::fromArray($rates))->toThrow(InvalidArgumentException::class);
})->with(['negative' => ['-1'], 'float' => [5.0], 'missing' => [null], 'scientific' => ['1e-3'], 'malformed' => ['NaN'], 'whitespace' => [' 5.00']]);

test('rejects invalid provider pricing parameters', function (string $key, mixed $value) {
    $response = documentedPricing();
    data_set($response, $key, $value);

    expect(fn () => PricingParameters::fromPricingResponse($response))->toThrow(InvalidArgumentException::class);
})->with([
    'wrong currency' => ['currency', 'eur'],
    'wrong unit' => ['unit', 'per_token'],
    'zero divisor' => ['tokens.characters_per_text_token', 0],
    'negative image usage' => ['tokens.per_reference_image', -1],
    'negative variance' => ['tokens.text_token_variance', '-0.15'],
    'variance above one' => ['tokens.text_token_variance', '1.01'],
    'missing size' => ['tokens.output.low.1024x1024', null],
    'negative output' => ['tokens.output.low.1024x1024', -1],
    'inverted reasoning bounds' => ['tokens.reasoning.low', ['min' => 500, 'max' => 0]],
]);
