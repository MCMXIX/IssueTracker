<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('guests cannot access the current user endpoint', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

test('a user can sign in and access their profile', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertNoContent();

    $this->assertAuthenticatedAs($user, 'web');
    $this->getJson('/api/me')->assertExactJson($user->only(['id', 'name', 'email']));
});

test('invalid credentials leave the user unauthenticated', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest('web');
});

test('a user can sign out', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->postJson('/logout')->assertNoContent();

    $this->assertGuest('web');
    $this->getJson('/api/me')->assertUnauthorized();
});
