<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deleted_initiative_shows_an_in_app_not_found_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/initiatives/999999/friction')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('isInitiative', true)
                ->where('signedIn', true));
    }

    public function test_forbidden_pages_use_the_same_error_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('status', 403));
    }

    public function test_json_requests_still_get_json_errors(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/initiatives/999999/friction')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
