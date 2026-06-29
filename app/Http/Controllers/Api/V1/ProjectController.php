<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->projects();
        $this->applyFilters($request, $query);
        $projects = $query->cursorPaginate(JsonApi::pageSize($request), cursor: $request->input('page.cursor'));

        return JsonApi::collection($projects, $projects->getCollection()->map(
            fn (Project $project): array => ResourceSerializer::serialize($project),
        )->all());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request): JsonResponse {
            $data = $this->validated($request);
            $project = $request->user()->projects()->create([
                'project_name' => $data['name'],
                'project_description' => $data['description'] ?? null,
                'project_url' => $data['url'] ?? null,
                'project_status' => $data['status'] ?? 'Active',
            ]);
            $this->audit->record($request, 'project.created', 'projects', $project, after: $project->toArray());

            return $this->showResource($project, 201);
        });
    }

    public function show(Request $request, string $project): JsonResponse
    {
        return $this->showResource($this->find($request, $project));
    }

    public function update(Request $request, string $project): JsonResponse
    {
        $model = $this->find($request, $project);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        $data = $this->validated($request, true);
        $before = $model->toArray();
        $model->update(array_filter([
            'project_name' => $data['name'] ?? null,
            'project_description' => array_key_exists('description', $data) ? $data['description'] : null,
            'project_url' => array_key_exists('url', $data) ? $data['url'] : null,
            'project_status' => $data['status'] ?? null,
        ], fn (mixed $value, string $key): bool => array_key_exists(match ($key) {
            'project_name' => 'name',
            'project_description' => 'description',
            'project_url' => 'url',
            default => 'status',
        }, $data), ARRAY_FILTER_USE_BOTH));
        $this->audit->record($request, 'project.updated', 'projects', $model, $before, $model->fresh()->toArray());

        return $this->showResource($model->fresh());
    }

    public function destroy(Request $request, string $project): JsonResponse
    {
        $model = $this->find($request, $project);

        if ($response = $this->precondition($request, $model)) {
            return $response;
        }

        if ($model->testRepositoryModules()->exists()
            || $model->testPlans()->exists()
            || $model->testCases()->exists()
            || $model->issues()->exists()) {
            return JsonApi::error($request, 409, 'resource_has_children', 'Resource Has Children', 'Delete the project’s modules, plans, cases, runs, and issues before deleting the project.');
        }

        $before = $model->toArray();
        $model->delete();
        $this->audit->record($request, 'project.deleted', 'projects', $model, $before);

        return JsonApi::noContent();
    }

    private function find(Request $request, string $id): Project
    {
        return $request->user()->projects()->whereKey($id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $attributes = JsonApi::attributes($request, 'projects');
        $presence = $partial ? 'sometimes' : 'required';

        return validator($attributes, [
            'name' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(['Active', 'In Progress', 'Archived'])],
        ])->validate();
    }

    private function applyFilters(Request $request, mixed $query): void
    {
        if ($search = trim((string) $request->input('filter.search'))) {
            $query->where(function ($inner) use ($search): void {
                $inner->where('project_name', 'like', "%{$search}%")
                    ->orWhere('project_description', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('project_status', $status);
        }

        $sort = (string) $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = match (ltrim($sort, '-')) {
            'name' => 'project_name',
            'status' => 'project_status',
            'updated_at' => 'updated_at',
            default => 'created_at',
        };
        $query->orderBy($column, $direction)->orderBy('project_id');
    }
}
