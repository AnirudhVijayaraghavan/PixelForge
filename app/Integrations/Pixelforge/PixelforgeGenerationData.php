<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use App\Domain\Billing\ProviderRates;
use App\Domain\Billing\TokenUsage;
use App\Domain\Generations\Enums\GenerationQuality;
use App\Domain\Generations\Enums\GenerationSize;
use App\Domain\Generations\Enums\GenerationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final readonly class PixelforgeGenerationData
{
    /**
     * @param  list<string>  $referenceImages
     * @param  array<string, string>  $metadata
     */
    private function __construct(
        public string $id,
        public GenerationStatus $status,
        public string $prompt,
        public GenerationSize $size,
        public GenerationQuality $quality,
        public array $referenceImages,
        public array $metadata,
        public ProviderRates $rates,
        public ?TokenUsage $usage,
        public ?string $imageUrl,
        public ?CarbonImmutable $imageUrlExpiresAt,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $completedAt,
        public ?string $errorCode,
        public ?string $errorMessage,
        public bool $idempotentReplayed,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, bool $idempotentReplayed = false): self
    {
        $values = Validator::make($data, [
            'object' => ['required', Rule::in(['generation'])],
            'id' => ['required', 'string', 'regex:/^gen_[A-Za-z0-9]+$/D'],
            'status' => ['required', Rule::enum(GenerationStatus::class)],
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::enum(GenerationSize::class)],
            'quality' => ['required', Rule::enum(GenerationQuality::class)],
            'reference_images' => ['present', 'array', 'list', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
            'metadata' => ['present', 'array', 'max:20'],
            'metadata.*' => ['string', 'max:500'],
            'rates' => ['required', 'array'],
            'usage' => ['present', 'nullable', 'array'],
            'image_url' => ['present', 'nullable', 'string', 'url:http,https'],
            'image_url_expires_at' => ['present', 'nullable', 'string'],
            'error' => ['present', 'nullable', 'array'],
            'created_at' => ['required', 'string'],
            'started_at' => ['present', 'nullable', 'string'],
            'completed_at' => ['present', 'nullable', 'string'],
        ])->validate();

        if (! mb_check_encoding($values['prompt'], 'UTF-8')) {
            throw new InvalidArgumentException('Invalid provider prompt encoding.');
        }

        $status = GenerationStatus::from($values['status']);
        $created = self::timestamp($values['created_at']);
        $started = self::timestamp($values['started_at']);
        $completed = self::timestamp($values['completed_at']);
        $expires = self::timestamp($values['image_url_expires_at']);

        if ($created === null || ($started !== null && $started->lessThan($created))
            || ($completed !== null && $completed->lessThan($started ?? $created))) {
            throw new InvalidArgumentException('Invalid provider lifecycle timestamps.');
        }

        $terminal = in_array($status, [GenerationStatus::Succeeded, GenerationStatus::Failed], true);

        if ($terminal !== ($completed !== null)) {
            throw new InvalidArgumentException('Invalid terminal provider state.');
        }

        $usage = null;
        $errorCode = null;
        $errorMessage = null;

        if ($status === GenerationStatus::Succeeded) {
            if (! is_array($values['usage']) || ! array_key_exists('total_tokens', $values['usage'])
                || $values['image_url'] === null || $expires === null || $values['error'] !== null) {
                throw new InvalidArgumentException('Incomplete successful generation.');
            }

            $usage = TokenUsage::fromArray($values['usage']);
        } else {
            if ($values['usage'] !== null || $values['image_url'] !== null || $expires !== null) {
                throw new InvalidArgumentException('Unexpected success data on a non-successful generation.');
            }

            if ($status === GenerationStatus::Failed) {
                $error = $values['error'];

                if (! is_array($error) || ! is_string($error['code'] ?? null) || $error['code'] === ''
                    || ! is_string($error['message'] ?? null) || $error['message'] === '') {
                    throw new InvalidArgumentException('Missing provider failure details.');
                }

                $errorCode = $error['code'];
                $errorMessage = $error['message'];
            } elseif ($values['error'] !== null) {
                throw new InvalidArgumentException('Unexpected provider failure data.');
            }
        }

        foreach ($values['metadata'] as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Provider metadata keys must be strings.');
            }
        }

        return new self(
            $values['id'], $status, $values['prompt'],
            GenerationSize::from($values['size']), GenerationQuality::from($values['quality']),
            $values['reference_images'], $values['metadata'], ProviderRates::fromArray($values['rates']),
            $usage, $values['image_url'], $expires, $created, $started, $completed,
            $errorCode, $errorMessage, $idempotentReplayed,
        );
    }

    public static function timestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new InvalidArgumentException('Provider timestamps must use ISO 8601.');
        }

        $format = str_contains($value, '.') ? '!Y-m-d\TH:i:s.uP' : '!Y-m-d\TH:i:sP';
        $date = CarbonImmutable::createFromFormat($format, $value);
        $errors = CarbonImmutable::getLastErrors();

        if ($date === null || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Invalid provider timestamp.');
        }

        return $date->utc();
    }
}
