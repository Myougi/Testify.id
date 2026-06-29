<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');
        $activeProject = null;

        if ($request->user() && is_numeric($activeProjectId)) {
            $project = $request->user()
                ->projects()
                ->whereKey((int) $activeProjectId)
                ->first(['project_id', 'project_name']);

            if ($project) {
                $activeProject = [
                    'id' => $project->project_id,
                    'name' => $project->project_name,
                ];
            } else {
                $request->session()->forget('qa_workspace_project_id');
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'activeProject' => $activeProject,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
