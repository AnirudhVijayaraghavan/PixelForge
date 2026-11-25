<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE credit_wallets (
            user_id BIGINT PRIMARY KEY NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            settled BIGINT NOT NULL DEFAULT 0,
            reserved BIGINT NOT NULL DEFAULT 0 CHECK (reserved >= 0)
        )');
        DB::statement('CREATE UNIQUE INDEX generations_wallet_owner ON generations (id, user_id)');
        DB::statement('CREATE UNIQUE INDEX settlements_capture_amount ON generation_settlements (generation_id, customer_charge_nanodollars)');
        DB::statement("CREATE TABLE credit_reservations (
            generation_id UUID PRIMARY KEY NOT NULL,
            user_id BIGINT NOT NULL REFERENCES credit_wallets(user_id),
            intent_id UUID NOT NULL,
            held BIGINT NOT NULL CHECK (held >= 0),
            maximum_unavailable BOOLEAN NOT NULL DEFAULT FALSE,
            accepted_maximum BIGINT CHECK (accepted_maximum >= 0),
            state VARCHAR(10) NOT NULL CHECK (state IN ('active','released','captured')),
            closed_at TIMESTAMP,
            UNIQUE (user_id, intent_id), UNIQUE (generation_id, user_id),
            CHECK ((state = 'active') = (closed_at IS NULL)),
            FOREIGN KEY (generation_id, user_id) REFERENCES generations (id, user_id)
        )");
        DB::statement('CREATE TABLE credit_ledger_entries (
            id UUID PRIMARY KEY NOT NULL,
            user_id BIGINT NOT NULL REFERENCES credit_wallets(user_id),
            amount BIGINT NOT NULL CHECK (amount >= 0),
            purchase_intent UUID,
            generation_id UUID UNIQUE,
            created_at TIMESTAMP NOT NULL,
            UNIQUE (user_id, purchase_intent),
            CHECK ((purchase_intent IS NULL) <> (generation_id IS NULL)),
            CHECK (purchase_intent IS NULL OR amount > 0),
            FOREIGN KEY (generation_id, user_id) REFERENCES credit_reservations (generation_id, user_id),
            FOREIGN KEY (generation_id, amount) REFERENCES generation_settlements (generation_id, customer_charge_nanodollars)
        )');
        DB::statement('CREATE INDEX wallet_activity ON credit_ledger_entries (user_id, created_at, id)');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("CREATE FUNCTION reject_credit_ledger_change() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Credit ledger entries are immutable'; END; $$");
            DB::statement('CREATE TRIGGER immutable_credit_ledger BEFORE UPDATE OR DELETE ON credit_ledger_entries FOR EACH ROW EXECUTE FUNCTION reject_credit_ledger_change()');
        } else {
            foreach (['UPDATE', 'DELETE'] as $operation) {
                DB::unprepared("CREATE TRIGGER immutable_ledger_{$operation} BEFORE {$operation} ON credit_ledger_entries BEGIN SELECT RAISE(ABORT, 'Credit ledger entries are immutable'); END");
            }
        }
        DB::statement('INSERT INTO credit_wallets (user_id) SELECT id FROM users');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE credit_ledger_entries');
        DB::statement('DROP TABLE credit_reservations');
        DB::statement('DROP TABLE credit_wallets');
        DB::statement('DROP INDEX settlements_capture_amount');
        DB::statement('DROP INDEX generations_wallet_owner');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION reject_credit_ledger_change()');
        }
    }
};
