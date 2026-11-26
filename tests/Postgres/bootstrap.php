<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function check(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

$cluster = getenv('PF_RELEASE_CLUSTER');
$scratch = realpath((string) getenv('PF_RELEASE_DIR'));
check(is_string($cluster) && preg_match('/^pf_[a-f0-9]{24}$/D', $cluster) === 1 && $scratch !== false
    && str_starts_with(basename($scratch), 'pixelforge-release-')
    && is_file($scratch.'/cluster/PG_VERSION'), 'Start the disposable cluster using run.py.');

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($scratch);
$app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($cluster): void {
    $config = $app->make('config');
    $db = $config->get('database.connections.pgsql');
    check(! $app->configurationIsCached() && $config->get('app.env') === 'testing'
        && $config->get('database.default') === 'pgsql' && $db['driver'] === 'pgsql'
        && $db['host'] === '127.0.0.1' && $db['database'] === $cluster && $db['username'] === $cluster
        && (int) $db['port'] > 1024 && ! in_array((int) $db['port'], [5432, 5433], true)
        && empty($db['url']) && ! isset($db['read']) && ! isset($db['write']), 'Unsafe database configuration.');
    $config->set('database.connections', ['pgsql' => $db]);
});
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, $error->getMessage().PHP_EOL.$error->getFile().':'.$error->getLine().PHP_EOL);
    exit(1);
});
$target = DB::selectOne("select current_database() as db, current_user as role, inet_server_addr() as host, inet_server_port() as port, current_setting('cluster_name') as cluster");
check($target->db === $cluster && $target->role === $cluster && $target->host === '127.0.0.1'
    && (string) $target->port === getenv('DB_PORT') && $target->cluster === $cluster, 'Wrong live PostgreSQL target.');
DB::statement("SET statement_timeout = '20s'");
DB::statement("SET lock_timeout = '15s'");
Http::preventStrayRequests();
require __DIR__.'/../Support/PixelforgeFixtures.php';
