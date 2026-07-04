<?php

namespace App\Services\Api\V1;

use App\Models\ApiAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        Request $request,
        string $action,
        string $resourceType,
        Model|string|null $resource = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): void {
        $user = $actor ?? $request->user();
        $requestId = $request->attributes->get('request_id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }

        ApiAuditLog::query()->create([
            'user_id' => $user?->id,
            'personal_access_token_id' => $user?->currentAccessToken()?->getKey(),
            'request_id' => $requestId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resource instanceof Model ? (string) $resource->getKey() : $resource,
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), ['password', 'token', 'plain_text_token', 'two_factor_secret'], true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
