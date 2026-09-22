<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\DocumentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Reuses the resource's own scoping so a Processing Staff or
        // Approver only sees counts for documents visible to them.
        $query = DocumentResource::getEloquentQuery();

        return [
            Stat::make('Registered', (clone $query)->where('status', DocumentStatus::Registered)->count())
                ->color('gray'),
            Stat::make('Pending Action', (clone $query)->whereIn('status', [
                DocumentStatus::InRouting,
                DocumentStatus::ForApproval,
            ])->count())
                ->color('warning'),
            Stat::make('Returned', (clone $query)->where('status', DocumentStatus::Returned)->count())
                ->color('danger'),
            Stat::make('Completed', (clone $query)->where('status', DocumentStatus::Completed)->count())
                ->color('success'),
        ];
    }
}
