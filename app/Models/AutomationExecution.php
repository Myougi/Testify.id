<?php

namespace App\Models;

use Database\Factories\AutomationExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id', 'automation_run_id', 'test_case_id', 'test_case_automation_id', 'definition_snapshot',
    'status', 'duration_ms', 'failure_type', 'failure_message', 'failed_step',
    'console_excerpt', 'started_at', 'completed_at',
])]
class AutomationExecution extends Model
{
    public const STATUS_QUEUED = 'Queued';

    public const STATUS_RUNNING = 'Running';

    public const STATUS_PASSED = 'Passed';

    public const STATUS_FAILED = 'Failed';

    public const STATUS_BLOCKED = 'Blocked';

    public const STATUS_SKIPPED = 'Skipped';

    /** @use HasFactory<AutomationExecutionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'definition_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AutomationRun, $this> */
    public function automationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class);
    }

    /** @return BelongsTo<TestCase, $this> */
    public function testCase(): BelongsTo
    {
        return $this->belongsTo(TestCase::class);
    }

    /** @return BelongsTo<TestCaseAutomation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(TestCaseAutomation::class, 'test_case_automation_id');
    }

    /** @return HasMany<AutomationArtifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(AutomationArtifact::class);
    }
}
