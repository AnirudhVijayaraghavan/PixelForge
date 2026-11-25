<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Money;
use App\Domain\Billing\WalletAccounting;
use App\Http\Requests\BuyCreditsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class WalletController extends Controller
{
    public function index(Request $request, WalletAccounting $accounts): Response
    {
        $wallet = $accounts->wallet($request->user());
        $entries = DB::table('credit_ledger_entries')->where('user_id', $wallet->user_id)
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20);
        $items = array_map(fn (stdClass $entry): array => [
            'id' => $entry->id, 'amount' => Money::fromNanodollars($entry->amount)->toUsd(),
            'generation_id' => $entry->generation_id, 'created_at' => $entry->created_at,
        ], $entries->items());

        return Inertia::render('wallet/Index', ['balances' => $wallet->summary(), 'activity' => ['data' => $items, 'current_page' => $entries->currentPage(), 'next_page_url' => $entries->nextPageUrl(), 'prev_page_url' => $entries->previousPageUrl()],
            'denominations' => WalletAccounting::DENOMINATIONS]);
    }

    public function purchase(BuyCreditsRequest $request, WalletAccounting $accounts): RedirectResponse
    {
        $accounts->purchase($request->user(), $request->validated('denomination'), $request->validated('intent'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Simulated credits added to your wallet.']);

        return to_route('wallet.index');
    }
}
