<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DashboardService;
use App\Services\ProjectAccessService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPageController extends Controller
{
    public function __construct(
        public DashboardService $dashboardService,
        public ProjectAccessService $projectAccessService,
    ) {}

    public function index(Request $request): Response
    {
        $activeProject = $this->activeProject($request);

        return Inertia::render('Dashboard', [
            'initialPayload' => $activeProject
                ? $this->dashboardService->getPayload($activeProject)
                : $this->dashboardService->emptyPayload(),
        ]);
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
