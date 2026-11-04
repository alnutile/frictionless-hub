<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InitiativePolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, bool, bool, bool}>
     */
    public static function roleMatrix(): array
    {
        // role, can create, can update own, can update someone else's
        return [
            'admin' => ['admin', true, true, true],
            'facilitator' => ['facilitator', true, true, false],
            'viewer' => ['viewer', false, false, false],
        ];
    }

    #[DataProvider('roleMatrix')]
    public function test_role_permissions(string $role, bool $canCreate, bool $canUpdateOwn, bool $canUpdateOthers): void
    {
        $user = User::factory()->create(['role' => $role]);
        $own = Initiative::factory()->for($user, 'owner')->create();
        $others = Initiative::factory()->create();

        $this->assertSame($canCreate, $user->can('create', Initiative::class));
        $this->assertSame($canUpdateOwn, $user->can('update', $own));
        $this->assertSame($canUpdateOthers, $user->can('update', $others));
        $this->assertTrue($user->can('view', $others));
    }

    public function test_demoted_owner_can_no_longer_manage_their_initiative(): void
    {
        $user = User::factory()->facilitator()->create();
        $initiative = Initiative::factory()->for($user, 'owner')->create();

        $user->update(['role' => 'viewer']);

        $this->assertFalse($user->fresh()->can('update', $initiative));
    }
}
