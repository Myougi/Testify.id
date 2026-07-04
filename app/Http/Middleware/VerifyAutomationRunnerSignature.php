<?php

namespace App\Http\Middleware;

use App\Support\Automation\RunnerSignature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAutomationRunnerSignature
{
    public function __construct(private readonly RunnerSignature $signature) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        if (! $this->signature->verify($request)) {
            return response()->json([
                'message' => 'The automation runner signature is invalid, expired, or already used.',
                'code' => 'invalid_runner_signature',
            ], 401);
        }

        return $next($request);
    }
}
