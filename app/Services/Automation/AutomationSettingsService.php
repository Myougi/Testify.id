<?php

namespace App\Services\Automation;

use App\Models\Project;
use App\Models\ProjectCredentialProfile;
use App\Models\ProjectEnvironment;
use App\Models\TestRun;
use Illuminate\Support\Facades\DB;

class AutomationSettingsService
{
    /** @return array<string, mixed> */
    public function payload(Project $project): array
    {
        $project->loadMissing(['environments', 'credentialProfiles']);

        return [
            'enabled' => (bool) config('automation.enabled', false),
            'environments' => collect(TestRun::environments())->map(function (string $environment) use ($project): array {
                $configured = $project->environments->firstWhere('environment', $environment);

                if ($configured instanceof ProjectEnvironment) {
                    return [
                        'environment' => $environment,
                        'base_url' => $configured->base_url,
                        'enabled' => $configured->enabled,
                    ];
                }

                return ['environment' => $environment, 'base_url' => '', 'enabled' => false];
            })->all(),
            'credential_profiles' => $project->credentialProfiles
                ->map(fn (ProjectCredentialProfile $profile): array => [
                    'id' => $profile->id,
                    'environment' => $profile->environment,
                    'name' => $profile->name,
                    'key' => $profile->key,
                    'fields' => $profile->fields,
                ])->values()->all(),
        ];
    }

    /**
     * @param  list<array{environment: string, base_url: string, enabled: bool}>  $environments
     * @param  list<array{environment: string, name: string, key: string, fields: list<string>}>  $profiles
     * @return array<string, mixed>
     */
    public function update(Project $project, array $environments, array $profiles): array
    {
        DB::transaction(function () use ($project, $environments, $profiles): void {
            foreach ($environments as $environment) {
                if (trim($environment['base_url']) === '') {
                    $project->environments()->where('environment', $environment['environment'])->delete();

                    continue;
                }

                $project->environments()->updateOrCreate(
                    ['environment' => $environment['environment']],
                    ['base_url' => rtrim($environment['base_url'], '/'), 'enabled' => $environment['enabled']],
                );
            }

            $project->credentialProfiles()->delete();

            foreach ($profiles as $profile) {
                $project->credentialProfiles()->create($profile);
            }
        });

        return $this->payload($project->fresh());
    }
}
