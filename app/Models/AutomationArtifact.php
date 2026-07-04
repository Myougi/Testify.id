<?php

namespace App\Models;

use Database\Factories\AutomationArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $automation_execution_id
 * @property string $disk
 * @property string $path
 * @property string $sha256
 * @property int $size
 * @property Carbon|null $uploaded_at
 */
#[Fillable([
    'automation_execution_id', 'type', 'disk', 'path', 'original_name',
    'mime_type', 'size', 'sha256', 'uploaded_at',
])]
class AutomationArtifact extends Model
{
    public const TYPE_SCREENSHOT = 'Screenshot';

    public const TYPE_TRACE = 'Trace';

    public const TYPE_LOG = 'Log';

    /** @use HasFactory<AutomationArtifactFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    /** @return BelongsTo<AutomationExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(AutomationExecution::class, 'automation_execution_id');
    }
}
