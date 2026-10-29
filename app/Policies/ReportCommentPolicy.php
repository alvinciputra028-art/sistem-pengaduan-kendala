<?php

namespace App\Policies;

use App\Models\ReportComment;
use App\Models\User;

class ReportCommentPolicy
{
    public function view(User $user, ReportComment $comment): bool
    {
        return $user->can('view', $comment->report);
    }

    public function create(User $user, ReportComment $comment): bool
    {
        return $user->can('comment', $comment->report);
    }

    public function delete(User $user, ReportComment $comment): bool
    {
        return $user->role === 'manager' || $comment->user_id === $user->id;
    }

    public function report(User $user, ReportComment $comment): bool
    {
        $report = $comment->report;
        $communityDisabledForOtherResident = $user->role === 'resident'
            && $report->visibility === 'public_building'
            && $report->community_chat_disabled_at !== null
            && $report->reporter_id !== $user->id;

        return in_array($user->role, ['resident', 'staff'], true)
            && $comment->user_id !== $user->id
            && $this->view($user, $comment)
            && ! $communityDisabledForOtherResident;
    }

    public function moderate(User $user, ReportComment $comment): bool
    {
        return $user->role === 'manager';
    }
}
