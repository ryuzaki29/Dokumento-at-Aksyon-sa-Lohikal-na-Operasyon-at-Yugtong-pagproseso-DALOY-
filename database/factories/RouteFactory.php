<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Route;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_type_id' => DocumentType::factory(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
