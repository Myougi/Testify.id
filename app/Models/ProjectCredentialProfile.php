<?php

namespace App\Models;

use Database\Factories\ProjectCredentialProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $environment
 * @property string $name
 * @property string $key
 * @property list<string> $fields
 */
#[Fillable(['project_id', 'environment', 'name', 'key', 'fields'])]
class ProjectCredentialProfile extends Model
{
    /** @use HasFactory<ProjectCredentialProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'project_id');
    }
}
