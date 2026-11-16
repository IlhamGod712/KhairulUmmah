<?php

use App\Enums\UserRole;
use App\Models\User;

test('health endpoint reports the service is reachable', function () {
    $response = $this->getJson(route('api.health'));

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => ['service', 'environment', 'api_prefix'],
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.api_prefix', 'api');
});

test('health endpoint allows the react spa origin', function () {
    $response = $this->getJson(route('api.health'), [
        'Origin' => 'http://localhost:5173',
    ]);

    $response
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

test('users are cast to the role enum', function () {
    $user = User::factory()->admin()->create();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->isAdmin())->toBeTrue()
        ->and($user->isWali())->toBeFalse();
});

test('users default to the wali role', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::Wali)
        ->and($user->isWali())->toBeTrue();
});
