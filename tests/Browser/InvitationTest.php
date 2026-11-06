<?php

use App\Models\User;

it('lets an admin invite someone who then signs up from the link', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $page = visit(route('admin.users.index'))
        ->type('@invite-email', 'invitee@example.com')
        ->select('@invite-role', 'facilitator')
        ->click('@invite-create')
        ->assertSeeIn('@invite-link', 'invitee@example.com');

    $url = $page->value('@invite-url');

    auth()->logout();
    $this->app['auth']->forgetGuards();

    visit($url)
        ->assertSee('Join Frictionless Hub')
        ->fill('name', 'Ivy Invitee')
        ->fill('password', 'a-strong-password-1')
        ->fill('password_confirmation', 'a-strong-password-1')
        ->click('@accept-invitation')
        ->assertPathIs('/initiatives')
        ->assertSee('New initiative')
        ->assertNoJavaScriptErrors();

    expect(User::firstWhere('email', 'invitee@example.com')->role->value)->toBe('facilitator');
});

it('shows invite-only copy on the login page', function () {
    User::factory()->create();

    visit('/login')
        ->assertSee('Access is by invitation.')
        ->assertDontSee('Create the admin account');
});
