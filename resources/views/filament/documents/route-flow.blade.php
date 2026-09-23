@php
    use App\Enums\DocumentStatus;

    $steps = $route->steps;
    $currentOfficeId = $record?->current_office_id;
    $isCompleted = $record?->status === DocumentStatus::Completed;
    $isActive = $record !== null && in_array($record->status, [DocumentStatus::InRouting, DocumentStatus::ForApproval], true);
    $onRoute = $currentOfficeId !== null && $steps->contains('office_id', $currentOfficeId);
@endphp

@if ($steps->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">This route has no steps configured.</p>
@else
    <div class="flex flex-wrap items-center gap-x-1 gap-y-3">
        @foreach ($steps as $index => $step)
            @php
                $isCurrent = $isActive && $step->office_id === $currentOfficeId;
            @endphp

            <div class="flex items-center gap-2">
                <span
                    @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                        'bg-primary-600 text-white ring-2 ring-primary-300 dark:ring-primary-500/50' => $isCurrent,
                        'bg-success-600 text-white' => $isCompleted && ! $isCurrent,
                        'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $isCurrent && ! $isCompleted,
                    ])
                >
                    @if ($isCompleted && ! $isCurrent)
                        <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>

                <span class="flex flex-col leading-tight">
                    <span
                        @class([
                            'text-sm',
                            'font-semibold text-primary-600 dark:text-primary-400' => $isCurrent,
                            'text-gray-700 dark:text-gray-200' => ! $isCurrent,
                        ])
                    >
                        {{ $step->office->name }}
                    </span>

                    @if (filled($step->roles))
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ implode(', ', $step->roles) }}
                        </span>
                    @endif
                </span>

                @if ($isCurrent)
                    <x-filament::badge color="warning" size="sm">Current</x-filament::badge>
                @endif
            </div>

            @if (! $loop->last)
                <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4 shrink-0 text-gray-400" />
            @endif
        @endforeach
    </div>

    @if ($record && $record->status !== DocumentStatus::Draft && $currentOfficeId !== null && ! $onRoute)
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            This document is currently at <strong>{{ $record->currentOffice?->name }}</strong>, which isn't one of this reference route's steps — routing isn't restricted to this path.
        </p>
    @endif
@endif
