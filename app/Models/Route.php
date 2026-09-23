<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Database\Factories\RouteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A configured, ordered path of offices a document of one DocumentType must
 * follow. Optional: a DocumentType with no active Route (or none at all)
 * keeps the original free-choice routing — see
 * DocumentRoutingService::assertMatchesConfiguredRoute(). The last step in
 * `steps` is always the approving office; every step before it is a
 * forward-only stop.
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
    protected $fillable = ['document_type_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RouteStep::class)->orderBy('sequence');
    }

    /**
     * The office the document should go to next, given where it currently
     * sits. Null if the current office is already the last configured step
     * (nothing further defined) or `steps` is empty.
     */
    public function nextOfficeIdAfter(?int $currentOfficeId): ?int
    {
        $steps = $this->steps;

        if ($steps->isEmpty()) {
            return null;
        }

        $index = $steps->search(fn (RouteStep $step): bool => $step->office_id === $currentOfficeId);

        if ($index === false) {
            return $steps->first()->office_id;
        }

        return $steps->get($index + 1)?->office_id;
    }

    public function isFinalStepOffice(int $officeId): bool
    {
        return $this->steps->last()?->office_id === $officeId;
    }
}
