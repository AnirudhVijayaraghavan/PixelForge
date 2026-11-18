<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('the feature suite resolves an actual in-memory SQLite database', function () {
    $connection = DB::connection();

    expect($connection->getDriverName())->toBe('sqlite');
    expect($connection->getDatabaseName())->toBe(':memory:');
    expect($connection->select('PRAGMA database_list')[0]->file)->toBe('');
    expect(config('inertia.ssr.enabled'))->toBeFalse();
});

test('development database connections are unavailable to tests', function () {
    expect(fn () => DB::connection('pgsql'))
        ->toThrow(InvalidArgumentException::class, 'not configured');
});

test('unfaked HTTP requests are blocked before reaching the network', function () {
    expect(fn () => Http::get('https://provider.invalid/never-send'))
        ->toThrow(StrayRequestException::class);
});
