<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * Set by createDraft() before delegating to the normal create() flow, so
     * mutateFormDataBeforeCreate() can tell which of the two form buttons was
     * pressed without duplicating the whole create pipeline.
     */
    private bool $savingAsDraft = false;

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()->label('Register'),
            Action::make('saveAsDraft')
                ->label('Save as Draft')
                ->color('gray')
                ->action('createDraft'),
            $this->getCancelFormAction(),
        ];
    }

    public function createDraft(): void
    {
        $this->savingAsDraft = true;

        $this->create();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->savingAsDraft ? 'Draft saved' : 'Document registered';
    }

    /**
     * `status`, `originating_office_id`, and `current_office_id` are
     * system-managed, not part of the form: a document always originates
     * from — and starts held at — the registering user's own office, never
     * a freely-chosen one, so this is set from `auth()->user()` rather than
     * read from form data. The form only shows it as a read-only Placeholder.
     *
     * A draft has no current_office_id yet — it hasn't entered routing, so
     * it stays private to its creator (see DocumentResource::getEloquentQuery())
     * until the Submit action moves it to Registered.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $officeId = auth()->user()->office_id;

        if (! $officeId) {
            Notification::make()
                ->title('You must belong to an office before you can register a document.')
                ->danger()
                ->send();

            $this->halt();
        }

        $data['originating_office_id'] = $officeId;

        if ($this->savingAsDraft) {
            $data['status'] = DocumentStatus::Draft;
            $data['current_office_id'] = null;
        } else {
            $data['status'] = DocumentStatus::Registered;
            $data['current_office_id'] = $officeId;
        }

        return $data;
    }
}
