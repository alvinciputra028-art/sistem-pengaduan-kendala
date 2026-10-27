<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $report = $attachment->report ?: $attachment->comment?->report;

        return $report !== null && $user->can('view', $report);
    }

    public function upload(User $user, Attachment $attachment): bool
    {
        $report = $attachment->report ?: $attachment->comment?->report;

        return $report !== null && $user->can('comment', $report);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->role === 'manager' || $attachment->uploaded_by === $user->id;
    }
}
