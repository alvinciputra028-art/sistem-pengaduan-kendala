<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPriorityHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'old_priority',
        'new_priority',
        'score',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return ['score' => 'integer'];
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
