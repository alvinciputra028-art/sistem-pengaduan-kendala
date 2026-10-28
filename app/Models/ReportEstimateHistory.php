<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportEstimateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'old_estimated_response_at',
        'new_estimated_response_at',
        'old_estimated_resolution_at',
        'new_estimated_resolution_at',
        'estimate_note',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_estimated_response_at' => 'datetime',
            'new_estimated_response_at' => 'datetime',
            'old_estimated_resolution_at' => 'datetime',
            'new_estimated_resolution_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
