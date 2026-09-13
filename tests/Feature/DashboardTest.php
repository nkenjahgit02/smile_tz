<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed();
    $user = User::factory()->create();
    $user->assignRole('Seller');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
