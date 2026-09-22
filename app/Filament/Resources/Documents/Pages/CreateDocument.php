<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * `status` and `current_office_id` are system-managed, not part of the
     * form: a freshly registered document starts as Registered, held at its
     * own originating office.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = DocumentStatus::Registered;
        $data['current_office_id'] = $data['originating_office_id'];

        return $data;
    }
}
