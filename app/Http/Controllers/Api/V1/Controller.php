<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller extends \App\Http\Controllers\Controller
{
    public function __construct(
        protected readonly AuditService $audit,
        protected readonly IdempotencyService $idempotency,
    ) {}

    protected function showResource(Model $model, int $status = 200): JsonResponse
    {
        return JsonApi::resource(
            ResourceSerializer::serialize($model),
            $status,
            $this->included($model),
            etag: JsonApi::etag($model),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function included(Model $model): array
    {
        if ($model instanceof \App\Models\TestCase && $model->relationLoaded('steps')) {
            return $model->steps
                ->map(fn (Model $step): array => ResourceSerializer::serialize($step))
                ->values()
                ->all();
        }

        return [];
    }

    protected function precondition(Request $request, Model $model): ?JsonResponse
    {
        return JsonApi::requireMatchingEtag($request, $model);
    }
}
