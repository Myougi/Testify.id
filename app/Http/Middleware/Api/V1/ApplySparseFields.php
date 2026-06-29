<?php

namespace App\Http\Middleware\Api\V1;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySparseFields
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $fields = $request->input('fields', []);

        if (! $response instanceof JsonResponse || ! is_array($fields) || $fields === []) {
            return $response;
        }

        $document = $response->getData(true);

        if (isset($document['data'])) {
            $document['data'] = $this->apply($document['data'], $fields);
        }

        if (isset($document['included'])) {
            $document['included'] = $this->apply($document['included'], $fields);
        }

        $response->setData($document);

        return $response;
    }

    /**
     * @param  mixed  $resources
     * @param  array<string, mixed>  $fields
     */
    private function apply(mixed $resources, array $fields): mixed
    {
        if (! is_array($resources)) {
            return $resources;
        }

        if (array_is_list($resources)) {
            return array_map(fn (mixed $resource): mixed => $this->apply($resource, $fields), $resources);
        }

        $type = $resources['type'] ?? null;

        if (! is_string($type) || ! isset($resources['attributes']) || ! is_array($resources['attributes'])) {
            return $resources;
        }

        $requested = $fields[$type] ?? null;

        if (! is_string($requested)) {
            return $resources;
        }

        $allowed = array_filter(array_map('trim', explode(',', $requested)));
        $resources['attributes'] = array_intersect_key($resources['attributes'], array_flip($allowed));

        return $resources;
    }
}
