<?php

namespace App\Support\Api\V1;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class JsonApi
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<int, array<string, mixed>>  $included
     * @param  array<string, mixed>  $meta
     */
    public static function resource(
        array $resource,
        int $status = 200,
        array $included = [],
        array $meta = [],
        ?string $etag = null,
    ): JsonResponse {
        $document = ['jsonapi' => ['version' => '1.1'], 'data' => $resource];

        if ($included !== []) {
            $document['included'] = $included;
        }

        if ($meta !== []) {
            $document['meta'] = $meta;
        }

        $response = response()->json($document, $status, ['Content-Type' => self::MEDIA_TYPE]);

        if ($etag !== null) {
            $response->headers->set('ETag', $etag);
        }

        return $response;
    }

    /**
     * @template TModel of Model
     * @param  CursorPaginator<int, TModel>  $paginator
     * @param  array<int, array<string, mixed>>  $resources
     */
    public static function collection(CursorPaginator $paginator, array $resources): JsonResponse
    {
        $response = response()->json([
            'jsonapi' => ['version' => '1.1'],
            'data' => $resources,
            'links' => [
                'first' => $paginator->path(),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'page' => [
                    'size' => $paginator->perPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ],
        ], 200, ['Content-Type' => self::MEDIA_TYPE]);
        $response->headers->set('ETag', '"'.hash('sha256', json_encode($resources, JSON_THROW_ON_ERROR)).'"');

        return $response;
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204, ['Content-Type' => self::MEDIA_TYPE]);
    }

    public static function error(
        Request $request,
        int $status,
        string $code,
        string $title,
        string $detail,
        ?string $pointer = null,
    ): JsonResponse {
        $error = [
            'status' => (string) $status,
            'code' => $code,
            'title' => $title,
            'detail' => $detail,
            'meta' => ['request_id' => self::requestId($request)],
        ];

        if ($pointer !== null) {
            $error['source'] = ['pointer' => $pointer];
        }

        return response()->json([
            'jsonapi' => ['version' => '1.1'],
            'errors' => [$error],
        ], $status, ['Content-Type' => self::MEDIA_TYPE]);
    }

    public static function validationError(Request $request, ValidationException $exception): JsonResponse
    {
        $errors = [];

        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => '422',
                    'code' => 'validation_failed',
                    'title' => 'Validation Failed',
                    'detail' => $message,
                    'source' => ['pointer' => '/data/attributes/'.str_replace('.', '/', $field)],
                    'meta' => ['request_id' => self::requestId($request)],
                ];
            }
        }

        return response()->json([
            'jsonapi' => ['version' => '1.1'],
            'errors' => $errors,
        ], 422, ['Content-Type' => self::MEDIA_TYPE]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function attributes(Request $request, string $expectedType): array
    {
        self::assertType($request, $expectedType);

        return Arr::wrap($request->input('data.attributes', []));
    }

    /**
     * @return array<string, mixed>
     */
    public static function relationship(Request $request, string $name): array
    {
        return Arr::wrap($request->input("data.relationships.{$name}.data", []));
    }

    public static function assertType(Request $request, string $expectedType): void
    {
        if ($request->input('data.type') !== $expectedType) {
            throw ValidationException::withMessages([
                'type' => "The resource type must be {$expectedType}.",
            ]);
        }
    }

    public static function etag(Model $model): string
    {
        return '"'.hash('sha256', json_encode([
            'table' => $model->getTable(),
            'id' => $model->getKey(),
            'updated_at' => (string) $model->getAttribute('updated_at'),
            'api_version' => (int) ($model->getAttribute('api_version') ?? 1),
        ], JSON_THROW_ON_ERROR)).'"';
    }

    public static function requireMatchingEtag(Request $request, Model $model): ?JsonResponse
    {
        $ifMatch = $request->header('If-Match');

        if (! is_string($ifMatch) || $ifMatch === '') {
            return self::error($request, 428, 'precondition_required', 'Precondition Required', 'If-Match is required for this operation.');
        }

        if (! hash_equals(self::etag($model), $ifMatch)) {
            return self::error($request, 412, 'precondition_failed', 'Precondition Failed', 'The resource changed after it was read.');
        }

        return null;
    }

    public static function requestId(Request $request): string
    {
        return (string) $request->attributes->get('request_id', '');
    }

    public static function pageSize(Request $request): int
    {
        return max(1, min(100, (int) $request->input('page.size', 25)));
    }
}
