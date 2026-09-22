<?php

namespace App\Models;

use App\Enums\RouteActionType;
use Database\Factories\RouteActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only routing history: no SoftDeletes, no created_by/updated_by.
 * `acted_by`/`acted_at` already record authorship and time, and history rows
 * are only ever written by the Document routing actions, never hand-edited.
 */
class RouteAction extends Model
{
    /** @use HasFactory<RouteActionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_id', 'from_office_id', 'to_office_id', 'action', 'remarks', 'acted_by', 'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => RouteActionType::class,
            'acted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'to_office_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
