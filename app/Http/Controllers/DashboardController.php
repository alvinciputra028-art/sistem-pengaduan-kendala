<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Policies\ReportPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $data = [
            'user' => $user,
            'recentReports' => collect(),
            'reportCounts' => [],
            'priorityReports' => collect(),
            'moderationCount' => 0,
        ];

        if ($user->role === 'resident') {
            $residentReports = app(ReportPolicy::class)->visibleQuery($user);
            $data['reportCounts'] = $this->statusCounts(clone $residentReports);
            $data['recentReports'] = $residentReports
                ->with(['category', 'building'])
                ->latest()
                ->limit(5)
                ->get();
            $data['waitingConfirmationCount'] = (clone $residentReports)
                ->where('status', 'waiting_confirmation')
                ->count();
        } elseif ($user->role === 'staff') {
            $assignedReports = Report::query()->whereHas(
                'assignments',
                fn (Builder $assignment) => $assignment
                    ->where('staff_id', $user->id)
                    ->whereNull('unassigned_at'),
            );
            $data['reportCounts'] = $this->statusCounts(clone $assignedReports);
            $data['recentReports'] = $assignedReports
                ->with(['category', 'building'])
                ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->oldest('created_at')
                ->limit(5)
                ->get();
        } else {
            $allReports = Report::query();
            $data['reportCounts'] = $this->statusCounts(clone $allReports);
            $data['priorityReports'] = (clone $allReports)
                ->whereIn('priority', ['critical', 'high'])
                ->where('status', '!=', 'closed')
                ->with(['category', 'building'])
                ->orderByRaw("CASE priority WHEN 'critical' THEN 1 ELSE 2 END")
                ->oldest('created_at')
                ->limit(5)
                ->get();
            $data['triageCount'] = (clone $allReports)->where('status', 'submitted')->count();
            $data['moderationCount'] = \App\Models\ReportModerationReport::query()
                ->where('status', 'open')
                ->count();
        }

        return view('dashboard', $data);
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(Builder $query): array
    {
        return $query
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }
}
