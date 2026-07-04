<?php

namespace App\Services\Automation;

use App\Models\ApiIdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunnerIdempotencyService
{
    /** @param Closure(): JsonResponse $operation */
    public function execute(Request $request, User $actor, Closure $operation): JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '' || mb_strlen($key) > 255) {
            return response()->json([
                'message' => 'A valid Idempotency-Key header is required.',
                'code' => 'invalid_idempotency_key',
            ], 400);
        }

        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $existing = ApiIdempotencyKey::query()
            ->where('user_id', $actor->id)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                return response()->json([
                    'message' => 'This idempotency key was used with a different callback.',
                    'code' => 'idempotency_key_conflict',
                ], 409);
            }

            if ($existing->status_code === null || $existing->response_body === null) {
                return response()->json([
                    'message' => 'This callback is still being processed.',
                    'code' => 'request_in_progress',
                ], 409);
            }

            $response = response()->json(
                json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR),
                $existing->status_code,
            );
            $response->headers->set('Idempotent-Replayed', 'true');

            return $response;
        }

        $record = ApiIdempotencyKey::query()->create([
            'user_id' => $actor->id,
            'key' => $key,
            'request_hash' => $requestHash,
            'expires_at' => now()->addDay(),
        ]);

        try {
            $response = $operation();
            $record->update([
                'status_code' => $response->getStatusCode(),
                'response_headers' => ['Content-Type' => 'application/json'],
                'response_body' => $response->getContent(),
            ]);

            return $response;
        } catch (\Throwable $exception) {
            $record->delete();

            throw $exception;
        }
    }
}
