<?php

namespace App\Support\Api\V1;

use App\Models\Issue;
use App\Models\Project;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestPlan;
use App\Models\TestRepositoryModule;
use App\Models\TestRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken;

class ResourceSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(Model $model): array
    {
        return match (true) {
            $model instanceof Project => self::project($model),
            $model instanceof TestRepositoryModule => self::module($model),
            $model instanceof TestPlan => self::testPlan($model),
            $model instanceof TestCase => self::testCase($model),
            $model instanceof TestCaseStep => self::step($model),
            $model instanceof TestRun => self::testRun($model),
            $model instanceof Issue => self::issue($model),
            $model instanceof User => self::user($model),
            $model instanceof PersonalAccessToken => self::token($model),
            default => throw new \InvalidArgumentException('Unsupported API resource: '.$model::class),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function project(Project $project): array
    {
        return self::resource('projects', $project, [
            'name' => $project->project_name,
            'description' => $project->project_description,
            'url' => $project->project_url,
            'status' => $project->project_status,
            ...self::timestamps($project),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function module(TestRepositoryModule $module): array
    {
        return self::resource('repository-modules', $module, [
            'name' => $module->name,
            'key_prefix' => $module->key_prefix,
            'sort_order' => $module->sort_order,
            ...self::timestamps($module),
        ], [
            'project' => self::linkage('projects', $module->project_id),
            'parent' => self::nullableLinkage('repository-modules', $module->parent_id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function testPlan(TestPlan $testPlan): array
    {
        return self::resource('test-plans', $testPlan, [
            'name' => $testPlan->name,
            'description' => $testPlan->description,
            'scope' => $testPlan->scope,
            'strategy' => $testPlan->strategy,
            'criteria' => $testPlan->criteria,
            'status' => $testPlan->status,
            'priority' => $testPlan->priority,
            'start_date' => $testPlan->start_date?->toDateString(),
            'due_date' => $testPlan->due_date?->toDateString(),
            'release_version' => $testPlan->release_version,
            'tester' => $testPlan->tester,
            ...self::timestamps($testPlan),
        ], ['project' => self::linkage('projects', $testPlan->project_id)]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function testCase(TestCase $testCase): array
    {
        $relationships = [
            'project' => self::linkage('projects', $testCase->project_id),
            'module' => self::linkage('repository-modules', $testCase->module_id),
        ];

        if ($testCase->relationLoaded('steps')) {
            $relationships['steps'] = [
                'data' => $testCase->steps
                    ->map(fn (TestCaseStep $step): array => ['type' => 'test-case-steps', 'id' => (string) $step->id])
                    ->values()
                    ->all(),
            ];
        }

        return self::resource('test-cases', $testCase, [
            'case_key' => $testCase->case_key,
            'title' => $testCase->title,
            'priority' => $testCase->priority,
            'automation_status' => $testCase->automation_status,
            'test_behavior' => $testCase->test_behavior,
            'test_type' => $testCase->test_type,
            'preconditions' => $testCase->preconditions,
            ...self::timestamps($testCase),
        ], $relationships);
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(TestCaseStep $step): array
    {
        return self::resource('test-case-steps', $step, [
            'step_number' => $step->position,
            'action' => $step->action,
            'expected_result' => $step->expected_result,
            ...self::timestamps($step),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function testRun(TestRun $testRun): array
    {
        $relationships = ['test_plan' => self::linkage('test-plans', $testRun->test_plan_id)];

        if ($testRun->relationLoaded('testCases')) {
            $testCaseLinkages = [];

            foreach ($testRun->testCases as $testCase) {
                $pivot = $testCase->getRelationValue('pivot');
                $testCaseLinkages[] = [
                    'type' => 'test-cases',
                    'id' => (string) $testCase->id,
                    'meta' => [
                        'execution_status' => $pivot instanceof \Illuminate\Database\Eloquent\Relations\Pivot
                            ? $pivot->getAttribute('execution_status')
                            : null,
                    ],
                ];
            }

            $relationships['test_cases'] = [
                'data' => $testCaseLinkages,
            ];
        }

        return self::resource('test-runs', $testRun, [
            'name' => $testRun->name,
            'environment' => $testRun->environment,
            'priority' => $testRun->priority,
            'description' => $testRun->description,
            'tester_name' => $testRun->tester_name,
            'status' => $testRun->status,
            'execution_started_at' => self::dateTime($testRun->execution_started_at),
            'execution_completed_at' => self::dateTime($testRun->execution_completed_at),
            ...self::timestamps($testRun),
        ], $relationships);
    }

    /**
     * @return array<string, mixed>
     */
    private static function issue(Issue $issue): array
    {
        return self::resource('issues', $issue, [
            'title' => $issue->title,
            'description' => $issue->description,
            'expected_result' => $issue->expected_result,
            'actual_result' => $issue->actual_result,
            'priority' => $issue->priority,
            'severity' => $issue->severity,
            'status' => $issue->status,
            'reporter' => $issue->reporter,
            'assignee' => $issue->assignee,
            'environment' => $issue->environment,
            'defect_type' => $issue->defect_type,
            'closed_at' => self::dateTime($issue->closed_at),
            ...self::timestamps($issue),
        ], [
            'project' => self::linkage('projects', $issue->project_id),
            'test_plan' => self::nullableLinkage('test-plans', $issue->test_plan_id),
            'test_run' => self::nullableLinkage('test-runs', $issue->test_run_id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function user(User $user): array
    {
        return self::resource('users', $user, [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => self::dateTime($user->email_verified_at),
            ...self::timestamps($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function token(PersonalAccessToken $token): array
    {
        $resource = self::resource('api-tokens', $token, [
            'name' => $token->name,
            'abilities' => $token->abilities ?? [],
            'last_used_at' => self::dateTime($token->last_used_at),
            'expires_at' => self::dateTime($token->expires_at),
            ...self::timestamps($token),
        ]);
        $resource['meta'] = ['etag' => JsonApi::etag($token)];

        return $resource;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $relationships
     * @return array<string, mixed>
     */
    private static function resource(string $type, Model $model, array $attributes, array $relationships = []): array
    {
        $resource = [
            'type' => $type,
            'id' => (string) $model->getKey(),
            'attributes' => $attributes,
        ];

        if ($relationships !== []) {
            $resource['relationships'] = $relationships;
        }

        return $resource;
    }

    /**
     * @return array{created_at: ?string, updated_at: ?string}
     */
    private static function timestamps(Model $model): array
    {
        return [
            'created_at' => self::dateTime($model->getAttribute('created_at')),
            'updated_at' => self::dateTime($model->getAttribute('updated_at')),
        ];
    }

    /**
     * @return array{data: array{type: string, id: string}}
     */
    private static function linkage(string $type, int|string $id): array
    {
        return ['data' => ['type' => $type, 'id' => (string) $id]];
    }

    /**
     * @return array{data: array{type: string, id: string}|null}
     */
    private static function nullableLinkage(string $type, int|string|null $id): array
    {
        return ['data' => $id === null ? null : ['type' => $type, 'id' => (string) $id]];
    }

    private static function dateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->utc()->toIso8601String();
    }
}
