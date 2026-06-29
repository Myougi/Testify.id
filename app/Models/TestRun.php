<?php

namespace App\Models;

use Database\Factories\TestRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $test_plan_id
 * @property string $name
 * @property string $environment
 * @property string $priority
 * @property string|null $description
 * @property string $tester_name
 * @property string $status
 * @property Carbon|null $execution_started_at
 * @property Carbon|null $execution_completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'test_plan_id',
    'name',
    'environment',
    'priority',
    'description',
    'tester_name',
    'status',
    'execution_started_at',
    'execution_completed_at',
])]
class TestRun extends Model
{
    public const ENVIRONMENT_DEVELOPMENT = 'Development';

    public const ENVIRONMENT_STAGING = 'Staging';

    public const ENVIRONMENT_UAT = 'UAT';

    public const ENVIRONMENT_PRODUCTION = 'Production';

    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_URGENT = 'Urgent';

    public const STATUS_NEW = 'New';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_COMPLETED = 'Completed';

    public const CASE_STATUS_NOT_RUN = 'Not Run';

    public const CASE_STATUS_PASSED = 'Passed';

    public const CASE_STATUS_FAILED = 'Failed';

    public const CASE_STATUS_IN_TESTING = 'In Testing';

    public const CASE_STATUS_SKIPPED = 'Skipped';

    public const CASE_STATUS_BLOCKED = 'Blocked';

    /** @use HasFactory<TestRunFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'execution_started_at' => 'datetime',
            'execution_completed_at' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function environments(): array
    {
        return [
            self::ENVIRONMENT_DEVELOPMENT,
            self::ENVIRONMENT_STAGING,
            self::ENVIRONMENT_UAT,
            self::ENVIRONMENT_PRODUCTION,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function priorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ];
    }

    public static function normalizeStatus(string $status): string
    {
        return match ($status) {
            'Under Review', 'Rejected', 'Done' => self::STATUS_COMPLETED,
            default => $status,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function caseStatuses(): array
    {
        return [
            self::CASE_STATUS_NOT_RUN,
            self::CASE_STATUS_PASSED,
            self::CASE_STATUS_FAILED,
            self::CASE_STATUS_IN_TESTING,
            self::CASE_STATUS_SKIPPED,
            self::CASE_STATUS_BLOCKED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function completedCaseStatuses(): array
    {
        return [
            self::CASE_STATUS_PASSED,
        ];
    }

    public static function normalizeCaseStatus(string $status): string
    {
        return $status === 'Pending' ? self::CASE_STATUS_NOT_RUN : $status;
    }

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return BelongsToMany<TestCase, $this>
     */
    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class)
            ->withPivot('execution_status')
            ->withTimestamps()
            ->orderBy('test_cases.case_key');
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }
}
