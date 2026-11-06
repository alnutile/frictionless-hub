<?php

namespace App\Mcp\Tools;

use App\Models\Initiative;
use App\Models\Participant;
use App\Models\SegmentValue;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Shared plumbing for tools that take an initiative_id.
 */
abstract class InitiativeTool extends Tool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'initiative_id' => $schema->integer()->description('The initiative id (from list_initiatives).')->required(),
        ];
    }

    protected function initiative(Request $request): Initiative
    {
        $validated = $request->validate(['initiative_id' => ['required', 'integer', 'exists:initiatives,id']]);

        return Initiative::findOrFail($validated['initiative_id']);
    }

    protected function area(Participant $participant): ?string
    {
        return $participant->segmentValues->first(fn (SegmentValue $v) => $v->category->is_area)?->name;
    }
}
