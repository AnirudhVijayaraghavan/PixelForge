<?php

use App\Models\User;

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('authenticated home visits go to studio without a redirect loop', function (bool $verified) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('studio'));
    $this->get(route('studio'))->assertOk();
})->with(['verified' => true, 'unverified' => false]);
