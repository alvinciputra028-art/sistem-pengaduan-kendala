<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportCommentController extends Controller
{
    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('comment', $report);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = new ReportComment($data);
        $comment->user_id = $request->user()->id;
        $report->comments()->save($comment);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Komentar berhasil ditambahkan.');
    }
}
