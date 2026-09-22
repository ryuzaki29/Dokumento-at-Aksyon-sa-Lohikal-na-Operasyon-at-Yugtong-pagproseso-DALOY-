<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasAuditColumns;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    /**
     * `status` and `current_office_id` are fillable so the Create/routing
     * actions can set them server-side, but neither appears in the Filament
     * form schema, so a user can never edit them directly through the UI.
     * `reference_no` is never fillable — it is always generated in
     * `booted()`. `created_by`/`updated_by` are set by HasAuditColumns via
     * direct property assignment, not mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_type_id', 'subject', 'description', 'originating_office_id', 'file_path', 'status', 'current_office_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $document->reference_no ??= static::generateReferenceNo();
        });
    }

    /**
     * `DOC-<year>-<sequence>`, computed from the count of documents already
     * created this year. Callers must create the record inside the same
     * DB::transaction() as any lockForUpdate() step that also touches
     * `documents` to keep this race-free.
     */
    public static function generateReferenceNo(): string
    {
        $year = now()->format('Y');

        $sequence = static::withTrashed()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('DOC-%s-%05d', $year, $sequence);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function originatingOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'originating_office_id');
    }

    public function currentOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    public function routeActions(): HasMany
    {
        return $this->hasMany(RouteAction::class)->orderBy('acted_at');
    }
}
