<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('TYP-???')),
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }
}
