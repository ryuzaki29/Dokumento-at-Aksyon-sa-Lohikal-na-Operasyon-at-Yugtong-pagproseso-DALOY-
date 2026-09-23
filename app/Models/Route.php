<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named, ordered reference path of offices (e.g. "REC-BUD-LEG": Records ->
 * Budget -> Legal). Reference/documentation data only — routing a Document
 * is still free-choice regardless of what Routes exist; nothing in
 * DocumentRoutingService reads this table.
 */
class Route extends Model
{
    /** @use HasFactory<RouteFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    /**
     * `created_by`/`updated_by` are deliberately absent: HasAuditColumns
     * sets them via direct property assignment in its creating/updating
     * hooks, never through mass assignment, so a form can't spoof them.
     *
     * @var list<string>
     */
    protected $fillable = ['code', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RouteStep::class)->orderBy('sequence');
    }
}
