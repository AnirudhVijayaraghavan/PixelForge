<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Notification::fake();

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('studio', absolute: false));
    $this->get(route('studio'))->assertOk();

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration preserves the intended workspace destination', function () {
    Notification::fake();

    $this->get(route('generations.index'))->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'New Studio User',
        'email' => 'studio@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('generations.index'));

    $user = User::query()->where('email', 'studio@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->get(route('generations.index'))->assertOk();
    Notification::assertSentTo($user, VerifyEmail::class);
});
