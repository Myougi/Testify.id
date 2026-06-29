<?php

namespace App\Http\Middleware\Api\V1;

use App\Support\Api\V1\JsonApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonApi
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_contains((string) $request->header('Accept'), JsonApi::MEDIA_TYPE)) {
            return JsonApi::error($request, 406, 'not_acceptable', 'Not Acceptable', 'Accept must include '.JsonApi::MEDIA_TYPE.'.');
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'DELETE'], true)
            && $request->header('Content-Type') !== JsonApi::MEDIA_TYPE) {
            return JsonApi::error($request, 415, 'unsupported_media_type', 'Unsupported Media Type', 'Content-Type must be exactly '.JsonApi::MEDIA_TYPE.'.');
        }

        return $next($request);
    }
}
