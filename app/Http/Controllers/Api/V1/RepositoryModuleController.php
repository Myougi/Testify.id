<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TestRepositoryModule;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\TestRepositoryService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepositoryModuleController extends Controller
{
    public function __construct(
        AuditService $audit,
        IdempotencyService $idempotency,
        private readonly TestRepositoryService $repositoryService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request): JsonResponse
    {
        $query = TestRepositoryModule::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'));

        if ($project = $request->input('filter.project')) {
            $query->where('project_id', $project);
        }

        if ($parent = $request->input('filter.parent')) {
            $query->where('parent_id', $parent);
        }

        $modules = $query->orderBy('sort_order')->orderBy('id')
            ->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($modules, $modules->getCollection()->map(
            fn (TestRepositoryModule $module): array => ResourceSerializer::serialize($module),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            $data = $this->validated($request);
            $module = $this->repositoryService->storeModule($data);
            $this->audit->record($request, 'repository_module.created', 'repository-modules', $module, after: $module->toArray());

            return $this->showResource($module, 201);
        });
    }

    public function show(Request $request, string $repositoryModule): JsonResponse
    {
        return $this->showResource($this->find($request, $repositoryModule));
    }

    public function update(Request $request, string $repositoryModule): JsonResponse
    {
        $module = $this->find($request, $repositoryModule);

        if ($response = $this->precondition($request, $module)) {
            return $response;
        }

        $before = $module->toArray();
        $updated = $this->repositoryService->updateModule($module, $this->validated($request, true, $module));
        $this->audit->record($request, 'repository_module.updated', 'repository-modules', $updated, $before, $updated->toArray());

        return $this->showResource($updated);
    }

    public function destroy(Request $request, string $repositoryModule): JsonResponse
    {
        $module = $this->find($request, $repositoryModule);

        if ($response = $this->precondition($request, $module)) {
            return $response;
        }

        $before = $module->toArray();
        $this->repositoryService->deleteModule($module);
        $this->audit->record($request, 'repository_module.deleted', 'repository-modules', $module, $before);

        return JsonApi::noContent();
    }

    private function find(Request $request, string $id): TestRepositoryModule
    {
        return TestRepositoryModule::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'))
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false, ?TestRepositoryModule $module = null): array
    {
        $attributes = JsonApi::attributes($request, 'repository-modules');
        $projectId = (int) data_get(JsonApi::relationship($request, 'project'), 'id', $module?->project_id);
        $parentId = data_get(JsonApi::relationship($request, 'parent'), 'id', $module?->parent_id);
        $presence = $partial ? 'sometimes' : 'required';
        $data = validator([
            ...$attributes,
            'project_id' => $projectId,
            'parent_id' => $parentId,
        ], [
            'project_id' => [
                $presence,
                'integer',
                Rule::exists('projects', 'project_id')->whereNull('deleted_at'),
            ],
            'parent_id' => ['nullable', 'integer', 'different:id', Rule::exists('test_repository_modules', 'id')->where('project_id', $projectId)->whereNull('deleted_at')],
            'name' => [$presence, 'string', 'max:255'],
            'key_prefix' => [$presence, 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
        ])->validate();

        if ($partial) {
            $data = array_intersect_key($data, array_flip([
                ...array_keys($attributes),
                ...($request->has('data.relationships.project') ? ['project_id'] : []),
                ...($request->has('data.relationships.parent') ? ['parent_id'] : []),
            ]));
        }

        return $data;
    }
}
