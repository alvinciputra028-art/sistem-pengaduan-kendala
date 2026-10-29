<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
        'body',
        'visibility',
        'deleted_by',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'comment_id');
    }

    public function moderationReports(): HasMany
    {
        return $this->hasMany(ReportCommentModerationReport::class, 'comment_id');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('not_deleted', function ($query): void {
            $query->whereNull('deleted_at');
        });
    }
}
