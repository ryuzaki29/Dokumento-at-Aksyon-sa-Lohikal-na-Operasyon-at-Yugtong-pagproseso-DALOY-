<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\DocumentRoutingActions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * The routing actions (receive/forward/submit/approve/return/resubmit)
     * previously only existed as table row actions on the list page, so a
     * user who opened a document's own detail page — e.g. a returned
     * document they need to resubmit — had no way to act on it at all.
     * Mirroring DocumentsTable's recordActions here fixes that; each
     * action's own visible() already scopes it to the right status/office/
     * permission/creator.
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DocumentRoutingActions::submit(),
            DocumentRoutingActions::receive(),
            DocumentRoutingActions::forward(),
            DocumentRoutingActions::submitForApproval(),
            DocumentRoutingActions::approve(),
            DocumentRoutingActions::return(),
            DocumentRoutingActions::resubmit(),
        ];
    }
}
