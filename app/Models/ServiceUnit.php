<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceUnit extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'service_unit_building');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_service_unit');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class);
    }
}
