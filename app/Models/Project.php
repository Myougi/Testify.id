<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team_accesses', 'project_id', 'team_id', 'project_id')
            ->withPivot('id', 'granted_by_user_id')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $accessQuery) use ($user): void {
            $accessQuery
                ->where('user_id', $user->id)
                ->orWhereIn('project_id', ProjectTeamAccess::query()
                    ->select('project_id')
                    ->whereIn('team_id', TeamMember::query()
                        ->select('team_id')
                        ->where('user_id', $user->id)));
        })->distinct();
    }
}
