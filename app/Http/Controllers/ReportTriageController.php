<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ServiceUnit;
use App\Models\User;
use App\Services\ReportNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportTriageController extends Controller
{
    private const TRANSITIONS = [
        'submitted' => ['triaged', 'assigned'],
        'triaged' => ['assigned', 'in_progress'],
        'assigned' => ['in_progress'],
        'in_progress' => ['waiting_confirmation'],
        'waiting_confirmation' => [],
        'reopened' => ['triaged', 'assigned', 'in_progress'],
        'closed' => ['reopened'],
    ];

    public function edit(Request $request, Report $report): View
    {
        $this->authorize('triage', $report);
        $report->load(['category', 'building', 'assignments.staff', 'assignments.serviceUnit']);

        $serviceUnits = ServiceUnit::query()
            ->where('is_active', true)
            ->whereHas('buildings', fn (Builder $query) => $query->whereKey($report->building_id))
            ->with(['staff' => fn ($query) => $query
                ->where('users.is_active', true)
                ->where('users.role', 'staff')
                ->whereHas('buildings', fn (Builder $building) => $building->whereKey($report->building_id))])
            ->orderBy('name')
            ->get();

        return view('reports.triage', [
            'report' => $report,
            'serviceUnits' => $serviceUnits,
            'activeAssignment' => $report->assignments->first(fn ($assignment): bool => $assignment->unassigned_at === null),
            'recommendedUnit' => $serviceUnits->first(fn (ServiceUnit $unit): bool => $unit->id === $report->category->default_service_unit_id),
            'availableTransitions' => self::TRANSITIONS[$report->status] ?? [],
        ]);
    }

    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('updatePriority', $report);
        $data = $request->validate([
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])],
            'reason' => ['nullable', 'string', 'max:2000'],
            'estimate_reason' => ['nullable', 'string', 'max:2000'],
            'estimate_note' => ['nullable', 'string', 'max:2000'],
            'estimated_response_at' => ['nullable', 'date'],
            'estimated_resolution_at' => ['nullable', 'date', 'after_or_equal:estimated_response_at'],
        ]);

        $priorityChanged = $data['priority'] !== $report->priority;
        $estimateChanged = $report->estimated_response_at?->toDateTimeString() !== $this->normalizeDate($data['estimated_response_at'] ?? null)
            || $report->estimated_resolution_at?->toDateTimeString() !== $this->normalizeDate($data['estimated_resolution_at'] ?? null)
            || $report->estimate_note !== ($data['estimate_note'] ?? null);
        if ($priorityChanged && blank($data['reason'] ?? null)) {
            return back()->withErrors(['reason' => 'Alasan wajib diisi jika prioritas diubah.'])->withInput();
        }
        if ($estimateChanged && blank($data['estimate_reason'] ?? null)) {
            return back()->withErrors(['estimate_reason' => 'Alasan wajib diisi jika estimasi diubah.'])->withInput();
        }

        DB::transaction(function () use ($data, $report, $request, $priorityChanged, $estimateChanged): void {
            $oldStatus = $report->status;
            $oldPriority = $report->priority;
            $oldResponseAt = $report->estimated_response_at;
            $oldResolutionAt = $report->estimated_resolution_at;
            $oldEstimateNote = $report->estimate_note;
            $newStatus = $request->user()->role === 'manager' && in_array($oldStatus, ['submitted', 'reopened'], true)
                ? 'triaged'
                : $oldStatus;
            $report->update([
                'priority' => $data['priority'],
                'status' => $newStatus,
                'estimate_note' => $data['estimate_note'] ?? null,
                'estimated_response_at' => $data['estimated_response_at'] ?? null,
                'estimated_resolution_at' => $data['estimated_resolution_at'] ?? null,
                'resolved_at' => $newStatus === 'waiting_confirmation' ? now() : null,
                'closed_at' => null,
            ]);

            if ($priorityChanged) {
                $report->priorityHistories()->create([
                    'old_priority' => $oldPriority,
                    'new_priority' => $data['priority'],
                    'score' => $report->priority_score,
                    'changed_by' => $request->user()->id,
                    'reason' => $data['reason'],
                ]);
            }

            if ($estimateChanged) {
                $report->estimateHistories()->create([
                    'old_estimated_response_at' => $oldResponseAt,
                    'new_estimated_response_at' => $report->estimated_response_at,
                    'old_estimated_resolution_at' => $oldResolutionAt,
                    'new_estimated_resolution_at' => $report->estimated_resolution_at,
                    'estimate_note' => $report->estimate_note,
                    'changed_by' => $request->user()->id,
                    'reason' => $data['estimate_reason'],
                ]);
            }

            if ($oldStatus !== $newStatus) {
                $report->statusHistories()->create([
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'changed_by' => $request->user()->id,
                    'note' => $data['reason'] ?? 'Triase disimpan.',
                ]);
            }
        });

        if ($priorityChanged) {
            app(ReportNotificationService::class)->notify(
                $report,
                'report.priority_changed',
                'Prioritas laporan '.$report->ticket_number.' diubah menjadi '.$report->priority.'.',
                $request->user(),
            );
        }
        if ($estimateChanged) {
            app(ReportNotificationService::class)->notify(
                $report,
                'report.estimate_changed',
                'Estimasi laporan '.$report->ticket_number.' diperbarui.',
                $request->user(),
            );
        }

        return redirect()->route('reports.show', $report)->with('success', 'Triase laporan berhasil diperbarui.');
    }

    private function normalizeDate(?string $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->toDateTimeString();
    }

    public function transition(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeTriage($request, $report);
        abort_unless($request->user()->role === 'staff', 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'resolved', 'waiting_confirmation'])],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $allowedStatuses = self::TRANSITIONS[$report->status] ?? [];
        abort_unless(in_array($data['status'], $allowedStatuses, true), 422, 'Transisi status tersebut tidak diizinkan.');

        DB::transaction(function () use ($data, $report, $request): void {
            $oldStatus = $report->status;
            $report->update([
                'status' => $data['status'],
                'resolved_at' => $data['status'] === 'waiting_confirmation' ? now() : null,
            ]);
            $report->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => $data['status'],
                'changed_by' => $request->user()->id,
                'note' => $data['note'],
            ]);
        });

        app(ReportNotificationService::class)->notify(
            $report,
            'report.status_changed',
            'Status laporan '.$report->ticket_number.' diubah menjadi '.$report->status.'.',
            $request->user(),
        );

        return redirect()->route('reports.show', $report)->with('success', 'Status laporan berhasil diperbarui.');
    }

    public function assign(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('triage', $report);
        abort_unless($request->user()->role === 'manager', 403);

        $data = $request->validate([
            'service_unit_id' => ['required', 'integer'],
            'staff_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $unit = ServiceUnit::query()
            ->whereKey($data['service_unit_id'])
            ->where('is_active', true)
            ->whereHas('buildings', fn (Builder $query) => $query->whereKey($report->building_id))
            ->firstOrFail();
        $staff = User::query()
            ->whereKey($data['staff_id'])
            ->where('role', 'staff')
            ->where('is_active', true)
            ->whereHas('serviceUnits', fn (Builder $query) => $query->whereKey($unit->id))
            ->whereHas('buildings', fn (Builder $query) => $query->whereKey($report->building_id))
            ->firstOrFail();

        DB::transaction(function () use ($report, $unit, $staff, $data, $request): void {
            $report->assignments()
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);
            $report->assignments()->create([
                'service_unit_id' => $unit->id,
                'staff_id' => $staff->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'note' => $data['note'] ?? null,
            ]);

            $oldStatus = $report->status;
            if (in_array($oldStatus, ['submitted', 'triaged'], true)) {
                $report->update(['status' => 'assigned']);
                $report->statusHistories()->create([
                    'from_status' => $oldStatus,
                    'to_status' => 'assigned',
                    'changed_by' => $request->user()->id,
                    'note' => 'Laporan ditugaskan kepada '.$staff->name.'.',
                ]);
            }
        });

        app(ReportNotificationService::class)->notify(
            $report,
            'report.assigned',
            'Laporan '.$report->ticket_number.' ditugaskan kepada '.$staff->name.'.',
            $request->user(),
        );

        return redirect()->route('reports.show', $report)->with('success', 'Laporan berhasil ditugaskan.');
    }

    private function authorizeTriage(Request $request, Report $report): void
    {
        $this->authorize('triage', $report);
    }
}
