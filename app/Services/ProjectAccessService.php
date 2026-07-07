<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectAccessService
{
    /**
     * @return Builder<Project>
     */
    public function accessibleProjectsQuery(User $user): Builder
    {
        return Project::query()->accessibleTo($user);
    }

    /**
     * @return Builder<Project>
     */
    public function ownedProjectsQuery(User $user): Builder
    {
        return $user->projects();
    }

    public function resolveAccessibleProject(?User $user, mixed $projectId): ?Project
    {
        if (! $user instanceof User || ! is_numeric($projectId)) {
            return null;
        }

        return $this->accessibleProjectsQuery($user)
            ->whereKey((int) $projectId)
            ->first();
    }
}
