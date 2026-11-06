<?php

namespace App\Mcp\Tools;

use App\Models\Initiative;
use App\Services\InitiativeProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_initiatives')]
#[Description('List every initiative with its owner, phase and per-step counts (the same counts as the overview ladder).')]
#[IsReadOnly]
class ListInitiativesTool extends Tool
{
    public function handle(Request $request): Response
    {
        return Response::json(Initiative::with('owner:id,name,email')->orderBy('name')->get()->map(fn (Initiative $i) => [
            'id' => $i->id,
            'name' => $i->name,
            'scope' => $i->scope,
            'phase' => $i->phase->value,
            'owner' => $i->owner->name,
            'owner_email' => $i->owner->email,
            'progress' => array_map(fn (array $p) => $p['count'], InitiativeProgress::for($i)),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
