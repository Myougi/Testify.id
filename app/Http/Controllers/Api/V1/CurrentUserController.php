<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Api\V1\JsonApi;
use App\Support\Api\V1\ResourceSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrentUserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return JsonApi::resource(ResourceSerializer::serialize($user), etag: JsonApi::etag($user));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($response = $this->precondition($request, $user)) {
            return $response;
        }

        $attributes = JsonApi::attributes($request, 'users');
        $data = validator($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ])->validate();
        $before = $user->only(['name', 'email']);
        $user->update($data);
        $this->audit->record($request, 'profile.updated', 'users', $user, $before, $user->only(['name', 'email']));

        return $this->showResource($user->fresh());
    }
}
