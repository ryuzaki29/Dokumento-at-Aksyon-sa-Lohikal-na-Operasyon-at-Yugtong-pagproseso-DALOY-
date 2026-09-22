<?php

namespace App\Filament\Pages;

use App\Enums\RouteActionType;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Office;
use App\Models\RouteAction;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The "1 report/output" requirement: a cross-document routing log — holder,
 * action, and timestamps — scoped through the same visibility rule as
 * DocumentResource so a user only sees history for documents they can see.
 */
class RoutingLogReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.routing-log-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Routing Log Report';

    protected static string|\UnitEnum|null $navigationGroup = 'Audit Trail';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                RouteAction::query()
                    ->whereIn('document_id', DocumentResource::getEloquentQuery()->select('id'))
            )
            ->columns([
                TextColumn::make('document.reference_no')
                    ->label('Document')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('acted_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->sortable(),
                TextColumn::make('fromOffice.name')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('toOffice.name')
                    ->label('To')
                    ->placeholder('—'),
                TextColumn::make('actor.name')
                    ->label('By'),
                TextColumn::make('remarks')
                    ->limit(60)
                    ->placeholder('—'),
            ])
            ->defaultSort('acted_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(RouteActionType::class),
                SelectFilter::make('from_office_id')
                    ->label('From Office')
                    ->options(fn () => Office::query()->pluck('name', 'id')),
                SelectFilter::make('to_office_id')
                    ->label('To Office')
                    ->options(fn () => Office::query()->pluck('name', 'id')),
                Filter::make('acted_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('acted_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('acted_at', '<=', $date));
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    private function exportCsv(): StreamedResponse
    {
        $rows = $this->getFilteredTableQuery()
            ->with(['document', 'fromOffice', 'toOffice', 'actor'])
            ->orderByDesc('acted_at')
            ->get();

        return Response::streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Document', 'When', 'Action', 'From', 'To', 'By', 'Remarks']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->document?->reference_no,
                    $row->acted_at?->toDateTimeString(),
                    $row->action?->getLabel(),
                    $row->fromOffice?->name,
                    $row->toOffice?->name,
                    $row->actor?->name,
                    $row->remarks,
                ]);
            }

            fclose($handle);
        }, 'routing-log-'.now()->format('Y-m-d-His').'.csv');
    }
}
