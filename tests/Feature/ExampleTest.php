<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests see the public landing page', function () {
    $response = $this->get(route('home'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Welcome')->where('auth.user', null));
});

test('authenticated home visits go to studio without a redirect loop', function (bool $verified) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('studio'));
    $this->get(route('studio'))->assertOk();
})->with(['verified' => true, 'unverified' => false]);
