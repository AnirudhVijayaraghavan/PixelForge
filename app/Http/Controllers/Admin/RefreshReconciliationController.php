<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\ProviderReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialReportRequest;
use Illuminate\Http\RedirectResponse;

class RefreshReconciliationController extends Controller
{
    public function __invoke(FinancialReportRequest $request, ProviderReconciliation $reconciliation): RedirectResponse
    {
        $error = $reconciliation->refresh($request->period());
        $response = to_route('admin.financials.index', $request->period()->toArray());

        return $error === null ? $response->with('reconciliation_refreshed', true) : $response->withErrors(['reconciliation' => $error]);
    }
}
