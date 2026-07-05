<?php

namespace App\Models;

use Database\Factories\TestCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $module_id
 * @property string $case_key
 * @property string $title
 * @property string $priority
 * @property string $automation_status
 * @property string $test_behavior
 * @property string $test_type
 * @property string|null $preconditions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Pivot|null $pivot
 */
#[Fillable([
    'project_id',
    'module_id',
    'case_key',
    'title',
    'priority',
    'automation_status',
    'test_behavior',
    'test_type',
    'preconditions',
])]
class TestCase extends Model
{
    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_URGENT = 'Urgent';

    public const AUTOMATION_MANUAL = 'Manual';

    public const AUTOMATION_AUTOMATED = 'Automated';

    public const BEHAVIOR_POSITIVE = 'Positive';

    public const BEHAVIOR_NEGATIVE = 'Negative';

    public const TYPE_ACCEPTANCE = 'Acceptance';

    public const TYPE_ACCESSIBILITY = 'Accessibility';

    public const TYPE_COMPATIBILITY = 'Compatibility';

    public const TYPE_FUNCTIONAL = 'Functional';

    public const TYPE_INTEGRATION = 'Integration';

    public const TYPE_PERFORMANCE = 'Performance';

    public const TYPE_REGRESSION = 'Regression';

    public const TYPE_UI_UX = 'UI/UX';

    public const TYPE_SECURITY = 'Security';

    public const TYPE_SANITY = 'Sanity';

    public const TYPE_SMOKE = 'Smoke';

    /** @use HasFactory<TestCaseFactory> */
    use HasFactory, SoftDeletes;

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
    public static function automationStatuses(): array
    {
        return [
            self::AUTOMATION_MANUAL,
            self::AUTOMATION_AUTOMATED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function behaviors(): array
    {
        return [
            self::BEHAVIOR_POSITIVE,
            self::BEHAVIOR_NEGATIVE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_ACCEPTANCE,
            self::TYPE_ACCESSIBILITY,
            self::TYPE_COMPATIBILITY,
            self::TYPE_FUNCTIONAL,
            self::TYPE_INTEGRATION,
            self::TYPE_PERFORMANCE,
            self::TYPE_REGRESSION,
            self::TYPE_UI_UX,
            self::TYPE_SECURITY,
            self::TYPE_SANITY,
            self::TYPE_SMOKE,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }

    /**
     * @return BelongsTo<TestRepositoryModule, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(TestRepositoryModule::class, 'module_id');
    }

    /**
     * @return HasMany<TestCaseStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(TestCaseStep::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<TestPlan, $this>
     */
    public function testPlans(): BelongsToMany
    {
        return $this->belongsToMany(TestPlan::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<TestRun, $this>
     */
    public function testRuns(): BelongsToMany
    {
        return $this->belongsToMany(TestRun::class)->withTimestamps();
    }

    /**
     * Backward-compatible alias for earlier snake_case relation usage.
     *
     * @return BelongsToMany<TestPlan, $this>
     */
    public function test_plans(): BelongsToMany
    {
        return $this->testPlans();
    }

    /**
     * Backward-compatible alias for earlier snake_case relation usage.
     *
     * @return BelongsToMany<TestRun, $this>
     */
    public function test_runs(): BelongsToMany
    {
        return $this->testRuns();
    }
}
