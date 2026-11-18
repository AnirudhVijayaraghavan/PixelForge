<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerationStatusesRequest;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use Illuminate\Http\JsonResponse;

class GenerationStatusesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GenerationStatusesRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');
        $generations = $request->user()->generations()->with('settlement')->whereIn('id', $ids)->get();
        abort_unless($generations->count() === count($ids), 404);

        return response()->json(['generations' => $generations->map(function (Generation $generation) use ($request): array {
            $resource = new GenerationResource($generation);

            return $request->boolean('detail') ? $resource->detail($request) : $resource->resolve($request);
        })])->header('Cache-Control', 'private, no-store');
    }
}
