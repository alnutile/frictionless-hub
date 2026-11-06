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

    public function test_only_the_first_person_can_self_register_and_they_become_admin(): void
    {
        $register = fn (string $email) => $this->post(route('register'), [
            'name' => 'Someone',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $register('first@example.com');
        auth()->logout();
        $register('second@example.com')->assertRedirect(route('login'));

        $this->assertSame(Role::Admin, User::firstWhere('email', 'first@example.com')->role);
        $this->assertFalse(User::where('email', 'second@example.com')->exists());
    }
}
