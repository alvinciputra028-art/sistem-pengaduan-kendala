<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportAcknowledgement;
use App\Models\ReportModerationReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->role === 'resident' && $user->building_id, 403);

        $reports = Report::query()
            ->where('visibility', 'public_building')
            ->whereNull('community_hidden_at')
            ->whereNull('duplicate_of_report_id')
            ->whereIn('status', ['submitted', 'triaged', 'assigned', 'in_progress', 'waiting_confirmation', 'reopened'])
            ->where('building_id', $user->building_id)
            ->with(['category', 'building', 'reporter', 'acknowledgements'])
            ->withCount('acknowledgements')
            ->latest()
            ->paginate(12);

        return view('community.index', compact('reports'));
    }

    public function moderation(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        return redirect()->route('reports.index');
    }

    public function acknowledge(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'resident', 403);
        $this->authorizeCommunity($request, $report);
        abort_unless($report->reporter_id !== $request->user()->id, 422, 'Pelapor tidak dapat menandai laporannya sendiri.');
        ReportAcknowledgement::firstOrCreate([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Laporan ditandai sebagai kendala yang juga Anda alami.');
    }

    public function report(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeCommunityAction($request, $report);
        $data = $request->validate([
            'reason' => ['required', Rule::in(['inappropriate', 'personal_data', 'duplicate', 'other'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        ReportModerationReport::updateOrCreate(
            ['report_id' => $report->id, 'reported_by' => $request->user()->id],
            [
                'reason' => $data['reason'],
                'note' => $data['note'] ?? null,
                'status' => 'open',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        $this->notifyManagers($report, $request->user(), 'Laporan komunitas dilaporkan.');

        return back()->with('success', 'Laporan komunitas dikirim untuk ditinjau.');
    }

    public function duplicate(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeCommunityAction($request, $report);
        ReportModerationReport::updateOrCreate([
            'report_id' => $report->id,
            'reported_by' => $request->user()->id,
        ], [
            'reason' => 'duplicate',
            'note' => 'Ditandai sebagai duplikat dari feed komunitas.',
            'status' => 'open',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $this->notifyManagers($report, $request->user(), 'Laporan ditandai sebagai duplikat.');

        return back()->with('success', 'Laporan ditandai untuk ditinjau sebagai duplikat.');
    }

    public function hide(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        $report->update(['community_hidden_at' => now()]);
        $report->moderationReports()->where('status', 'open')->update([
            'status' => 'resolved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Laporan disembunyikan dari feed komunitas.');
    }

    public function restore(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        $report->update(['community_hidden_at' => null]);
        $report->moderationReports()
            ->where('status', 'resolved')
            ->where('reason', '!=', 'duplicate')
            ->update([
                'status' => 'open',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

        return back()->with('success', 'Laporan ditampilkan kembali di feed komunitas.');
    }

    public function cancelDuplicate(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        abort_unless($report->duplicate_of_report_id !== null, 422, 'Laporan ini tidak berstatus duplikat.');

        $report->update([
            'duplicate_of_report_id' => null,
            'community_hidden_at' => null,
        ]);

        return back()->with('success', 'Status duplikat dibatalkan dan laporan dipulihkan sebagai laporan mandiri.');
    }

    public function review(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->role === 'manager', 403);
        $data = $request->validate([
            'action' => ['required', Rule::in(['dismiss_duplicate', 'confirm_duplicate'])],
            'duplicate_of_report_id' => ['nullable', 'integer', Rule::exists('reports', 'id')->where(
                fn ($query) => $query
                    ->where('visibility', 'public_building')
                    ->where('building_id', $report->building_id)
                    ->where('id', '!=', $report->id),
            )],
        ]);
        abort_if(
            $data['action'] === 'confirm_duplicate' && empty($data['duplicate_of_report_id']),
            422,
            'Pilih laporan utama untuk mengonfirmasi duplikat.',
        );

        $moderation = $report->moderationReports()
            ->where('reason', 'duplicate')
            ->where('status', 'open')
            ->latest()
            ->firstOrFail();
        $moderation->update([
            'status' => 'resolved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($data['action'] === 'confirm_duplicate') {
            $report->update([
                'duplicate_of_report_id' => $data['duplicate_of_report_id'],
                'community_hidden_at' => now(),
            ]);
        }

        return back()->with('success', 'Moderasi duplikat berhasil diperbarui.');
    }

    private function authorizeCommunity(Request $request, Report $report): void
    {
        abort_unless(
            $report->visibility === 'public_building'
                && $report->community_hidden_at === null
                && (
                    $request->user()->role === 'manager'
                    || $report->building_id === $request->user()->building_id
                    || ($request->user()->role === 'staff'
                        && $request->user()->buildings()->whereKey($report->building_id)->exists())
                ),
            403,
        );
    }

    private function authorizeCommunityAction(Request $request, Report $report): void
    {
        $user = $request->user();
        abort_unless($report->reporter_id !== $user->id, 422, 'Anda tidak dapat menandai laporan sendiri.');

        if ($user->role === 'resident') {
            $this->authorizeCommunity($request, $report);
            return;
        }

        abort_unless($user->role === 'staff', 403);
        $this->authorize('view', $report);
    }

    private function notifyManagers(Report $report, User $actor, string $action): void
    {
        $message = $action.' Pelapor: '.$report->reporter->name
            .'. Pengaju: '.$actor->name.'. Tiket: '.$report->ticket_number.'.';

        foreach (User::query()->where('role', 'manager')->where('is_active', true)->get() as $manager) {
            Notification::create([
                'user_id' => $manager->id,
                'type' => 'report.community_reported',
                'data' => [
                    'report_id' => $report->id,
                    'reported_by' => $actor->id,
                    'reporter_id' => $report->reporter_id,
                    'ticket_number' => $report->ticket_number,
                    'message' => $message,
                ],
            ]);
        }
    }
}
