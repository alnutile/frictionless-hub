<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_open_user_management(): void
    {
        $this->actingAs(User::factory()->facilitator()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), ['role' => 'facilitator'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Facilitator, $user->fresh()->role);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), ['role' => 'viewer'])
            ->assertSessionHasErrors(['role' => 'You cannot remove your own admin role.']);

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_first_registered_user_becomes_admin_and_later_users_are_viewers(): void
    {
        $register = fn (string $email) => $this->post(route('register'), [
            'name' => 'Someone',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $register('first@example.com');
        auth()->logout();
        $register('second@example.com');

        $this->assertSame(Role::Admin, User::firstWhere('email', 'first@example.com')->role);
        $this->assertSame(Role::Viewer, User::firstWhere('email', 'second@example.com')->role);
    }
}
