<?php

namespace App\Http\Controllers;

use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GenerationIndexController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Generation::class);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $generations = $request->user()->generations()->with('settlement')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12)
            ->through(fn (Generation $generation): array => (new GenerationResource($generation))->resolve($request));

        return Inertia::render('generations/Index', ['generations' => $generations]);
    }
}
