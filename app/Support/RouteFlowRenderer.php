<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Route;
use Illuminate\Support\HtmlString;

/**
 * Renders a Route's steps as a horizontal process-flow visual, highlighting
 * $record's current step when one is given (Document editing/view — a new,
 * unsaved Document has no status yet, so nothing is highlighted there).
 * Shared by DocumentForm's Create/Edit preview and DocumentInfolist's View
 * display, so both render identically.
 */
class RouteFlowRenderer
{
    public static function render(?int $routeId, ?Document $record): ?HtmlString
    {
        if (! $routeId) {
            return null;
        }

        $route = Route::query()->with('steps.office')->find($routeId);

        if (! $route || $route->steps->isEmpty()) {
            return null;
        }

        return new HtmlString(view('filament.documents.route-flow', [
            'route' => $route,
            'record' => $record,
        ])->render());
    }
}
