<?php

namespace Database\Factories;

use App\Enums\RouteActionType;
use App\Models\Document;
use App\Models\Office;
use App\Models\RouteAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteAction>
 */
class RouteActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'from_office_id' => Office::factory(),
            'to_office_id' => Office::factory(),
            'action' => fake()->randomElement(RouteActionType::cases()),
            'remarks' => fake()->optional()->sentence(),
            'acted_by' => User::factory(),
            'acted_at' => now(),
        ];
    }
}
