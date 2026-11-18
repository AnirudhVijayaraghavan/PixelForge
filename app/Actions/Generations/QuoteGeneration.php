<?php

namespace App\Actions\Generations;

use App\Domain\Billing\GenerationPricing;
use App\Domain\Billing\PriceQuote;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Integrations\Pixelforge\PixelforgeClient;
use App\Integrations\Pixelforge\PixelforgeException;
use Throwable;

final readonly class QuoteGeneration
{
    public function __construct(private PixelforgeClient $client) {}

    /** @param list<string> $referenceImages */
    public function handle(string $prompt, GenerationSize $size, GenerationQuality $quality, array $referenceImages): PriceQuote
    {
        $pricing = $this->client->pricing();

        try {
            return (new GenerationPricing($pricing->parameters))->quote($prompt, $size, $quality, $referenceImages, $pricing->rates);
        } catch (Throwable) {
            throw PixelforgeException::invalidResponse();
        }
    }
}
