<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReportPolicy
{
    public function visibleQuery(User $user): Builder
    {
        return Report::query()->where(function (Builder $query) use ($user): void {
            if ($user->role === 'manager') {
                return;
            }

            if ($user->role === 'staff') {
                $query->whereHas('assignments', fn (Builder $assignment) => $assignment
                    ->where('staff_id', $user->id)
                    ->whereNull('unassigned_at'));
                return;
            }

            $query->where('reporter_id', $user->id);
        });
    }

    public function view(User $user, Report $report): bool
    {
        if ($user->role === 'manager' || $report->reporter_id === $user->id) {
            return true;
        }

        if ($user->role === 'staff') {
            return $this->isAssignedStaff($user, $report);
        }

        return $report->visibility === 'public_building'
            && $report->building_id === $user->building_id;
    }

    public function triage(User $user, Report $report): bool
    {
        return $report->status !== 'closed'
            && ($user->role === 'manager' || $this->isAssignedStaff($user, $report));
    }

    public function updatePriority(User $user, Report $report): bool
    {
        return $report->status !== 'waiting_confirmation'
            && $this->triage($user, $report);
    }

    public function comment(User $user, Report $report): bool
    {
        $isCommunityParticipant = $user->role !== 'resident'
            || $report->reporter_id === $user->id
            || ($report->visibility === 'public_building'
                && $report->acknowledgements()->where('user_id', $user->id)->exists());

        $communityChatDisabledForResident = $user->role === 'resident'
            && $report->visibility === 'public_building'
            && $report->community_chat_disabled_at !== null
            && $report->reporter_id !== $user->id;

        return $this->view($user, $report)
            && $isCommunityParticipant
            && $report->status !== 'closed'
            && ! $communityChatDisabledForResident;
    }

    public function close(User $user, Report $report): bool
    {
        return ($user->role === 'manager' || $report->reporter_id === $user->id)
            && $report->status === 'waiting_confirmation';
    }

    public function reopen(User $user, Report $report): bool
    {
        return ($user->role === 'manager' || $report->reporter_id === $user->id)
            && $report->status === 'closed';
    }

    private function isAssignedStaff(User $user, Report $report): bool
    {
        return $user->role === 'staff'
            && $report->assignments()
                ->where('staff_id', $user->id)
                ->whereNull('unassigned_at')
                ->exists();
    }
}
