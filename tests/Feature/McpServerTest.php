<?php

namespace Tests\Feature;

use App\Mcp\Servers\HubServer;
use App\Mcp\Tools\GetInterviewTool;
use App\Mcp\Tools\InitiativeOverviewTool;
use App\Mcp\Tools\ListCodesTool;
use App\Mcp\Tools\ListFrictionPointsTool;
use App\Mcp\Tools\ListInitiativesTool;
use App\Mcp\Tools\ListInterviewsTool;
use App\Mcp\Tools\ListParticipantsTool;
use App\Mcp\Tools\ListThemesTool;
use App\Models\Initiative;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The MCP tools must report the same numbers as the UI. Each assertion here matches
 * a number verified in the UI tests for the Larkspur scenario.
 */
class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LarkspurScenarioSeeder::class);
        $this->initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);
    }

    private function args(): array
    {
        return ['initiative_id' => $this->initiative->id];
    }

    public function test_lists_initiatives_with_ladder_counts(): void
    {
        HubServer::tool(ListInitiativesTool::class)
            ->assertOk()
            ->assertSee(['"name":"Larkspur DevEx 2027"', '"friction":10', '"listening-tour":12', '"interviews":5', '"themes":3']);
    }

    public function test_overview_marks_built_and_planned_steps(): void
    {
        HubServer::tool(InitiativeOverviewTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"key":"themes"', '"built":true', '"key":"stakeholders"', '"built":false', '"interview_target":14']);
    }

    public function test_friction_points_include_the_no_stage_row_and_reconcile(): void
    {
        HubServer::tool(ListFrictionPointsTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"total":10', '"no_stage":1', '"stage":"No stage","total":1', 'Hedgehog CI suite takes ~38 minutes']);
    }

    public function test_participants_and_interviews_match_the_tour_and_interview_screens(): void
    {
        HubServer::tool(ListParticipantsTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"name":"Noor Haddad"', '"status":"declined"', '"Time at Larkspur":"Over 3 years"']);

        HubServer::tool(ListInterviewsTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"participant":"Jo Haddad"', '"status":"complete"', '"open_follow_ups":1']);
    }

    public function test_get_interview_returns_notes_with_codes_and_friction_links(): void
    {
        $jo = $this->initiative->participants()->where('name', 'Jo Haddad')->first()->interview;

        HubServer::tool(GetInterviewTool::class, ['interview_id' => $jo->id])
            ->assertOk()
            ->assertSee(['"kind":"quote"', 'make coffee', '"friction_point":"Hedgehog CI suite takes ~38 minutes"', '"codes":["slow-ci"]']);
    }

    public function test_codes_and_themes_match_the_themes_screen(): void
    {
        HubServer::tool(ListCodesTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"name":"slow-ci","notes":4,"interviews":3,"theme":"Waiting on builds and environments"', '"name":"on-call"', '"theme":null']);

        HubServer::tool(ListThemesTool::class, $this->args())
            ->assertOk()
            ->assertSee(['"name":"Waiting on builds and environments"', '"notes":7,"interviews":4', '"uncoded_notes":1', '"unthemed_codes":["ai-assistants","env-setup","on-call"]']);
    }

    public function test_rejects_an_unknown_initiative(): void
    {
        HubServer::tool(InitiativeOverviewTool::class, ['initiative_id' => 999999])->assertHasErrors();
    }
}
