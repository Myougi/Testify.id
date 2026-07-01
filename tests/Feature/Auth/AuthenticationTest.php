<?php

use App\Ai\Agents\SeniorQualityAssuranceAgent;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->has('canResetPassword'));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('projects.index', absolute: false));
});

test('users are redirected to projects after login even when dashboard was intended', function () {
    $user = User::factory()->create();

    $this->get(route('dashboard'));

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('projects.index', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('expired browser sessions are redirected to login instead of showing page expired', function () {
    $this->app->instance('env', 'local');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('projects.index'))
        ->post(route('logout'));

    $response
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('status', 'Your session expired. Please log in again.');
});

test('expired session api requests return the csrf recovery contract', function () {
    $this->app->instance('env', 'local');

    $user = User::factory()->create();

    SeniorQualityAssuranceAgent::fake()->preventStrayPrompts();

    $this
        ->actingAs($user)
        ->postJson(route('api.ai.generate'), [
            'artifact_type' => 'test_plan',
            'prompt' => 'Generate a release readiness plan.',
        ])
        ->assertStatus(419)
        ->assertExactJson([
            'message' => 'Your session expired. Please log in again.',
            'code' => 'session_expired',
            'login_url' => route('login', ['expired' => 1], absolute: false),
        ]);

    SeniorQualityAssuranceAgent::assertNeverPrompted();
});

test('expired login links explain why authentication is required', function () {
    $this->get(route('login', ['expired' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->where('status', 'Your session expired. Please log in again.'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
