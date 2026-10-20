<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'default_service_unit_id',
        'default_visibility',
        'is_sensitive',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function defaultServiceUnit(): BelongsTo
    {
        return $this->belongsTo(ServiceUnit::class, 'default_service_unit_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
