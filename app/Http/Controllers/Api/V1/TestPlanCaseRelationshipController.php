<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\TestCase;
use App\Models\TestPlan;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Services\TestPlanService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestPlanCaseRelationshipController extends Controller
{
    public function __construct(
        AuditService $audit,
        IdempotencyService $idempotency,
        private readonly TestPlanService $testPlanService,
    ) {
        parent::__construct($audit, $idempotency);
    }

    public function index(Request $request, string $testPlan): JsonResponse
    {
        $plan = $this->findPlan($request, $testPlan);
        $cases = $plan->testCases()->cursorPaginate(
            JsonApi::pageSize($request),
            cursor: $request->input('page.cursor'),
        );

        $resources = [];

        foreach ($cases->items() as $testCase) {
            $resources[] = ResourceSerializer::serialize($testCase);
        }

        return JsonApi::collection($cases, $resources);
    }

    public function store(Request $request, string $testPlan): JsonResponse
    {
        return $this->idempotency->execute($request, function () use ($request, $testPlan): JsonResponse {
            $plan = $this->findPlan($request, $testPlan);

            if ($response = $this->precondition($request, $plan)) {
                return $response;
            }

            $linkageInput = $request->input('data');
            $ids = validator(['data' => is_array($linkageInput) ? $linkageInput : null], [
                'data' => ['required', 'array', 'min:1'],
                'data.*.type' => ['required', 'in:test-cases'],
                'data.*.id' => ['required', 'integer', 'distinct'],
            ])->validate()['data'];
            $updated = $this->testPlanService->attachTestCases($plan, array_map(
                fn (array $linkage): int => (int) $linkage['id'],
                $ids,
            ));
            $this->audit->record($request, 'test_plan.cases_attached', 'test-plans', $plan, after: [
                'test_case_ids' => array_map(fn (array $linkage): mixed => $linkage['id'], $ids),
            ]);

            return $this->showResource($updated);
        });
    }

    public function destroy(Request $request, string $testPlan, string $testCase): JsonResponse
    {
        $plan = $this->findPlan($request, $testPlan);

        if ($response = $this->precondition($request, $plan)) {
            return $response;
        }

        $case = TestCase::query()->where('project_id', $plan->project_id)->whereKey($testCase)->firstOrFail();
        $this->testPlanService->detachTestCase($plan, $case);
        $this->audit->record($request, 'test_plan.case_detached', 'test-plans', $plan, before: ['test_case_id' => $case->id]);

        return JsonApi::noContent();
    }

    private function findPlan(Request $request, string $id): TestPlan
    {
        return TestPlan::query()
            ->whereIn('project_id', $request->user()->accessibleProjects()->select('project_id'))
            ->whereKey($id)
            ->firstOrFail();
    }
}
