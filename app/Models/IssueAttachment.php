<?php

namespace App\Models;

use Database\Factories\IssueAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'issue_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class IssueAttachment extends Model
{
    /** @use HasFactory<IssueAttachmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
