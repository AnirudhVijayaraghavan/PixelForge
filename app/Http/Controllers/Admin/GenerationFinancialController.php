<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GenerationFinancialController extends Controller
{
    public function __invoke(Request $request, Generation $generation): Response
    {
        Gate::authorize('viewFinancialReports');

        return Inertia::render('admin/financials/Show', [
            'generation' => (new GenerationResource($generation->load('settlement')))->detail($request),
            'provider_generation_id' => $generation->provider_generation_id,
            'user_name' => $generation->user->name,
        ]);
    }
}
