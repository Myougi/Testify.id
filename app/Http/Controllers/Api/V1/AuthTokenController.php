<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Api\V1\AuditService;
use App\Services\Api\V1\IdempotencyService;
use App\Support\Api\V1\JsonApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthTokenController extends Controller
{
    private const ABILITIES = [
        'projects:read', 'projects:write',
        'repository-modules:read', 'repository-modules:write',
        'test-plans:read', 'test-plans:write',
        'test-cases:read', 'test-cases:write',
        'test-runs:read', 'test-runs:write',
        'issues:read', 'issues:write',
        'profile:read', 'profile:write',
        'tokens:read', 'tokens:write',
        'docs:read',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function store(Request $request): JsonResponse
    {
        JsonApi::assertType($request, 'api-token-requests');
        $attributes = $request->validate([
            'data.attributes.email' => ['required', 'email'],
            'data.attributes.password' => ['required', 'string'],
            'data.attributes.device_name' => ['required', 'string', 'max:255'],
            'data.attributes.abilities' => ['required', 'array', 'min:1'],
            'data.attributes.abilities.*' => ['required', 'string', 'distinct', Rule::in(self::ABILITIES)],
        ])['data']['attributes'];

        $user = User::query()->where('email', $attributes['email'])->first();

        if (! $user || ! Hash::check($attributes['password'], $user->password)) {
            $this->audit->record($request, 'auth.failed', 'users', $user, after: [
                'reason' => 'invalid_credentials',
                'email' => $attributes['email'],
            ], actor: $user);

            return JsonApi::error($request, 401, 'invalid_credentials', 'Authentication Failed', 'The supplied credentials are invalid.');
        }

        if (! $user->is_active) {
            $this->audit->record($request, 'auth.failed', 'users', $user, after: [
                'reason' => 'user_inactive',
            ], actor: $user);

            return JsonApi::error($request, 403, 'user_inactive', 'Account Inactive', 'This account is inactive.');
        }

        return $this->idempotency->execute($request, function () use ($request, $user, $attributes): JsonResponse {
            $expiresAt = now()->addDays((int) config('api.token_expiration_days', 30));
            $token = $user->createToken($attributes['device_name'], $attributes['abilities'], $expiresAt);
            $this->audit->record($request, 'auth.token_created', 'api-tokens', (string) $token->accessToken->getKey(), after: [
                'name' => $attributes['device_name'],
                'abilities' => $attributes['abilities'],
                'expires_at' => $expiresAt->toIso8601String(),
            ], actor: $user);

            return JsonApi::resource([
                'type' => 'api-tokens',
                'id' => (string) $token->accessToken->getKey(),
                'attributes' => [
                    'token' => $token->plainTextToken,
                    'name' => $attributes['device_name'],
                    'abilities' => $attributes['abilities'],
                    'expires_at' => $expiresAt->utc()->toIso8601String(),
                ],
            ], 201);
        }, $user);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        $tokenId = (string) $token->getKey();
        $token->delete();
        $this->audit->record($request, 'auth.token_revoked', 'api-tokens', $tokenId);

        return JsonApi::noContent();
    }
}
