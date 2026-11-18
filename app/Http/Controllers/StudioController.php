<?php

namespace App\Http\Controllers;

use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Http\Requests\StudioRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudioController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(StudioRequest $request): Response
    {
        $generation = null;

        if ($request->filled('reuse')) {
            $generation = $request->user()->generations()->whereKey($request->string('reuse')->toString())->firstOrFail();
            Gate::authorize('view', $generation);
        }

        return Inertia::render('studio/Index', [
            'inputs' => [
                'prompt' => $generation !== null ? $generation->prompt : '',
                'size' => ($generation !== null ? $generation->size : GenerationSize::Square)->value,
                'quality' => ($generation !== null ? $generation->quality : GenerationQuality::Medium)->value,
                'reference_images' => $generation !== null ? $generation->reference_images : [],
            ],
            'reusing' => $generation !== null,
        ]);
    }
}
