<?php

namespace App\Models;

use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $test_plan_id
 * @property int|null $test_run_id
 * @property string|null $title
 * @property string $description
 * @property string|null $expected_result
 * @property string|null $actual_result
 * @property string $priority
 * @property string $severity
 * @property string $status
 * @property string $reporter
 * @property string|null $assignee
 * @property string|null $environment
 * @property string|null $defect_type
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id',
    'test_plan_id',
    'test_run_id',
    'title',
    'description',
    'expected_result',
    'actual_result',
    'priority',
    'severity',
    'status',
    'reporter',
    'assignee',
    'environment',
    'defect_type',
    'closed_at',
])]
class Issue extends Model
{
    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_URGENT = 'Urgent';

    public const SEVERITY_LOW = 'Low';

    public const SEVERITY_MEDIUM = 'Medium';

    public const SEVERITY_HIGH = 'High';

    public const SEVERITY_CRITICAL = 'Critical';

    public const STATUS_OPEN = 'Open';

    public const STATUS_IN_INVESTIGATION = 'In Investigation';

    public const STATUS_IN_TESTING = 'In Testing';

    public const STATUS_SOLVED = 'Solved';

    public const STATUS_DEFERRED = 'Deferred';

    public const ENVIRONMENT_DEVELOPMENT = 'Development';

    public const ENVIRONMENT_SIT = 'SIT';

    public const ENVIRONMENT_STAGING = 'Staging';

    public const ENVIRONMENT_UAT = 'UAT';

    public const ENVIRONMENT_PRODUCTION = 'Production';

    public const DEFECT_TYPE_UI_UX = 'UI/UX';

    public const DEFECT_TYPE_FUNCTIONAL = 'Functional';

    public const DEFECT_TYPE_COMPATIBILITY = 'Compatibility';

    public const DEFECT_TYPE_DATABASE = 'Database';

    public const DEFECT_TYPE_INTEGRATION = 'Integration';

    public const DEFECT_TYPE_PERFORMANCE = 'Performance';

    public const DEFECT_TYPE_SECURITY = 'Security';

    public const DEFECT_TYPE_USABILITY = 'Usability';

    /** @use HasFactory<IssueFactory> */
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
    public static function severities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_IN_INVESTIGATION,
            self::STATUS_IN_TESTING,
            self::STATUS_SOLVED,
            self::STATUS_DEFERRED,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function environments(): array
    {
        return [
            self::ENVIRONMENT_DEVELOPMENT,
            self::ENVIRONMENT_SIT,
            self::ENVIRONMENT_STAGING,
            self::ENVIRONMENT_UAT,
            self::ENVIRONMENT_PRODUCTION,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defectTypes(): array
    {
        return [
            self::DEFECT_TYPE_UI_UX,
            self::DEFECT_TYPE_FUNCTIONAL,
            self::DEFECT_TYPE_COMPATIBILITY,
            self::DEFECT_TYPE_DATABASE,
            self::DEFECT_TYPE_INTEGRATION,
            self::DEFECT_TYPE_PERFORMANCE,
            self::DEFECT_TYPE_SECURITY,
            self::DEFECT_TYPE_USABILITY,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
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
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return BelongsTo<TestRun, $this>
     */
    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TestRun::class);
    }
}
