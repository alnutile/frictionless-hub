<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Create an invite as the admin and return the plain link it produced.
     */
    private function invite(string $email = 'new.person@example.com', string $role = 'facilitator'): string
    {
        $response = $this->actingAs($this->admin)->post(route('admin.invitations.store'), ['email' => $email, 'role' => $role]);
        $response->assertSessionHas('invite_link');

        return session('invite_link')['url'];
    }

    private function accept(string $url, array $overrides = [])
    {
        auth()->logout();

        return $this->post($url, [
            'name' => 'New Person',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
            ...$overrides,
        ]);
    }

    public function test_only_admins_can_invite(): void
    {
        $this->actingAs(User::factory()->facilitator()->create())
            ->post(route('admin.invitations.store'), ['email' => 'x@example.com', 'role' => 'viewer'])
            ->assertForbidden();
    }

    public function test_the_token_is_stored_only_as_a_hash(): void
    {
        $url = $this->invite();
        $token = basename($url);

        $invitation = Invitation::firstOrFail();
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame(now()->addDays(7)->toDateString(), $invitation->expires_at->toDateString());
    }

    public function test_accepting_creates_a_verified_account_with_the_invited_role_and_signs_in(): void
    {
        $url = $this->invite('Mixed.Case@Example.com', 'facilitator');

        $this->accept($url)->assertRedirect(route('initiatives.index'));

        $user = User::firstWhere('email', 'mixed.case@example.com');
        $this->assertSame(Role::Facilitator, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(Invitation::first()->accepted_at);
    }

    public function test_a_link_works_only_once(): void
    {
        $url = $this->invite();
        $this->accept($url);

        $this->accept($url, ['name' => 'Second Try'])
            ->assertSessionHasErrors(['token' => 'This invitation link is invalid, expired, or already used. Ask an admin for a new one.']);

        $this->assertSame(1, User::where('email', 'new.person@example.com')->count());
    }

    public function test_expired_links_are_rejected(): void
    {
        $url = $this->invite();
        $this->travel(8)->days();

        $this->accept($url)->assertSessionHasErrors('token');
        $this->assertFalse(User::where('email', 'new.person@example.com')->exists());
    }

    public function test_a_new_link_replaces_the_old_one(): void
    {
        $old = $this->invite();

        $this->actingAs($this->admin)->post(route('admin.invitations.regenerate', Invitation::first()));
        $new = session('invite_link')['url'];

        $this->accept($old)->assertSessionHasErrors('token');
        $this->accept($new)->assertRedirect(route('initiatives.index'));
    }

    public function test_revoked_invites_stop_working(): void
    {
        $url = $this->invite();

        $this->actingAs($this->admin)->delete(route('admin.invitations.destroy', Invitation::first()));

        $this->accept($url)->assertSessionHasErrors('token');
    }

    public function test_cannot_invite_an_email_that_already_has_an_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin)
            ->post(route('admin.invitations.store'), ['email' => 'Taken@example.com', 'role' => 'viewer'])
            ->assertSessionHasErrors(['email' => 'Someone with that email already has an account.']);
    }

    public function test_inviting_the_same_email_again_replaces_the_pending_invite(): void
    {
        $first = $this->invite();
        $this->invite();

        $this->assertSame(1, Invitation::count());
        $this->accept($first)->assertSessionHasErrors('token');
    }

    public function test_an_invalid_link_shows_a_friendly_page(): void
    {
        $this->get(route('invitations.show', 'not-a-real-token'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/accept-invitation')->where('invitation', null));
    }

    public function test_public_registration_is_closed_once_anyone_has_an_account(): void
    {
        $this->get(route('register'))->assertRedirect(route('login'));

        $this->post(route('register'), [
            'name' => 'Walk-in',
            'email' => 'walkin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertFalse(User::where('email', 'walkin@example.com')->exists());
    }
}
