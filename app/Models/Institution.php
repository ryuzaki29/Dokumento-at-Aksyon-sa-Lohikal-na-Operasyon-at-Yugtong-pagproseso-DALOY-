<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasAuditColumns, HasFactory, SoftDeletes;

    /**
     * `created_by`/`updated_by` are deliberately absent: HasAuditColumns
     * sets them via direct property assignment in its creating/updating
     * hooks, never through mass assignment, so a form can't spoof them.
     *
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
