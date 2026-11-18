<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use RuntimeException;

final class PixelforgeException extends RuntimeException
{
    public function __construct(
        public readonly string $providerCode,
        string $safeMessage,
        public readonly bool $retryable = false,
        public readonly bool $uncertain = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($safeMessage);
    }

    public static function invalidResponse(): self
    {
        return new self('invalid_response', 'The provider returned an invalid or inconsistent response.', true, true);
    }

    public static function connectionFailure(bool $uncertain): self
    {
        return new self('connection_failure', 'The provider connection failed or timed out.', true, $uncertain);
    }

    public static function fromError(int $status, string $code, ?int $retryAfter): self
    {
        $definition = match ($code) {
            'invalid_api_key' => [401, 'Provider authentication is unavailable.', false],
            'validation_failed' => [422, 'The provider rejected the generation parameters.', false],
            'content_policy_violation' => [400, 'The provider rejected this prompt under its content policy.', false],
            'not_found' => [404, 'The provider generation was not found.', false],
            'idempotency_key_reused' => [409, 'The provider request identity conflicts with an earlier request.', false],
            'rate_limit_exceeded' => [429, 'The provider request rate limit was reached.', true],
            'concurrency_limit_exceeded' => [429, 'The provider concurrent generation limit was reached.', true],
            'daily_limit_exceeded' => [429, 'The provider daily quota is exhausted until the next UTC day.', false],
            'overloaded' => [503, 'The provider is temporarily overloaded.', true],
            default => null,
        };

        if ($definition === null || $status !== $definition[0]) {
            return new self('provider_error', 'The provider returned an unexpected error; the submission outcome needs recovery.', true, true, $retryAfter);
        }

        return new self($code, $definition[1], $definition[2], false, $retryAfter);
    }
}
