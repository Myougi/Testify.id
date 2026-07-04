<?php

namespace App\Models;

use Database\Factories\AutomationHealingAttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $test_case_automation_id
 * @property string $source_execution_id
 * @property string|null $revalidation_run_id
 * @property string $status
 * @property int $failed_step
 * @property array<string, mixed>|null $original_definition
 * @property array<string, mixed>|null $proposed_definition
 * @property string|null $rationale
 * @property int|null $confidence
 * @property string $source_hash
 * @property string|null $failure_message
 */
#[Fillable([
    'id', 'test_case_automation_id', 'source_execution_id', 'revalidation_run_id',
    'status', 'failed_step', 'original_definition', 'proposed_definition', 'rationale',
    'confidence', 'source_hash', 'failure_message', 'completed_at',
])]
class AutomationHealingAttempt extends Model
{
    public const STATUS_HEALING = 'Healing';

    public const STATUS_VALIDATING = 'Validating';

    public const STATUS_APPLIED = 'Applied';

    public const STATUS_FAILED = 'Failed';

    public const STATUS_CANCELLED = 'Cancelled';

    /** @use HasFactory<AutomationHealingAttemptFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'original_definition' => 'array',
            'proposed_definition' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TestCaseAutomation, $this> */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(TestCaseAutomation::class, 'test_case_automation_id');
    }

    /** @return BelongsTo<AutomationExecution, $this> */
    public function sourceExecution(): BelongsTo
    {
        return $this->belongsTo(AutomationExecution::class, 'source_execution_id');
    }

    /** @return BelongsTo<AutomationRun, $this> */
    public function revalidationRun(): BelongsTo
    {
        return $this->belongsTo(AutomationRun::class, 'revalidation_run_id');
    }
}
