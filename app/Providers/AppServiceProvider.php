<?php

namespace App\Providers;

use App\Ai\Agents\SeniorQualityAssuranceAgent;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Providers\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApiRateLimits();
        $this->configureAiEventLogging();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureApiRateLimits(): void
    {
        RateLimiter::for('api-login', fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limits.login', 5),
        )->by(strtolower((string) $request->input('data.attributes.email')).'|'.$request->ip()));

        RateLimiter::for('api-read', fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limits.read', 120),
        )->by((string) $request->user()->id));

        RateLimiter::for('api-write', fn (Request $request): Limit => Limit::perMinute(
            (int) config('api.rate_limits.write', 60),
        )->by((string) $request->user()->id));

    }

    private function configureAiEventLogging(): void
    {
        Event::listen(PromptingAgent::class, function (PromptingAgent $event): void {
            if (! $event->prompt->agent instanceof SeniorQualityAssuranceAgent) {
                return;
            }

            Log::info('QA AI agent prompting', [
                'invocation_id' => $event->invocationId,
                'agent' => $event->prompt->agent::class,
                'artifact_type' => $event->prompt->agent->artifactType,
                'provider' => $this->resolveAiProviderName($event->prompt->provider()),
                'model' => $event->prompt->model,
                'prompt_length' => mb_strlen($event->prompt->prompt),
            ]);
        });

        Event::listen(AgentPrompted::class, function (AgentPrompted $event): void {
            if (! $event->prompt->agent instanceof SeniorQualityAssuranceAgent) {
                return;
            }

            Log::info('QA AI agent prompted', [
                'invocation_id' => $event->invocationId,
                'agent' => $event->prompt->agent::class,
                'artifact_type' => $event->prompt->agent->artifactType,
                'provider' => $event->response->meta->provider,
                'model' => $event->response->meta->model,
                'prompt_tokens' => $event->response->usage->promptTokens,
                'completion_tokens' => $event->response->usage->completionTokens,
            ]);
        });
    }

    private function resolveAiProviderName(TextProvider $provider): string
    {
        if ($provider instanceof Provider) {
            return $provider->name();
        }

        return $provider::class;
    }
}
