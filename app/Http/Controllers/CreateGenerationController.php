<?php

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Domain\Billing\Money;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Http\Requests\CreateGenerationRequest;
use App\Integrations\Pixelforge\PixelforgeException;
use Illuminate\Http\JsonResponse;

class CreateGenerationController extends Controller
{
    public function __invoke(CreateGenerationRequest $request, CreateGeneration $createGeneration): JsonResponse
    {
        try {
            $generation = $createGeneration->handle(
                $request->user(),
                $request->string('prompt')->toString(),
                GenerationSize::from($request->string('size')->toString()),
                GenerationQuality::from($request->string('quality')->toString()),
                $request->validated('reference_images', []),
                $request->validated('intent'),
            );
        } catch (PixelforgeException) {
            return response()->json(['message' => 'Current pricing is unavailable. Please try again later.', 'code' => 'pricing_unavailable'], 503);
        }

        return response()->json([
            'id' => $generation->id,
            'submission_status' => $generation->submission_status->value,
            'estimated_customer_charge_usd' => Money::fromNanodollars($generation->estimated_customer_charge_nanodollars)->toUsd(),
        ], 202);
    }
}
