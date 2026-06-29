<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->latest()->get();

        return response()->json([
            'jsonapi' => ['version' => '1.1'],
            'data' => $tokens->map(fn (PersonalAccessToken $token): array => ResourceSerializer::serialize($token))->all(),
        ], 200, ['Content-Type' => JsonApi::MEDIA_TYPE]);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $accessToken = $request->user()->tokens()->whereKey($token)->firstOrFail();

        if ($response = $this->precondition($request, $accessToken)) {
            return $response;
        }

        $accessToken->delete();
        $this->audit->record($request, 'token.revoked', 'api-tokens', $token);

        return JsonApi::noContent();
    }
}
