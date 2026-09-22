<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        $originatingOfficeId = Office::factory()->create()->id;

        return [
            'reference_no' => Document::generateReferenceNo(),
            'document_type_id' => DocumentType::factory(),
            'subject' => fake()->sentence(6),
            'originating_office_id' => $originatingOfficeId,
            'current_office_id' => $originatingOfficeId,
            'status' => DocumentStatus::Registered,
            'created_by' => User::factory(),
        ];
    }
}
