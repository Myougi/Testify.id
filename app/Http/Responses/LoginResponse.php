<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $redirect = trim((string) $request->input('redirect', ''));

        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return redirect()->to($redirect);
        }

        return to_route('projects.index');
    }
}
