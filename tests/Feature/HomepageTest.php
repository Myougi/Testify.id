<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can visit the Testify homepage', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null));
});

test('authenticated users can visit the Testify homepage', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email));
});
