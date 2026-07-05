<?php

namespace App\Models;

use Database\Factories\AutomationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $kind
 * @property int|null $test_run_id
 * @property int|null $requested_by
 * @property string $environment
 * @property string $base_url
 * @property int $attempt
 * @property string $provider
 * @property string|null $provider_run_id
 * @property string|null $provider_run_url
 * @property string $status
 * @property string|null $failure_message
 * @property string|null $goal_prompt
 * @property string|null $phase
 * @property string|null $progress_message
 * @property string|null $generation_source
 * @property string|null $model
 * @property list<string>|null $warnings
 * @property string|null $failure_code
 * @property string|null $source_hash
 * @property string|null $parent_run_id
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
#[Fillable([
    'id', 'kind', 'test_run_id', 'requested_by', 'environment', 'base_url', 'attempt',
    'provider', 'provider_run_id', 'provider_run_url', 'status', 'failure_message',
    'goal_prompt', 'phase', 'progress_message', 'generation_source', 'model', 'warnings',
    'failure_code', 'source_hash', 'parent_run_id',
    'dispatched_at', 'started_at', 'completed_at',
])]
class AutomationRun extends Model
{
    public const KIND_VALIDATION = 'Validation';

    public const KIND_EXECUTION = 'Execution';

    public const KIND_GENERATION = 'Generation';

    public const PHASE_STATIC = 'Generating static definition';

    public const PHASE_NEEDS_GROUNDING = 'Static generation needs browser grounding';

    public const PHASE_STARTING_BROWSER = 'Starting browser exploration';

    public const PHASE_EXPLORING = 'Exploring browser';

    public const PHASE_COMPILING = 'Compiling recorded actions';

    public const PHASE_DRAFT_RECORDED = 'Draft - Browser Recorded';

    public const PHASE_FAILED = 'Generation failed';

    public const SOURCE_STATIC = 'Static AI';

    public const SOURCE_AGENTIC = 'Agentic Browser';

    public const STATUS_QUEUED = 'Queued';

    public const STATUS_DISPATCHING = 'Dispatching';

    public const STATUS_RUNNING = 'Running';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_FAILED = 'Failed';

    public const STATUS_ERROR = 'Error';

    public const STATUS_CANCELLED = 'Cancelled';

    /** @use HasFactory<AutomationRunFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'warnings' => 'array',
        ];
    }

    /** @return BelongsTo<TestRun, $this> */
    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TestRun::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<AutomationExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(AutomationExecution::class);
    }
}
