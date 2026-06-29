<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTestRepositoryModuleRequest;
use App\Http\Requests\UpdateTestRepositoryModuleRequest;
use App\Http\Resources\TestRepositoryModuleResource;
use App\Models\TestRepositoryModule;
use App\Services\TestRepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TestRepositoryModuleController extends Controller
{
    public function __construct(
        public TestRepositoryService $testRepositoryService,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): mixed
    {
        Gate::authorize('viewAny', TestRepositoryModule::class);

        return TestRepositoryModuleResource::collection(
            $this->testRepositoryService->getModulesForUser($request->user()),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTestRepositoryModuleRequest $request): JsonResponse
    {
        return (new TestRepositoryModuleResource(
            $this->testRepositoryService->storeModule($request->validated()),
        ))->response()->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTestRepositoryModuleRequest $request, TestRepositoryModule $module): TestRepositoryModuleResource
    {
        return new TestRepositoryModuleResource(
            $this->testRepositoryService->updateModule($module, $request->validated()),
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TestRepositoryModule $module): JsonResponse
    {
        Gate::authorize('delete', $module);

        $this->testRepositoryService->deleteModule($module);

        return response()->json([
            'message' => 'Repository folder deleted.',
        ]);
    }
}
