<?php

namespace App\Models;

use Database\Factories\TestPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $description
 * @property string|null $scope
 * @property string|null $strategy
 * @property string|null $criteria
 * @property string $status
 * @property string $priority
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property string|null $release_version
 * @property string|null $tester
 * @property Carbon|null $created_at
     * @property Carbon|null $updated_at
 */
#[Fillable([
    'project_id',
    'name',
    'description',
    'scope',
    'strategy',
    'criteria',
    'status',
    'priority',
    'start_date',
    'due_date',
    'release_version',
    'tester',
])]
class TestPlan extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_IN_PROGRESS = 'In Progress';

    public const STATUS_COMPLETED = 'Completed';

    public const STATUS_ARCHIVED = 'Archived';

    public const PRIORITY_LOW = 'Low';

    public const PRIORITY_MEDIUM = 'Medium';

    public const PRIORITY_HIGH = 'High';

    public const PRIORITY_URGENT = 'Urgent';

    /** @use HasFactory<TestPlanFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ARCHIVED,
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
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
     * @return BelongsToMany<TestCase, $this>
     */
    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class)
            ->withTimestamps()
            ->orderBy('test_cases.case_key');
    }

    /**
     * @return HasMany<TestRun, $this>
     */
    public function testRuns(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }
}
