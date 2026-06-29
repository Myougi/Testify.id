<?php

namespace App\Services\Api\V1;

use App\Models\ApiIdempotencyKey;
use App\Models\User;
use App\Support\Api\V1\JsonApi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyService
{
    /**
     * @param  Closure(): JsonResponse  $operation
     */
    public function execute(Request $request, Closure $operation, ?User $actor = null): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return JsonApi::error($request, 400, 'idempotency_key_required', 'Idempotency Key Required', 'Idempotency-Key is required for POST operations.');
        }

        if (mb_strlen($key) > 255) {
            return JsonApi::error($request, 400, 'invalid_idempotency_key', 'Invalid Idempotency Key', 'Idempotency-Key must not exceed 255 characters.');
        }

        $user = $actor ?? $request->user();
        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $existing = ApiIdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                return JsonApi::error($request, 409, 'idempotency_key_conflict', 'Idempotency Conflict', 'This key was already used with a different request payload.');
            }

            if ($existing->status_code === null || $existing->response_body === null) {
                return JsonApi::error($request, 409, 'request_in_progress', 'Request In Progress', 'A request with this idempotency key is still being processed.');
            }

            $storedHeaders = $existing->getAttribute('response_headers');
            $headers = is_array($storedHeaders) ? $storedHeaders : [];
            $response = new JsonResponse(
                json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR),
                $existing->status_code,
                $headers,
            );
            $response->headers->set('Idempotent-Replayed', 'true');

            return $response;
        }

        $record = ApiIdempotencyKey::query()->create([
            'user_id' => $user->id,
            'personal_access_token_id' => $actor === null
                ? $user->currentAccessToken()->getKey()
                : null,
            'key' => $key,
            'request_hash' => $requestHash,
            'expires_at' => now()->addDay(),
        ]);

        try {
            $response = $operation();
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_headers' => array_filter([
                    'Content-Type' => JsonApi::MEDIA_TYPE,
                    'ETag' => $response->headers->get('ETag'),
                    'Location' => $response->headers->get('Location'),
                ]),
                'response_body' => $response->getContent(),
            ]);

            return $response;
        } catch (\Throwable $exception) {
            $record->delete();

            throw $exception;
        }
    }
}
