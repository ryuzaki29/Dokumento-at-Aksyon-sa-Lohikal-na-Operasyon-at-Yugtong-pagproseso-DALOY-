<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStep extends Model
{
    protected $fillable = ['route_id', 'office_id', 'sequence'];

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
