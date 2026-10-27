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
}
