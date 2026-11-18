<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use Illuminate\Contracts\Config\Repository;

final readonly class PixelforgeWebhookSignatureVerifier
{
    public function __construct(private Repository $config) {}

    public function verify(string $body, ?string $header): bool
    {
        $secret = $this->config->get('services.pixelforge.webhook_secret');

        if (! is_string($secret) || trim($secret) === '' || $header === null) {
            return false;
        }

        /** Exactly one timestamp and digest; no ambiguous duplicate header components. */
        if (! preg_match('/^t=([1-9][0-9]{0,10}),v1=([a-fA-F0-9]{64})$/D', $header, $parts)) {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $parts[1]) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $parts[1].'.'.$body, $secret);

        return hash_equals($expected, strtolower($parts[2]));
    }
}
