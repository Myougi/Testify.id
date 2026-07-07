<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Services\AiGenerationService;
use App\Services\ProjectAccessService;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ReportPageController extends Controller
{
    public function __construct(
        public ReportService $reportService,
        public AiGenerationService $aiGenerationService,
        public ReportPdfService $reportPdfService,
        public ProjectAccessService $projectAccessService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $activeProject = $this->activeProject($request);
        $filters = $this->validatedFilters($request, $activeProject);

        return Inertia::render('reports/Index', [
            'initialPayload' => $activeProject
                ? $this->reportService->getPayload($activeProject, $filters)
                : $this->reportService->emptyPayload($filters),
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $activeProject = $this->activeProject($request);

        if (! $activeProject instanceof Project || ! $request->user() instanceof User) {
            return response()->json([
                'message' => 'Select an active project before exporting a report.',
            ], 422);
        }

        $filters = $this->validatedFilters($request, $activeProject);
        $payload = $this->reportService->getPayload($activeProject, $filters);

        try {
            $analysis = $this->aiGenerationService->generateReportSummary(
                $request->user(),
                $activeProject,
                $filters,
            );

            return $this->reportPdfService->download(
                $activeProject,
                $payload,
                $analysis['draft'],
                $analysis['warnings'],
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'The AI report could not be generated. Please try again.',
            ], 502);
        }
    }

    /**
     * @return array{scope: string, plan_id: int|null, range: string, start_date: string, end_date: string, grouping: string, sections: array<int, string>}
     */
    private function validatedFilters(Request $request, ?Project $activeProject): array
    {
        /** @var array{scope?: string, range?: string, start_date?: string, end_date?: string, grouping?: string, sections?: string} $validated */
        $validated = $request->validate([
            'scope' => ['nullable', 'string', 'max:50'],
            'range' => ['nullable', 'string', 'in:7d,30d,custom'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'grouping' => ['nullable', 'string', 'in:daily,weekly'],
            'sections' => ['nullable', 'string', 'max:255'],
        ]);

        $range = (string) ($validated['range'] ?? '7d');
        $endDate = Carbon::today();
        $startDate = $range === '30d'
            ? $endDate->copy()->subDays(29)
            : $endDate->copy()->subDays(6);

        if ($range === 'custom' && isset($validated['start_date'], $validated['end_date'])) {
            $startDate = Carbon::parse($validated['start_date']);
            $endDate = Carbon::parse($validated['end_date']);
        }

        $scope = (string) ($validated['scope'] ?? 'all');
        $planId = $this->planIdFromScope($scope, $activeProject);
        $sectionValues = collect($this->reportService->sections())
            ->pluck('value')
            ->all();
        $sections = collect(explode(',', (string) ($validated['sections'] ?? '')))
            ->map(fn (string $section): string => trim($section))
            ->filter(fn (string $section): bool => in_array($section, $sectionValues, true))
            ->values()
            ->all();

        if ($sections === []) {
            $sections = $sectionValues;
        }

        return [
            'scope' => $planId === null ? 'all' : (string) $planId,
            'plan_id' => $planId,
            'range' => $range,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'grouping' => (string) ($validated['grouping'] ?? 'daily'),
            'sections' => $sections,
        ];
    }

    private function planIdFromScope(string $scope, ?Project $activeProject): ?int
    {
        if ($scope === 'all' || ! is_numeric($scope)) {
            return null;
        }

        $planId = (int) $scope;

        if ($activeProject === null) {
            return null;
        }

        $belongsToActiveProject = $activeProject->testPlans()
            ->whereKey($planId)
            ->exists();

        if (! $belongsToActiveProject) {
            throw new NotFoundHttpException;
        }

        return $planId;
    }

    private function activeProject(Request $request): ?Project
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        return $this->projectAccessService
            ->accessibleProjectsQuery($request->user())
            ->whereKey((int) $activeProjectId)
            ->first();
    }
}
