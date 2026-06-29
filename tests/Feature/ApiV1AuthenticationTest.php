<?php

use App\Models\ApiAuditLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

const JSON_API = 'application/vnd.api+json';

test('token login issues an expiring bearer token and audits authentication', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $response = $this->json('POST', '/api/v1/auth/tokens', [
        'data' => [
            'type' => 'api-token-requests',
            'attributes' => [
                'email' => $user->email,
                'password' => 'secret-password',
                'device_name' => 'CI runner',
                'abilities' => ['projects:read', 'projects:write', 'profile:read'],
            ],
        ],
    ], ['Accept' => JSON_API, 'Content-Type' => JSON_API, 'Idempotency-Key' => 'login-ci-runner']);

    $response->assertCreated()
        ->assertHeader('Content-Type', JSON_API)
        ->assertHeader('X-Request-ID')
        ->assertJsonPath('data.type', 'api-tokens')
        ->assertJsonPath('data.attributes.name', 'CI runner');

    expect($response->json('data.attributes.token'))->toContain('|');
    $plainTextToken = $response->json('data.attributes.token');
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'CI runner',
    ]);
    expect(now()->diffInDays($user->tokens()->first()->expires_at))->toBeGreaterThanOrEqual(29);
    expect(ApiAuditLog::query()->where('action', 'auth.token_created')->exists())->toBeTrue();

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/users/me', ['Accept' => JSON_API])
        ->assertOk()
        ->assertJsonPath('data.attributes.email', $user->email);

    $replayed = $this->json('POST', '/api/v1/auth/tokens', [
        'data' => [
            'type' => 'api-token-requests',
            'attributes' => [
                'email' => $user->email,
                'password' => 'secret-password',
                'device_name' => 'CI runner',
                'abilities' => ['projects:read', 'projects:write', 'profile:read'],
            ],
        ],
    ], ['Accept' => JSON_API, 'Content-Type' => JSON_API, 'Idempotency-Key' => 'login-ci-runner']);

    $replayed->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect($replayed->json('data.attributes.token'))->toBe($plainTextToken);
    expect($user->tokens()->count())->toBe(1);
});

test('token login rejects invalid credentials and inactive users', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $payload = [
        'data' => [
            'type' => 'api-token-requests',
            'attributes' => [
                'email' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'Client',
                'abilities' => ['projects:read'],
            ],
        ],
    ];

    $this->json('POST', '/api/v1/auth/tokens', $payload, ['Accept' => JSON_API, 'Content-Type' => JSON_API, 'Idempotency-Key' => 'invalid-login'])
        ->assertUnauthorized()
        ->assertJsonPath('errors.0.code', 'invalid_credentials');
    expect(ApiAuditLog::query()->where('action', 'auth.failed')->count())->toBe(1);

    $user->update(['is_active' => false]);
    $payload['data']['attributes']['password'] = 'secret-password';

    $this->json('POST', '/api/v1/auth/tokens', $payload, ['Accept' => JSON_API, 'Content-Type' => JSON_API, 'Idempotency-Key' => 'inactive-login'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'user_inactive');
    expect(ApiAuditLog::query()->where('action', 'auth.failed')->count())->toBe(2);
});

test('json api negotiation and token abilities are enforced', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['projects:read']);

    $this->getJson('/api/v1/projects')
        ->assertNotAcceptable()
        ->assertJsonPath('errors.0.code', 'not_acceptable');

    $this->json('POST', '/api/v1/projects', [
        'data' => ['type' => 'projects', 'attributes' => ['name' => 'Blocked']],
    ], ['Accept' => JSON_API, 'Content-Type' => JSON_API, 'Idempotency-Key' => 'ability-test'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'insufficient_ability');
});
