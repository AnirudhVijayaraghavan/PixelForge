<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION reject_settlement_change() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Generation settlements are immutable'; END; $$");
            DB::statement('CREATE TRIGGER immutable_settlement BEFORE UPDATE OR DELETE ON generation_settlements FOR EACH ROW EXECUTE FUNCTION reject_settlement_change()');

            return;
        }
        foreach (['UPDATE', 'DELETE'] as $operation) {
            DB::unprepared("CREATE TRIGGER immutable_settlement_{$operation} BEFORE {$operation} ON generation_settlements BEGIN SELECT RAISE(ABORT, 'Generation settlements are immutable'); END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER immutable_settlement ON generation_settlements');
            DB::statement('DROP FUNCTION reject_settlement_change()');

            return;
        }
        foreach (['UPDATE', 'DELETE'] as $operation) {
            DB::statement("DROP TRIGGER immutable_settlement_{$operation}");
        }
    }
};
