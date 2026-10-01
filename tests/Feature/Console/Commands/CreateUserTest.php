<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('it creates a regular, verified user with the supplied password', function () {
    $this->artisan('app:create-user', [
        'name' => 'Study User',
        'email' => 'study@example.com',
        'password' => 'correct horse battery staple',
    ])
        ->expectsOutput('User study@example.com created successfully.')
        ->assertSuccessful();

    $user = User::query()->where('email', 'study@example.com')->firstOrFail();

    expect($user->name)->toBe('Study User')
        ->and($user->is_admin)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('correct horse battery staple', $user->password))->toBeTrue();
});

test('it can grant administrator access when requested', function () {
    $this->artisan('app:create-user', [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'correct horse battery staple',
        '--admin' => true,
    ])
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($user->is_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('it rejects duplicate email addresses', function () {
    User::factory()->create(['email' => 'study@example.com']);

    $this->artisan('app:create-user', [
        'name' => 'Another User',
        'email' => 'study@example.com',
        'password' => 'correct horse battery staple',
    ])
        ->expectsOutput('The email has already been taken.')
        ->assertFailed();
});
