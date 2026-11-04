<?php

use App\Enums\Role;
use App\Models\User;

it('makes the first person to register the admin', function () {
    visit('/register')
        ->fill('name', 'First Person')
        ->fill('email', 'first@example.com')
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->press('Create account')
        ->assertPathIs('/initiatives')
        ->assertSee('Users & roles');

    expect(User::firstWhere('email', 'first@example.com')->role)->toBe(Role::Admin);
});

it('logs in and lands on initiatives', function () {
    User::factory()->facilitator()->create(['email' => 'fac@example.com']);

    visit('/login')
        ->fill('email', 'fac@example.com')
        ->fill('password', 'password')
        ->press('Log in')
        ->assertPathIs('/initiatives')
        ->assertSee('New initiative')
        ->assertDontSee('Users & roles');
});
