<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStep extends Model
{
    /**
     * `roles` is a reference label — which role(s) are expected to handle
     * this step — shown in the Path/process-flow displays. It is not
     * enforced: DocumentRoutingActions/DocumentRoutingService don't read it.
     */
    protected $fillable = ['route_id', 'office_id', 'sequence', 'roles'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
        ];
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
