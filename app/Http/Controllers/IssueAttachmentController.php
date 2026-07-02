<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IssueAttachmentController extends Controller
{
    public function __invoke(Issue $issue): StreamedResponse
    {
        Gate::authorize('view', $issue);

        $attachment = $issue->attachment;

        if ($attachment === null) {
            abort(404);
        }

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
            'inline',
        );
    }
}
