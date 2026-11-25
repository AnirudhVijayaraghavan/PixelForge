<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('dashboard redirects authenticated users to studio without requiring verification', function (bool $verified) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('studio'));
    $this->get(route('studio'))->assertOk();
})->with(['verified' => true, 'unverified' => false]);
