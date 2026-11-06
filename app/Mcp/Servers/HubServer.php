<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetInterviewTool;
use App\Mcp\Tools\InitiativeOverviewTool;
use App\Mcp\Tools\ListCodesTool;
use App\Mcp\Tools\ListFrictionPointsTool;
use App\Mcp\Tools\ListInitiativesTool;
use App\Mcp\Tools\ListInterviewsTool;
use App\Mcp\Tools\ListParticipantsTool;
use App\Mcp\Tools\ListThemesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * Local, read-only access to Frictionless Hub data for QA agents and assistants.
 * Runs over stdio (`php artisan mcp:start frictionless-hub`); it is never exposed over HTTP.
 * It returns the same numbers the UI shows, including participant names, so treat it as
 * having the access of someone with direct database access.
 */
#[Name('Frictionless Hub')]
#[Version('0.1.0')]
#[Instructions('Read-only access to Frictionless Hub, a DevEx program app. Start with list_initiatives, then use initiative_overview for step counts and the walkthrough. Other tools list friction points, listening-tour participants, interviews (and one interview\'s notes), codes and themes. All tools take initiative_id except get_interview (interview_id). Numbers match what the UI shows.')]
class HubServer extends Server
{
    protected array $tools = [
        ListInitiativesTool::class,
        InitiativeOverviewTool::class,
        ListFrictionPointsTool::class,
        ListParticipantsTool::class,
        ListInterviewsTool::class,
        GetInterviewTool::class,
        ListCodesTool::class,
        ListThemesTool::class,
    ];
}
