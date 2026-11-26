<?php

use App\Actions\Generations\ApplyGenerationSnapshot;
use App\Domain\Billing\FinancialReport;
use App\Domain\Billing\ReportPeriod;
use App\Domain\Billing\TokenUsage;
use App\Domain\Billing\WalletAccounting;
use App\Domain\Generations\Enums\GenerationStatus;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Jobs\SubmitGeneration;
use App\Models\CreditWallet;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require __DIR__.'/bootstrap.php';
require __DIR__.'/operations.php';

if (($argv[1] ?? null) === 'worker') {
    DB::select("select set_config('application_name', ?, false)", [$argv[2]]);
    echo executeWorker(array_slice($argv, 3));
    exit;
}

function migration(array $options = []): void
{
    $code = Artisan::call('migrate', $options + ['--force' => true]);
    check($code === 0, Artisan::output());
}

function userWithCredits(?string $amount = '5'): User
{
    $user = User::factory()->create();
    $wallets = app(WalletAccounting::class);
    $wallets->wallet($user);
    if ($amount !== null) {
        $wallets->purchase($user, $amount, (string) Str::uuid());
    }

    return $user;
}

function balances(User $user, int $settled, int $reserved): void
{
    $wallet = CreditWallet::findOrFail($user->id);
    $ledger = DB::table('credit_ledger_entries')->where('user_id', $user->id)
        ->selectRaw('COALESCE(SUM(CASE WHEN generation_id IS NULL THEN amount ELSE -amount END),0) AS balance')->first();
    $holds = DB::table('credit_reservations')->where('user_id', $user->id)->where('state', 'active')->sum('held');
    check((string) $ledger->balance === (string) $settled && (int) $holds === $reserved
        && $wallet->reserved === $reserved && $wallet->settled === $settled
        && $wallet->available()->nanodollars() === $settled - $reserved, 'Wallet, ledger or hold totals disagree.');
}

function worker(string $name, string $action, string|int $id, ?string $intent = null): Process
{
    $process = new Process([PHP_BINARY, __FILE__, 'worker', $name, $action, (string) $id, $intent ?? (string) Str::uuid()]);
    $process->setTimeout(25);
    $process->start();

    return $process;
}

function result(Process $process): string
{
    check($process->wait() === 0, 'Child failed: '.$process->getErrorOutput().$process->getOutput());

    return trim($process->getOutput());
}

function waitForDatabaseLock(string $name, Process $process): void
{
    $until = microtime(true) + 8;
    do {
        DB::select('select pg_stat_clear_snapshot()');
        $row = DB::selectOne("select pid from pg_stat_activity where application_name = ? and wait_event_type = 'Lock'", [$name]);
        if ($row !== null) {
            return;
        }
        check($process->isRunning(), 'Child exited before contention: '.$process->getErrorOutput().$process->getOutput());
        usleep(15000);
    } while (microtime(true) < $until);
    throw new RuntimeException('Did not observe PostgreSQL lock wait for '.$name);
}

/** Queue both independent writers behind a real row lock before allowing either to commit. */
function race(string $table, string|int $id, array $first, array $second): array
{
    [$a, $b] = DB::transaction(function () use ($table, $id, $first, $second) {
        DB::table($table)->where($table === 'credit_wallets' ? 'user_id' : 'id', $id)->lockForUpdate()->firstOrFail();
        $a = worker('writer1', ...$first);
        waitForDatabaseLock('writer1', $a);
        $b = worker('writer2', ...$second);
        waitForDatabaseLock('writer2', $b);

        return [$a, $b];
    });

    return [result($a), result($b)];
}

function captured(Generation $g, int $charge = 2500000000): void
{
    $settlements = $g->settlement();
    $debits = DB::table('credit_ledger_entries')->where('generation_id', $g->id);
    check($g->fresh()->provider_status === GenerationStatus::Succeeded && $settlements->count() === 1
        && (int) $settlements->firstOrFail()->customer_charge_nanodollars === $charge
        && $debits->count() === 1 && (int) $debits->value('amount') === $charge
        && $g->reservation()->value('state') === 'captured', 'Successful capture mismatch.');
}

function blockedSql(callable $operation, string $state): void
{
    try {
        DB::transaction($operation);
    } catch (QueryException $e) {
        check((string) $e->getCode() === $state, 'Unexpected SQLSTATE '.$e->getCode());

        return;
    }
    throw new RuntimeException('Expected PostgreSQL to reject the write.');
}

check(DB::selectOne("select count(*) as n from pg_tables where schemaname='public'")->n == 0, 'Database must be empty.');
if (($argv[1] ?? null) === 'empty') {
    migration();
    check(DB::table('migrations')->count() === count(glob(database_path('migrations/*.php'))), 'Incomplete migration set.');
    $indexes = DB::table('pg_indexes')->where('schemaname', 'public')->pluck('indexname')->all();
    foreach (['generations_wallet_owner', 'settlements_capture_amount', 'wallet_activity', 'generations_user_history_index'] as $required) {
        check(in_array($required, $indexes, true), 'Missing index '.$required);
    }
    echo "PASS all migrations on empty PostgreSQL database\nCOMPLETE empty\n";
    exit;
}
$original = array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) < '2026_11_25');
migration(['--path' => array_values($original), '--realpath' => true]);
$legacy = User::findOrFail(DB::table('users')->insertGetId(['name' => 'Legacy', 'email' => 'legacy@example.test', 'password' => 'disabled']));
$pending = Generation::factory()->for($legacy)->create();
$failed = Generation::factory()->for($legacy)->failed()->create();
$past = Generation::factory()->for($legacy)->succeeded()->create();
$fact = GenerationSettlement::factory()->create(['generation_id' => $past->id]);
$before = $fact->fresh()->getRawOriginal();
// Probe legacy SQL bypass inside a transaction which is always rolled back.
DB::beginTransaction();
try {
    $edited = DB::table('generation_settlements')->where('id', $fact->id)->increment('provider_cost_nanodollars');
    check($edited === 1, 'Legacy immutability probe was inconclusive.');
} finally {
    DB::rollBack();
}
migration();
check($fact->fresh()->getRawOriginal() === $before && ! $legacy->fresh()->is_admin, 'Historical record changed.');
apply($pending, GenerationStatus::Succeeded);
check($pending->settlement()->count() === 1 && $failed->settlement()->count() === 0 && DB::table('credit_reservations')->count() === 0, 'Cutover mismatch.');
balances($legacy, 0, 0);
echo "PASS historical upgrade and pre-wallet completion\n";

$user = userWithCredits();
$outcomes = race('credit_wallets', $user->id, ['admit', $user->id], ['admit', $user->id]);
sort($outcomes);
check($outcomes === ['insufficient', 'ok'] && $user->generations()->count() === 1, 'Concurrent admission overspent.');
balances($user, 5000000000, 5000000000);
echo "PASS A competing admissions\n";

$user = userWithCredits(null);
$buy = ['purchase', $user->id, (string) Str::uuid()];
race('credit_wallets', $user->id, $buy, $buy);
balances($user, 5000000000, 0);
echo "PASS B duplicate purchase\n";
race('credit_wallets', $user->id, ['admit', $user->id], ['purchase', $user->id]);
balances($user, 10000000000, 5000000000);
echo "PASS C purchase and admission\n";

$g = $user->generations()->firstOrFail();
apply($g, GenerationStatus::Processing);
$g->refresh()->forceFill(['next_reconciliation_attempt_at' => now()->subMinute(), 'provider_created_at' => now()->subMinutes(5)])->save();
DB::select('select pg_advisory_lock(91)');
$poll = worker('poll', 'poll', $g->id);
waitForDatabaseLock('poll', $poll);
$webhook = DB::transaction(function () use ($g, $poll) {
    Generation::lockForUpdate()->findOrFail($g->id);
    $hook = worker('hook', 'succeeded', $g->id, 'evt_'.Str::ulid());
    waitForDatabaseLock('hook', $hook);
    DB::select('select pg_advisory_unlock(91)');
    waitForDatabaseLock('poll', $poll);

    return $hook;
});
check(result($poll) === 'ok' && result($webhook) === 'processed', 'Concurrent GET and webhook did not both complete.');
captured($g);
balances($user, 7500000000, 0);
echo "PASS D webhook and reconciliation GET\n";

$user = userWithCredits();
$g = admit($user);
$event = ['succeeded', $g->id, 'evt_'.Str::ulid()];
$outcomes = race('generations', $g->id, $event, $event);
sort($outcomes);
check($outcomes === ['duplicate', 'processed'], 'Duplicate event was processed twice.');
captured($g);
balances($user, 2500000000, 0);
echo "PASS E simultaneous duplicate webhook\n";

foreach (['failed', 'succeeded'] as $first) {
    $user = userWithCredits();
    $g = admit($user);
    $second = $first === 'failed' ? 'succeeded' : 'failed';
    race('generations', $g->id, [$first, $g->id, 'evt_'.Str::ulid()], [$second, $g->id, 'evt_'.Str::ulid()]);
    check($g->fresh()->provider_status->value === $first, 'Later terminal event replaced the first.');
    if ($first === 'succeeded') {
        captured($g);
        balances($user, 2500000000, 0);
    } else {
        check(! $g->settlement()->exists() && $g->reservation()->value('state') === 'released', 'Charged failed generation.');
        balances($user, 5000000000, 0);
    }
}
echo "PASS F conflicting terminal events in both orders\n";

$user = userWithCredits();
$g = admit($user);
$id = 'evt_'.Str::ulid();
DB::statement("CREATE FUNCTION abort_close() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'injected rollback'; END; $$");
DB::statement("CREATE TRIGGER abort_close BEFORE UPDATE ON credit_reservations FOR EACH ROW WHEN (NEW.state='captured') EXECUTE FUNCTION abort_close()");
blockedSql(fn () => deliver($g, 'succeeded', $id), 'P0001');
check(! $g->settlement()->exists() && $g->fresh()->provider_status === null && ! DB::table('pixelforge_webhook_events')->where('provider_event_id', $id)->exists(), 'Rollback left partial financial state.');
balances($user, 5000000000, 5000000000);
DB::statement('DROP TRIGGER abort_close ON credit_reservations');
// Prove the amount FK independently of the unique capture key and ownership FK.
DB::statement('CREATE FUNCTION alter_capture() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN NEW.amount := NEW.amount + 1; RETURN NEW; END; $$');
DB::statement('CREATE TRIGGER alter_capture BEFORE INSERT ON credit_ledger_entries FOR EACH ROW WHEN (NEW.generation_id IS NOT NULL) EXECUTE FUNCTION alter_capture()');
blockedSql(fn () => deliver($g, 'succeeded', $id), '23503');
check(! $g->settlement()->exists(), 'Mismatched capture left a settlement.');
balances($user, 5000000000, 5000000000);
DB::statement('DROP TRIGGER alter_capture ON credit_ledger_entries');
deliver($g, 'succeeded', $id);
captured($g);
echo "PASS G injected rollback and replay\n";

foreach (['5', '10'] as $amount) {
    $user = userWithCredits($amount);
    $g = admit($user);
    $changes = ['rates' => rates('4000000')];
    apply($g, GenerationStatus::Queued, $changes);
    $hold = $g->reservation()->firstOrFail();
    check($hold->accepted_maximum === 10000000000 && $hold->held === (int) $amount * 1000000000, 'Incorrect drift exposure.');
    $usage = (new TokenUsage(2, 0, 280, 0))->toArray();
    apply($g, GenerationStatus::Succeeded, $changes + ['usage' => $usage]);
    captured($g, 10000000000);
    balances($user, ((int) $amount - 10) * 1000000000, 0);
}
echo "PASS H funded and unfunded rate drift\n";

$user = userWithCredits();
$g = admit($user);
Http::fake(['*/generations' => Http::failedConnection()]);
$job = (new SubmitGeneration($g->id))->withFakeQueueInteractions();
$job->handle(app(PixelforgeClient::class), app(ApplyGenerationSnapshot::class));
check($g->fresh()->submission_status->value === 'uncertain', 'Timeout was misclassified.');
balances($user, 5000000000, 5000000000);
deliver($g, 'succeeded', 'evt_'.Str::ulid());
captured($g);
balances($user, 2500000000, 0);
echo "PASS I ambiguous POST followed by terminal confirmation\n";

$other = userWithCredits(null);
$waiting = DB::transaction(function () use ($user, $other) {
    CreditWallet::lockForUpdate()->findOrFail($user->id);
    $waiting = worker('blocked', 'purchase', $user->id);
    waitForDatabaseLock('blocked', $waiting);
    check(result(worker('independent', 'purchase', $other->id)) === 'ok', 'Independent wallet blocked.');

    return $waiting;
});
result($waiting);
balances($user, 7500000000, 0);
balances($other, 5000000000, 0);
echo "PASS J independent wallets progress under same-user contention\n";

$ledger = DB::table('credit_ledger_entries')->where('user_id', $other->id);
blockedSql(fn () => $ledger->update(['amount' => 1]), 'P0001');
blockedSql(fn () => $ledger->delete(), 'P0001');
$settlement = DB::table('generation_settlements')->where('id', $fact->id);
blockedSql(fn () => $settlement->increment('provider_cost_nanodollars'), 'P0001');
blockedSql(fn () => $settlement->delete(), 'P0001');
blockedSql(fn () => DB::table('credit_wallets')->insert(['user_id' => $user->id]), '23505');
$hold = (array) DB::table('credit_reservations')->where('generation_id', $g->id)->first();
blockedSql(fn () => DB::table('credit_reservations')->insert($hold), '23505');
$entry = (array) DB::table('credit_ledger_entries')->where('generation_id', $g->id)->first();
$entry['id'] = (string) Str::uuid();
blockedSql(fn () => DB::table('credit_ledger_entries')->insert($entry), '23505');
$unsettled = admit($user);
$entry['generation_id'] = $unsettled->id;
blockedSql(fn () => DB::table('credit_ledger_entries')->insert($entry), '23503');
blockedSql(fn () => DB::table('credit_wallets')->where('user_id', $user->id)->update(['reserved' => -1]), '23514');
echo "PASS immutable ledger and settlements, uniqueness, checks and capture foreign key\n";
$report = app(FinancialReport::class)->build(new ReportPeriod('2026-11-17', '2026-11-17'));
$expected = DB::table('generation_settlements as s')->join('generations as g', 's.generation_id', '=', 'g.id')
    ->whereDate('g.provider_completed_at', '2026-11-17')->sum('s.provider_cost_nanodollars');
check((string) $expected === $report['summary']['provider_cost_nanodollars'], 'PostgreSQL financial report differs from exact NUMERIC total.');
echo "PASS daily report against PostgreSQL NUMERIC aggregate\n";

// Include adjacent UTC days and exceed BIGINT only in the report aggregate.
foreach (['2030-01-01 23:59:59', '2030-01-02 00:00:00', '2030-01-02 00:00:01'] as $time) {
    $historical = Generation::factory()->for($legacy)->succeeded()->create(['provider_completed_at' => $time]);
    GenerationSettlement::factory()->create(['generation_id' => $historical->id, 'provider_cost_nanodollars' => '5000000000000000001', 'customer_charge_nanodollars' => '6250000000000000001']);
}
$totals = app(FinancialReport::class)->build(new ReportPeriod('2030-01-02', '2030-01-02'))['summary'];
check($totals['provider_cost_usd'] === '10000000000.000000002' && $totals['customer_charge_usd'] === '12500000000.000000002', 'Precision or UTC date regression.');
echo "PASS midnight and wider-than-BIGINT exact reporting\nCOMPLETE upgrade\n";
