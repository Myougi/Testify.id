<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateAiDraftRequest;
use App\Models\Project;
use App\Services\AiGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiGenerationController extends Controller
{
    public function __construct(
        public AiGenerationService $aiGenerationService,
    ) {
    }

    public function store(GenerateAiDraftRequest $request): JsonResponse
    {
        $payload = $this->aiGenerationService->generate(
            $request->user(),
            $request->validated(),
            $this->activeProject($request),
        );

        return response()->json($payload);
    }

    private function activeProject(Request $request): ?Project
    {
        $activeProjectId = $request->session()->get('qa_workspace_project_id');

        if (! $request->user() || ! is_numeric($activeProjectId)) {
            return null;
        }

        return $request->user()
            ->projects()
            ->whereKey((int) $activeProjectId)
            ->first();
    }
}
