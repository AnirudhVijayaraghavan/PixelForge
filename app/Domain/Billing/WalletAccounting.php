<?php

namespace App\Domain\Billing;

use App\Models\CreditReservation;
use App\Models\CreditWallet;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletAccounting
{
    public const DENOMINATIONS = ['5', '10', '25', '50'];

    public function wallet(User $user): CreditWallet
    {
        return CreditWallet::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** Caller holds an open transaction. The wallet serializes all writes for its owner. */
    public function lock(int $userId): CreditWallet
    {
        return CreditWallet::query()->lockForUpdate()->findOrFail($userId);
    }

    public function purchase(User $user, string $denomination, string $intent): void
    {
        if (! in_array($denomination, self::DENOMINATIONS, true)) {
            throw ValidationException::withMessages(['denomination' => 'Choose a listed credit pack.']);
        }
        $this->wallet($user);
        DB::transaction(function () use ($user, $denomination, $intent): void {
            $wallet = $this->lock($user->id);
            $amount = Money::fromUsd($denomination);
            $prior = DB::table('credit_ledger_entries')->where('user_id', $user->id)->where('purchase_intent', $intent)->first();
            if ($prior !== null) {
                if ((int) $prior->amount !== $amount->nanodollars()) {
                    throw ValidationException::withMessages(['intent' => 'This purchase was already confirmed for a different amount.']);
                }

                return;
            }
            try {
                $wallet->settled = Money::fromNanodollars($wallet->settled)->add($amount)->nanodollars();
            } catch (\OverflowException) {
                throw ValidationException::withMessages(['denomination' => 'This purchase would exceed the wallet balance limit.']);
            }
            $wallet->save();
            DB::table('credit_ledger_entries')->insert(['id' => (string) Str::uuid7(), 'user_id' => $user->id, 'amount' => $amount->nanodollars(), 'purchase_intent' => $intent, 'created_at' => now()]);
        }, 3);
    }

    public function reserve(CreditWallet $wallet, Generation $generation, string $intent, Money $amount): void
    {
        if ($wallet->available()->compareTo($amount) < 0) {
            throw ValidationException::withMessages(['wallet' => 'Available credits are below the maximum reservation of $'.$amount->toUsd().'. Buy simulated credits to continue.']);
        }
        $hold = new CreditReservation;
        $hold->generation_id = $generation->id;
        $hold->user_id = $wallet->user_id;
        $hold->held = $amount->nanodollars();
        $hold->intent_id = $intent;
        $hold->state = 'active';
        $hold->save();
        $wallet->reserved = Money::fromNanodollars($wallet->reserved)->add($amount)->nanodollars();
        $wallet->save();
    }

    /** Called inside the snapshot transaction with the generation locked. */
    public function accept(Generation $generation, ProviderRates $rates): void
    {
        $hold = $generation->reservation()->first();
        if ($hold === null || $hold->accepted_maximum !== null || $hold->maximum_unavailable) {
            return;
        }
        $pricing = new GenerationPricing(PricingParameters::fromPricingResponse($generation->quote_snapshot));
        try {
            $maximum = $pricing->calculateActual(TokenUsage::fromArray($generation->quote_snapshot['maximum_usage']), $rates)->customerCharge;
        } catch (\OverflowException|\InvalidArgumentException) {
            // A hypothetical maximum can exceed storage precision even when confirmed usage is representable.
            $hold->maximum_unavailable = true;
            $hold->save();

            return;
        }
        $hold->accepted_maximum = $maximum->nanodollars();
        $extra = $maximum->subtract(Money::fromNanodollars($hold->held));
        if ($extra->nanodollars() > 0) {
            $wallet = $this->lock($generation->user_id);
            if ($wallet->available()->compareTo($extra) >= 0) {
                $wallet->reserved = Money::fromNanodollars($wallet->reserved)->add($extra)->nanodollars();
                $wallet->save();
                $hold->held = $maximum->nanodollars();
            }
        }
        $hold->save();
    }

    /** The generation lock serializes closes. A missing hold identifies a pre-wallet intent. */
    public function close(Generation $generation, ?GenerationSettlement $settlement): void
    {
        $hold = $generation->reservation()->first();
        if ($hold === null || $hold->state !== 'active') {
            return;
        }
        $wallet = $this->lock($generation->user_id);
        $wallet->reserved = Money::fromNanodollars($wallet->reserved)->subtract(Money::fromNanodollars($hold->held))->nanodollars();
        $hold->state = 'released';
        if ($settlement !== null) {
            $charge = Money::fromNanodollars($settlement->customer_charge_nanodollars);
            DB::table('credit_ledger_entries')->insert(['id' => (string) Str::uuid7(), 'user_id' => $wallet->user_id, 'amount' => $charge->nanodollars(), 'generation_id' => $generation->id, 'created_at' => now()]);
            $wallet->settled = Money::fromNanodollars($wallet->settled)->subtract($charge)->nanodollars();
            $hold->state = 'captured';
        }
        $wallet->save();
        $hold->closed_at = now();
        $hold->save();
    }
}
