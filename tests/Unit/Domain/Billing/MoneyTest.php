<?php

declare(strict_types=1);

use App\Domain\Billing\Money;

test('parses and formats exact USD without losing nanodollars', function (string $input, int $nanodollars, string $formatted) {
    $money = Money::fromUsd($input);

    expect($money->nanodollars())->toBe($nanodollars);
    expect($money->toUsd())->toBe($formatted);
    expect(Money::fromNanodollars($nanodollars)->toUsd())->toBe($formatted);
    expect(json_encode($money))->toBe('"'.$formatted.'"');
})->with([
    'zero' => ['0', 0, '0.000000000'],
    'negative zero' => ['-0.000000000', 0, '0.000000000'],
    'one nanodollar' => ['0.000000001', 1, '0.000000001'],
    'one negative nanodollar' => ['-0.000000001', -1, '-0.000000001'],
    'whole dollars' => ['42', 42000000000, '42.000000000'],
    'fractional dollars' => ['1.234567891', 1234567891, '1.234567891'],
    'maximum signed BIGINT' => ['9223372036.854775807', PHP_INT_MAX, '9223372036.854775807'],
    'minimum signed BIGINT' => ['-9223372036.854775808', PHP_INT_MIN, '-9223372036.854775808'],
]);

test('adds subtracts and compares exactly without floating point drift', function () {
    $sum = Money::fromUsd('0.1')->add(Money::fromUsd('0.2'));

    expect($sum->toUsd())->toBe('0.300000000');
    expect($sum->compareTo(Money::fromUsd('0.3')))->toBe(0);
    expect($sum->subtract(Money::fromUsd('0.2'))->toUsd())->toBe('0.100000000');
    expect(Money::zero()->subtract(Money::fromNanodollars(1))->toUsd())->toBe('-0.000000001');
    expect(Money::zero()->compareTo($sum))->toBe(-1);
    expect($sum->compareTo(Money::zero()))->toBe(1);
});

test('rejects malformed USD and unsupported precision', function (string $amount) {
    expect(fn () => Money::fromUsd($amount))->toThrow(InvalidArgumentException::class);
})->with(['', ' 1.00', '1.00 ', '.1', '1.', '+1', '1e-9', 'NaN', 'INF', '1,25', '1.2.3', '0.0000000001', '1.0000000000']);

test('rejects malformed integer nanodollars', function (string $amount) {
    expect(fn () => Money::fromNanodollars($amount))->toThrow(InvalidArgumentException::class);
})->with(['1.0', '1e3', ' 1', 'NaN']);

test('protects signed BIGINT boundaries on parsing and arithmetic', function () {
    expect(fn () => Money::fromNanodollars('9223372036854775808'))->toThrow(OverflowException::class);
    expect(fn () => Money::fromNanodollars('-9223372036854775809'))->toThrow(OverflowException::class);
    expect(fn () => Money::fromUsd('9223372036.854775808'))->toThrow(OverflowException::class);
    expect(fn () => Money::fromNanodollars(PHP_INT_MAX)->add(Money::fromNanodollars(1)))->toThrow(OverflowException::class);
    expect(fn () => Money::fromNanodollars(PHP_INT_MIN)->subtract(Money::fromNanodollars(1)))->toThrow(OverflowException::class);
});
