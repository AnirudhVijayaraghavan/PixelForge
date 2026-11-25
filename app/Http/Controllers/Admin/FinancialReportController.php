<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\FinancialReport;
use App\Domain\Billing\ProviderReconciliation;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialReportRequest;
use Inertia\Inertia;
use Inertia\Response;

class FinancialReportController extends Controller
{
    public function __invoke(FinancialReportRequest $request, FinancialReport $reports, ProviderReconciliation $reconciliation): Response
    {
        $period = $request->period();
        $report = $reports->build($period);

        return Inertia::render('admin/financials/Index', [
            'filters' => $period->toArray(), 'report' => $report,
            'reconciliation' => $reconciliation->compare($period, $report['daily']),
            'refreshed' => $request->session()->get('reconciliation_refreshed', false),
        ]);
    }
}
