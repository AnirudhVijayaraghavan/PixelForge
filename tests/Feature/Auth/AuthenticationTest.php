<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users land in studio after login regardless of email verification', function (bool $verified) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('studio', absolute: false));
    $this->get(route('studio'))->assertOk();
    expect($user->fresh()->hasVerifiedEmail())->toBe($verified);
})->with(['verified' => true, 'unverified' => false]);

test('authenticated users visiting guest authentication pages go directly to studio', function (string $route) {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route($route))
        ->assertRedirect(route('studio'));
})->with(['login', 'register']);

test('login preserves the intended authenticated destination', function () {
    $user = User::factory()->unverified()->create();

    $this->get(route('generations.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('generations.index'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('generations.index'))->assertOk();
});

test('an intended generation URL still enforces ownership after login', function () {
    $user = User::factory()->unverified()->create();
    $otherGeneration = Generation::factory()->create();
    $destination = route('generations.show', $otherGeneration);

    $this->get($destination)->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect($destination);

    $this->assertAuthenticatedAs($user);
    $this->get($destination)->assertNotFound();
});

test('client redirect fields cannot replace the login destination', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => 'https://example.com/untrusted',
        'intended' => 'https://example.com/untrusted',
    ])->assertRedirect(route('studio', absolute: false));

    $this->assertAuthenticatedAs($user);
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

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
