<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $project_id
 * @property string $project_name
 * @property string|null $project_description
 * @property string|null $project_url
 * @property string $project_status
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $test_cases_count
 * @property int|null $test_plans_count
 * @property int|null $active_test_plans_count
 * @property int|null $completed_test_plans_count
 * @property int|null $execution_progress
 * @property int|null $open_issues_count
 */
#[Fillable(['project_name', 'project_description', 'project_url', 'project_status'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'project_id';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TestPlan, $this>
     */
    public function testPlans(): HasMany
    {
        return $this->hasMany(TestPlan::class, 'project_id', 'project_id');
    }

    /**
     * @return HasMany<TestRepositoryModule, $this>
     */
    public function testRepositoryModules(): HasMany
    {
        return $this->hasMany(TestRepositoryModule::class, 'project_id', 'project_id');
    }

    /**
     * @return HasMany<TestCase, $this>
     */
    public function testCases(): HasMany
    {
        return $this->hasMany(TestCase::class, 'project_id', 'project_id');
    }

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class, 'project_id', 'project_id');
    }
}
