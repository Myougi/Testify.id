<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LoginResponse as LoginResponseImplementation;
use App\Http\Responses\RegisterResponse as RegisterResponseImplementation;
use App\Models\TeamInvitation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponseImplementation::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponseImplementation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->boolean('expired')
                ? __('Your session expired. Please log in again.')
                : $request->session()->get('status'),
            'email' => $request->string('email')->toString(),
            'redirect' => $request->string('redirect')->toString(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function (Request $request) {
            $token = trim((string) $request->query('team_invitation', ''));
            $invitation = $token !== ''
                ? TeamInvitation::query()
                    ->with('team.owner')
                    ->where('token', $token)
                    ->first()
                : null;

            return Inertia::render('auth/Register', [
                'email' => $request->string('email')->toString(),
                'teamInvitation' => $invitation instanceof TeamInvitation
                    ? [
                        'token' => $invitation->token,
                        'email' => $invitation->email,
                        'teamName' => $invitation->team?->name,
                        'inviterName' => $invitation->team?->owner?->name,
                    ]
                    : null,
            ]);
        });
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
