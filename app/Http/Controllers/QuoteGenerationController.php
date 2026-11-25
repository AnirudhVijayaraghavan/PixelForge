<?php

namespace App\Http\Controllers;

use App\Actions\Generations\QuoteGeneration;
use App\Domain\Billing\WalletAccounting;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Http\Requests\CreateGenerationRequest;
use App\Integrations\Pixelforge\PixelforgeException;
use Illuminate\Http\JsonResponse;

class QuoteGenerationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CreateGenerationRequest $request, QuoteGeneration $quoteGeneration): JsonResponse
    {
        try {
            $quote = $quoteGeneration->handle(
                $request->string('prompt')->toString(),
                GenerationSize::from($request->string('size')->toString()),
                GenerationQuality::from($request->string('quality')->toString()),
                $request->validated('reference_images', []),
            );
        } catch (PixelforgeException) {
            return response()->json(['message' => 'Current pricing is unavailable. Please try again later.', 'code' => 'pricing_unavailable'], 503);
        }

        $wallet = app(WalletAccounting::class)->wallet($request->user());

        return response()->json([
            'available_usd' => $wallet->available()->toUsd(),
            'affordable' => $wallet->available()->compareTo($quote->maximum->customerCharge) >= 0,
            ...$quote->toArray(),
            'provenance' => 'Pixelforge public pricing, cached for up to 60 seconds. Creation is independently repriced.',
            'quoted_at' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }
}
