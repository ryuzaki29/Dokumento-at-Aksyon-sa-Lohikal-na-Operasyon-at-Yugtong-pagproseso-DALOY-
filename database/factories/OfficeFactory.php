<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('OFF-???')),
            'name' => fake()->unique()->company().' Office',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
