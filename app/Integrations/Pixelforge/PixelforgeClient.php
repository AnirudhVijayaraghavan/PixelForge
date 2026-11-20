<?php

declare(strict_types=1);

namespace App\Integrations\Pixelforge;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Throwable;

final readonly class PixelforgeClient
{
    public function __construct(private Factory $http, private Cache $cache, private Config $config) {}

    public function pricing(): PixelforgePricingData
    {
        $key = 'pixelforge:pricing:v2:'.hash('sha256', $this->baseUrl());

        $data = $this->cache->remember($key, 60, function (): array {
            try {
                $response = $this->request()->get('/pricing');
            } catch (ConnectionException) {
                throw PixelforgeException::connectionFailure(false);
            }

            return $this->parse($response, 200, function (array $data): array {
                PixelforgePricingData::fromArray($data);

                return $data;
            });
        });

        try {
            return PixelforgePricingData::fromArray($data);
        } catch (Throwable) {
            throw PixelforgeException::invalidResponse();
        }
    }

    /** @param array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array{local_generation_id: string}} $payload */
    public function createGeneration(array $payload, string $idempotencyKey): PixelforgeGenerationData
    {
        try {
            $response = $this->request()->withToken($this->apiKey())
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->post('/generations', $payload);
        } catch (ConnectionException) {
            throw PixelforgeException::connectionFailure(true);
        }

        return $this->parse($response, 202, fn (array $data): PixelforgeGenerationData => PixelforgeGenerationData::fromArray(
            $data, strtolower($response->header('Idempotent-Replayed')) === 'true',
        ));
    }

    public function generation(string $id): PixelforgeGenerationData
    {
        if (! preg_match('/^gen_[A-Za-z0-9]+$/D', $id)) {
            throw PixelforgeException::invalidResponse();
        }

        try {
            $response = $this->request()->withToken($this->apiKey())->get('/generations/'.$id);
        } catch (ConnectionException) {
            throw PixelforgeException::connectionFailure(false);
        }

        $generation = $this->parse($response, 200, fn (array $data): PixelforgeGenerationData => PixelforgeGenerationData::fromArray($data));

        if ($generation->id !== $id) {
            throw PixelforgeException::invalidResponse();
        }

        return $generation;
    }

    private function request(): PendingRequest
    {
        return $this->http->baseUrl($this->baseUrl())->acceptJson()->asJson()
            ->connectTimeout(3)->timeout(15)->withoutRedirecting();
    }

    private function baseUrl(): string
    {
        $url = $this->config->get('services.pixelforge.base_url', 'https://pixelforge.promptedit.dev/v1');

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new PixelforgeException('invalid_configuration', 'The provider base URL is not configured correctly.');
        }

        return rtrim($url, '/');
    }

    private function apiKey(): string
    {
        $key = $this->config->get('services.pixelforge.api_key');

        if (! is_string($key) || $key === '') {
            throw new PixelforgeException('invalid_api_key', 'Provider authentication is unavailable.');
        }

        return $key;
    }

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $decode
     * @return T
     */
    private function parse(Response $response, int $expectedStatus, callable $decode): mixed
    {
        try {
            $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                throw PixelforgeException::invalidResponse();
            }

            if ($response->status() !== $expectedStatus) {
                $error = $data['error'] ?? null;

                if (! is_array($error) || ! is_string($error['code'] ?? null)
                    || ! is_string($error['type'] ?? null) || ! is_string($error['message'] ?? null)
                    || (isset($error['param']) && ! is_string($error['param']))
                    || (isset($error['errors']) && ! is_array($error['errors']))) {
                    throw PixelforgeException::invalidResponse();
                }

                $header = $response->header('Retry-After');
                $retryAfter = $header === '' ? null : filter_var($header, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

                if ($retryAfter === false) {
                    throw PixelforgeException::invalidResponse();
                }

                throw PixelforgeException::fromError($response->status(), $error['code'], $retryAfter);
            }

            return $decode($data);
        } catch (PixelforgeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw PixelforgeException::invalidResponse();
        }
    }
}
