<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\DocumentRoutingActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * Same gap as ViewDocument had: routing actions only existed on the
     * table row, so a user editing a document — e.g. revising a returned
     * one — had no way to then resubmit it without navigating back to the
     * list. Mirrored here for the same reason.
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DocumentRoutingActions::submit(),
            DocumentRoutingActions::receive(),
            DocumentRoutingActions::forward(),
            DocumentRoutingActions::submitForApproval(),
            DocumentRoutingActions::approve(),
            DocumentRoutingActions::return(),
            DocumentRoutingActions::resubmit(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
