<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('a signed-in user who opens a missing page is sent to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/this-page-does-not-exist')
        ->assertRedirect(route('dashboard'));
});

test('a logged-in visit to an unknown address is sent to the dashboard', function () {
    $user = User::factory()->create();

    $login = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $login->assertRedirect(route('dashboard', absolute: false));

    $session = $login->getCookie(config('session.cookie'));

    Auth::forgetGuards();

    $this->withCookie($session->getName(), $session->getValue())
        ->get('/dashboard/2026/jan/6/8')
        ->assertRedirect(route('dashboard'));
});

test('a guest who opens a missing page sees a 404', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound();
});

test('a signed-in JSON request for a missing page stays a 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/this-page-does-not-exist')
        ->assertNotFound();
});
