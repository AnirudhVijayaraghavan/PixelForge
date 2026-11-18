<?php

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Tests\TestEnvironment;

/** @return array<string, mixed> */
function isolatedTestConfiguration(): array
{
    return [
        'app' => ['env' => 'testing', 'timezone' => 'UTC'],
        'database' => [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'url' => null],
                'pgsql' => ['driver' => 'pgsql', 'database' => 'development'],
            ],
        ],
        'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
        'session' => ['driver' => 'array'],
        'queue' => ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']], 'failed' => ['driver' => null]],
        'mail' => ['default' => 'array', 'mailers' => ['array' => ['transport' => 'array']]],
        'broadcasting' => ['default' => null],
        'inertia' => ['ssr' => ['enabled' => false]],
    ];
}

test('allows only isolated SQLite and removes other named database connections', function () {
    $application = new Application(dirname(__DIR__, 2));
    $application->instance('config', new Repository(isolatedTestConfiguration()));

    TestEnvironment::assertSafe($application);

    expect(array_keys($application['config']->get('database.connections')))->toBe(['sqlite']);
});

test('rejects unsafe resolved configuration without opening a database', function (string $key, mixed $value) {
    $application = new Application(dirname(__DIR__, 2));
    $configuration = new Repository(isolatedTestConfiguration());
    $configuration->set($key, $value);
    $application->instance('config', $configuration);

    expect(fn () => TestEnvironment::assertSafe($application))
        ->toThrow(RuntimeException::class, 'Unsafe test');

    expect($application->bound('db'))->toBeFalse();
})->with([
    'testing environment with development PostgreSQL' => ['database.default', 'pgsql'],
    'SQLite file on disk' => ['database.connections.sqlite.database', 'database/development.sqlite'],
    'wrong resolved driver' => ['database.connections.sqlite.driver', 'mysql'],
    'URL overriding an otherwise safe connection' => ['database.connections.sqlite.url', 'postgres://localhost/development'],
    'read connection override' => ['database.connections.sqlite.read', ['database' => 'development']],
    'write connection override' => ['database.connections.sqlite.write', ['database' => 'development']],
    'missing connection' => ['database.connections.sqlite', null],
    'development environment' => ['app.env', 'local'],
    'persistent cache' => ['cache.default', 'database'],
    'misconfigured array cache' => ['cache.stores.array.driver', 'redis'],
    'persistent session' => ['session.driver', 'database'],
    'external queue' => ['queue.default', 'database'],
    'misconfigured sync queue' => ['queue.connections.sync.driver', 'redis'],
    'persistent failed jobs' => ['queue.failed.driver', 'database-uuids'],
    'external mail' => ['mail.default', 'smtp'],
    'misconfigured array mailer' => ['mail.mailers.array.transport', 'smtp'],
    'external broadcasts' => ['broadcasting.default', 'pusher'],
    'SSR rendering service' => ['inertia.ssr.enabled', true],
]);

test('rejects a stale configuration cache before later bootstrappers can run', function () {
    $cachePath = tempnam(sys_get_temp_dir(), 'pixelforge-test-config-');
    $configuration = new Repository(isolatedTestConfiguration());
    $configuration->set('database.default', 'pgsql');
    file_put_contents($cachePath, '<?php return '.var_export($configuration->all(), true).';');

    $application = new class($cachePath) extends Application
    {
        public function __construct(private string $testCachePath)
        {
            parent::__construct(dirname(__DIR__, 2));
        }

        public function getCachedConfigPath(): string
        {
            return $this->testCachePath;
        }
    };

    $laterBootstrapper = new class
    {
        public bool $executed = false;

        public function bootstrap(Application $application): void
        {
            $this->executed = true;
        }
    };

    $application->instance('later-bootstrapper', $laterBootstrapper);
    TestEnvironment::protect($application);

    try {
        expect(fn () => $application->bootstrapWith([LoadConfiguration::class, 'later-bootstrapper']))
            ->toThrow(RuntimeException::class, 'Unsafe test database configuration');

        expect($laterBootstrapper->executed)->toBeFalse();
        expect($application->bound('db'))->toBeFalse();
    } finally {
        unlink($cachePath);
    }
});
