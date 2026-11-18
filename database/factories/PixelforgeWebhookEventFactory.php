<?php

namespace Database\Factories;

use App\Models\PixelforgeWebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PixelforgeWebhookEvent> */
class PixelforgeWebhookEventFactory extends Factory
{
    /** @return array{provider_event_id: string, generation_id: null, event_type: string, received_at: CarbonImmutable, diagnostic_metadata: array{delivery_attempt: int}} */
    public function definition(): array
    {
        return [
            'provider_event_id' => 'evt_'.Str::ulid(),
            'generation_id' => null,
            'event_type' => 'ping',
            'received_at' => now(),
            'diagnostic_metadata' => ['delivery_attempt' => 1],
        ];
    }
}
