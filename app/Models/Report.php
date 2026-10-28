<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'reporter_id',
        'building_id',
        'room_id',
        'category_id',
        'title',
        'description',
        'location_type',
        'location_description',
        'visibility',
        'identity_visibility',
        'impact_score',
        'urgency_score',
        'safety_risk_score',
        'assessment_answers',
        'incident_duration',
        'priority',
        'priority_score',
        'status',
        'estimated_response_at',
        'estimated_resolution_at',
        'estimate_note',
        'resolved_at',
        'closed_at',
        'retained_until',
    ];

    protected function casts(): array
    {
        return [
            'estimated_response_at' => 'datetime',
            'estimated_resolution_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'retained_until' => 'datetime',
            'impact_score' => 'integer',
            'urgency_score' => 'integer',
            'safety_risk_score' => 'integer',
            'assessment_answers' => 'array',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ReportAssignment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class);
    }

    public function priorityHistories(): HasMany
    {
        return $this->hasMany(ReportPriorityHistory::class);
    }

    public function estimateHistories(): HasMany
    {
        return $this->hasMany(ReportEstimateHistory::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
