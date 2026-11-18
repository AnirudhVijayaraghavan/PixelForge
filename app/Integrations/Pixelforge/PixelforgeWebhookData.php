<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final readonly class PixelforgeWebhookData
{
    private function __construct(
        public string $id,
        public string $type,
        public CarbonImmutable $createdAt,
        public ?PixelforgeGenerationData $generation,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?string $headerId = null, ?string $headerType = null): self
    {
        $values = Validator::make($data, [
            'id' => ['required', 'string', 'max:255', 'regex:/^evt_[A-Za-z0-9]+$/D'],
            'object' => ['required', Rule::in(['event'])],
            'type' => ['required', Rule::in(['generation.processing', 'generation.succeeded', 'generation.failed', 'ping'])],
            'created_at' => ['required', 'string'],
        ])->validate();

        if (($headerId !== null && $headerId !== $values['id']) || ($headerType !== null && $headerType !== $values['type'])) {
            throw new InvalidArgumentException('Webhook headers disagree with the authenticated event.');
        }

        $generation = null;

        if ($values['type'] !== 'ping') {
            $object = $data['data']['object'] ?? null;

            if (! is_array($object)) {
                throw new InvalidArgumentException('Missing webhook generation.');
            }

            $generation = PixelforgeGenerationData::fromArray($object);
            Validator::make($generation->metadata, ['local_generation_id' => ['required', 'uuid']])->validate();

            if ($values['type'] !== 'generation.'.$generation->status->value) {
                throw new InvalidArgumentException('Webhook event type disagrees with the generation status.');
            }
        }

        $createdAt = PixelforgeGenerationData::timestamp($values['created_at']) ?? throw new InvalidArgumentException('Missing event timestamp.');

        return new self($values['id'], $values['type'], $createdAt, $generation);
    }
}
