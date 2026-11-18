<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Http\Client\Factory;
use RuntimeException;

final class TestEnvironment
{
    public static function protect(Application $application): void
    {
        $application->afterBootstrapping(LoadConfiguration::class, self::assertSafe(...));

        $application->afterBootstrapping(RegisterProviders::class, function (Application $application): void {
            $application->make(Factory::class)->preventStrayRequests();
        });
    }

    /**
     * Validate loaded configuration before providers, PDO, or database test traits run.
     */
    public static function assertSafe(Application $application): void
    {
        $configuration = $application->make('config');
        $connectionName = $configuration->get('database.default');
        $connection = $configuration->get('database.connections.'.$connectionName);

        if ($configuration->get('app.env') !== 'testing'
            || $connectionName !== 'sqlite'
            || ! is_array($connection)
            || ($connection['driver'] ?? null) !== 'sqlite'
            || ($connection['database'] ?? null) !== ':memory:'
            || ! empty($connection['url'])
            || array_key_exists('read', $connection)
            || array_key_exists('write', $connection)) {
            throw new RuntimeException(
                'Unsafe test database configuration: only the sqlite connection with driver sqlite, database :memory:, '
                .'APP_ENV=testing, no DB_URL, and no read/write overrides is allowed. '
                .'No database connection was opened. Check phpunit.xml and the resolved configuration cache at '
                .$application->getCachedConfigPath().'. Do not run migrations or clear development caches to bypass this guard.'
            );
        }

        foreach ([
            'cache.default' => 'array',
            'cache.stores.array.driver' => 'array',
            'session.driver' => 'array',
            'queue.default' => 'sync',
            'queue.connections.sync.driver' => 'sync',
            'queue.failed.driver' => null,
            'mail.default' => 'array',
            'mail.mailers.array.transport' => 'array',
            'broadcasting.default' => null,
            'inertia.ssr.enabled' => false,
        ] as $key => $expected) {
            if ($configuration->get($key) !== $expected) {
                throw new RuntimeException("Unsafe test service configuration: [{$key}] must use its isolated test driver. Check phpunit.xml and the test configuration cache.");
            }
        }

        /** Other named connections must not retain access to development databases. */
        $configuration->set('database.connections', ['sqlite' => $connection]);
    }
}
