<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'comment_id',
        'uploaded_by',
        'storage_path',
        'original_name',
        'mime_type',
        'size',
        'checksum',
        'retained_until',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'retained_until' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ReportComment::class, 'comment_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
