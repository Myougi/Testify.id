<?php

use App\Http\Middleware\Api\V1\ApplySparseFields;
use App\Http\Middleware\Api\V1\AssignRequestId;
use App\Http\Middleware\Api\V1\EnsureJsonApi;
use App\Http\Middleware\Api\V1\EnsureTokenAbility;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\VerifyAutomationRunnerSignature;
use App\Support\Api\V1\JsonApi;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'api.request-id' => AssignRequestId::class,
            'api.json' => EnsureJsonApi::class,
            'api.ability' => EnsureTokenAbility::class,
            'api.sparse-fields' => ApplySparseFields::class,
            'automation.runner' => VerifyAutomationRunnerSignature::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return match (true) {
                $exception instanceof ValidationException => JsonApi::validationError($request, $exception),
                $exception instanceof AuthenticationException => JsonApi::error($request, 401, 'unauthenticated', 'Unauthenticated', 'A valid Bearer token is required.'),
                $exception instanceof ModelNotFoundException => JsonApi::error($request, 404, 'not_found', 'Not Found', 'The requested resource was not found.'),
                $exception instanceof HttpExceptionInterface => JsonApi::error(
                    $request,
                    $exception->getStatusCode(),
                    match ($exception->getStatusCode()) {
                        404 => 'not_found',
                        429 => 'rate_limit_exceeded',
                        default => 'http_error',
                    },
                    Response::$statusTexts[$exception->getStatusCode()] ?? 'Request Failed',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'The request could not be completed.',
                ),
                default => null,
            };
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($response->getStatusCode() === 419 && $request->is('api/*') && ! $request->is('api/v1/*')) {
                return response()->json([
                    'message' => __('Your session expired. Please log in again.'),
                    'code' => 'session_expired',
                    'login_url' => route('login', ['expired' => 1], absolute: false),
                ], 419);
            }

            if ($response->getStatusCode() === 419 && ! $request->is('api/*')) {
                return to_route('login')->with('status', __('Your session expired. Please log in again.'));
            }

            return $response;
        });
    })->create();
