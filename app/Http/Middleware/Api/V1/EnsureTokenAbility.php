<?php

namespace App\Http\Middleware\Api\V1;

use App\Support\Api\V1\JsonApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        if (! $request->user()?->tokenCan($ability)) {
            return JsonApi::error($request, 403, 'insufficient_ability', 'Forbidden', 'The current token does not have the required ability.');
        }

        return $next($request);
    }
}
