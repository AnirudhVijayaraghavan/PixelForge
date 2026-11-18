<?php

namespace Database\Factories;

use App\Domain\Generations\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GenerationSettlement> */
class GenerationSettlementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'generation_id' => Generation::factory()->succeeded(),
            'provider_status' => GenerationStatus::Succeeded,
            'provider_cost_nanodollars' => '57780000',
            'customer_charge_nanodollars' => '72225000',
            'settled_at' => now(),
        ];
    }
}
