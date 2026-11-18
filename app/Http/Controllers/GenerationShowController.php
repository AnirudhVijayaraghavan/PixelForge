<?php

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GenerationShowController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Generation $generation): Response
    {
        Gate::authorize('view', $generation);

        return Inertia::render('generations/Show', [
            'generation' => (new GenerationResource($generation->load('settlement')))->detail($request),
        ]);
    }
}
