<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Building;
use App\Models\Report;
use App\Models\ServiceUnit;
use App\Policies\ReportPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = [];
        $query = Report::query()
            ->with(['category', 'building', 'reporter']);

        if (in_array($user->role, ['manager', 'staff'], true)) {
            $filters = $request->validate([
                'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
                'service_unit_id' => ['nullable', 'integer', 'exists:service_units,id'],
                'status' => ['nullable', Rule::in([
                    'submitted', 'triaged', 'assigned', 'in_progress',
                    'waiting_confirmation', 'reopened', 'closed',
                ])],
                'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'critical'])],
                'age' => ['nullable', Rule::in(['under_1_day', '1_to_3_days', 'over_3_days'])],
            ]);

            $query
                ->with(['assignments' => fn ($assignment) => $assignment
                    ->whereNull('unassigned_at')
                    ->with(['serviceUnit', 'staff'])])
                ->when($user->role === 'staff', fn (Builder $query) => $query->whereHas(
                    'assignments',
                    fn (Builder $assignment) => $assignment
                        ->where('staff_id', $user->id)
                        ->whereNull('unassigned_at'),
                ))
                ->when($filters['building_id'] ?? null, fn (Builder $query, int $buildingId) => $query->where('building_id', $buildingId))
                ->when($filters['service_unit_id'] ?? null, fn (Builder $query, int $unitId) => $query->whereHas(
                    'assignments',
                    fn (Builder $assignment) => $assignment
                        ->where('service_unit_id', $unitId)
                        ->whereNull('unassigned_at'),
                ))
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
                ->when($filters['age'] ?? null, function (Builder $query, string $age): void {
                    $query->when($age === 'under_1_day', fn (Builder $query) => $query->where('created_at', '>', now()->subDay()))
                        ->when($age === '1_to_3_days', fn (Builder $query) => $query
                            ->where('created_at', '<=', now()->subDay())
                            ->where('created_at', '>', now()->subDays(3)))
                        ->when($age === 'over_3_days', fn (Builder $query) => $query->where('created_at', '<=', now()->subDays(3)));
                })
                ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
                ->oldest('created_at');
        } else {
            $query = app(ReportPolicy::class)->visibleQuery($user)
                ->with(['category', 'building', 'reporter'])
                ->latest();
        }

        return view('reports.index', [
            'reports' => $query->paginate(15)->withQueryString(),
            'filters' => $filters,
            'buildings' => in_array($user->role, ['manager', 'staff'], true)
                ? Building::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'serviceUnits' => in_array($user->role, ['manager', 'staff'], true)
                ? ServiceUnit::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->role === 'resident', 403);

        $building = $request->user()->building;
        abort_unless($building, 403, 'Akun belum memiliki gedung.');

        return view('reports.create', [
            'building' => $building,
            'room' => $request->user()->room,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'resident', 403);

        $user = $request->user();
        abort_unless($user->building_id, 403, 'Akun belum memiliki gedung.');

        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'location_type' => ['required', Rule::in(['room', 'common_area', 'building'])],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where(
                    fn ($query) => $query->where('building_id', $user->building_id),
                ),
            ],
            'location_description' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['public_building', 'participants'])],
            'identity_visibility' => ['required', Rule::in(['visible', 'anonymous'])],
            'assessment_answers' => ['required', 'array'],
            'assessment_answers.affects_many' => [Rule::in(['yes', 'no', 'uncertain'])],
            'assessment_answers.unusable' => [Rule::in(['yes', 'no', 'uncertain'])],
            'assessment_answers.safety_risk' => [Rule::in(['yes', 'no', 'uncertain'])],
            'assessment_answers.worsening' => [Rule::in(['yes', 'no', 'uncertain'])],
            'assessment_answers.ongoing' => [Rule::in(['yes', 'no', 'uncertain'])],
            'incident_duration' => ['required', Rule::in(['today', '1_to_3_days', '4_to_7_days', 'over_7_days'])],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if ($data['location_type'] === 'room' && ($user->room_id === null || (int) ($data['room_id'] ?? 0) !== $user->room_id)) {
            return back()->withErrors(['room_id' => 'Laporan kamar hanya dapat dibuat untuk kamar Anda sendiri.'])->withInput();
        }
        if ($data['location_type'] !== 'room' && ! empty($data['room_id'])) {
            return back()->withErrors(['room_id' => 'Kamar tidak boleh dipilih untuk lokasi ini.'])->withInput();
        }
        if (in_array($data['location_type'], ['common_area', 'building'], true)
            && blank($data['location_description'] ?? null)) {
            return back()->withErrors(['location_description' => 'Keterangan lokasi wajib diisi.'])->withInput();
        }

        $category = Category::findOrFail($data['category_id']);
        $answers = $data['assessment_answers'];
        $score = ($answers['safety_risk'] === 'yes' ? 10 : ($answers['safety_risk'] === 'uncertain' ? 5 : 0))
            + ($answers['unusable'] === 'yes' ? 5 : ($answers['unusable'] === 'uncertain' ? 2 : 0))
            + ($answers['affects_many'] === 'yes' ? 4 : ($answers['affects_many'] === 'uncertain' ? 2 : 0))
            + ($answers['worsening'] === 'yes' ? 3 : ($answers['worsening'] === 'uncertain' ? 1 : 0))
            + ($answers['ongoing'] === 'yes' ? 2 : 0);
        $priority = match (true) {
            $score >= 25 => 'critical',
            $score >= 17 => 'high',
            $score >= 9 => 'normal',
            default => 'low',
        };

        $report = DB::transaction(function () use ($data, $user, $category, $score, $priority, $answers): Report {
            $report = Report::create([
                'ticket_number' => $this->makeTicketNumber(),
                'reporter_id' => $user->id,
                'building_id' => $user->building_id,
                'room_id' => $user->room_id,
                'category_id' => $category->id,
                'title' => $data['title'],
                'description' => $data['description'],
                'location_type' => $data['location_type'],
                'location_description' => $data['location_type'] === 'room'
                    ? null
                    : ($data['location_description'] ?? null),
                'visibility' => $category->is_sensitive || $data['location_type'] === 'room'
                    ? 'participants'
                    : $data['visibility'],
                'identity_visibility' => $data['identity_visibility'],
                'impact_score' => $answers['affects_many'] === 'yes' ? 2 : 1,
                'urgency_score' => $answers['ongoing'] === 'yes' ? 2 : 1,
                'safety_risk_score' => $answers['safety_risk'] === 'yes' ? 1 : 0,
                'assessment_answers' => $answers,
                'incident_duration' => $data['incident_duration'],
                'priority' => $priority,
                'priority_score' => $score,
                'status' => 'submitted',
            ]);

            $report->statusHistories()->create([
                'to_status' => 'submitted',
                'changed_by' => $user->id,
                'note' => 'Laporan dibuat.',
            ]);
            $report->priorityHistories()->create([
                'new_priority' => $priority,
                'score' => $score,
                'changed_by' => $user->id,
                'reason' => 'Prioritas otomatis saat laporan dibuat.',
            ]);

            foreach ($data['attachments'] ?? [] as $file) {
                $path = $file->store('reports', 'local');
                if ($path === false) {
                    throw new \RuntimeException('Bukti laporan gagal disimpan.');
                }

                $report->attachments()->create([
                    'uploaded_by' => $user->id,
                    'storage_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                ]);
            }

            return $report;
        });

        return redirect()->route('reports.show', $report)->with('success', 'Laporan berhasil dibuat.');
    }

    public function show(Request $request, Report $report): View
    {
        $viewer = $request->user();
        $this->authorize('view', $report);

        $report->load([
            'category',
            'building',
            'room',
            'reporter',
            'attachments',
            'assignments.serviceUnit',
            'assignments.staff',
            'comments' => function ($query) use ($viewer): void {
                if ($viewer->role === 'manager') {
                    $query->withoutGlobalScope('not_deleted');
                }
                $query->with([
                    'user',
                    'attachments',
                    'moderationReports.reportedBy',
                    'moderationReports.reviewedBy',
                ]);
            },
            'statusHistories.changedBy',
        ]);

        $isReporter = $report->reporter_id === $viewer->id;
        $isAssignedStaff = $viewer->role === 'staff'
            && $report->assignments->contains(fn ($assignment): bool => $assignment->staff_id === $viewer->id
                && $assignment->unassigned_at === null);
        $canSeeReporterIdentity = $isReporter
            || $viewer->role === 'manager'
            || $isAssignedStaff
            || ($report->identity_visibility === 'visible' && $report->visibility === 'public_building');
        $canSeeReporterRoom = $viewer->role === 'manager' || $isAssignedStaff || $isReporter;
        $activeAssignment = $report->assignments
            ->first(fn ($assignment): bool => $assignment->unassigned_at === null);
        $canTriage = Gate::allows('triage', $report);
        $serviceUnits = collect();
        $recommendedUnit = null;
        $availableTransitions = [];
        if ($canTriage) {
            $serviceUnits = ServiceUnit::query()
                ->where('is_active', true)
                ->whereHas('buildings', fn (Builder $query) => $query->whereKey($report->building_id))
                ->with(['staff' => fn ($query) => $query
                    ->where('users.is_active', true)
                    ->where('users.role', 'staff')
                    ->whereHas('buildings', fn (Builder $building) => $building->whereKey($report->building_id))])
                ->orderBy('name')
                ->get();
            $recommendedUnit = $serviceUnits->first(
                fn (ServiceUnit $unit): bool => $unit->id === $report->category->default_service_unit_id,
            );
            $availableTransitions = [
                'submitted' => ['triaged', 'assigned'],
                'triaged' => ['assigned', 'in_progress'],
                'assigned' => ['in_progress'],
                'in_progress' => ['waiting_confirmation'],
                'waiting_confirmation' => [],
                'reopened' => ['triaged', 'assigned', 'in_progress'],
                'closed' => ['reopened'],
            ][$report->status] ?? [];
        }

        return view('reports.show', compact(
            'report',
            'canSeeReporterIdentity',
            'canSeeReporterRoom',
            'activeAssignment',
            'canTriage',
            'serviceUnits',
            'recommendedUnit',
            'availableTransitions',
        ));
    }

    private function makeTicketNumber(): string
    {
        do {
            $number = 'LAP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Report::where('ticket_number', $number)->exists());

        return $number;
    }
}
