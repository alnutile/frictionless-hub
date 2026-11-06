<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Models\Initiative;
use App\Models\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageTemplateController extends Controller
{
    public function store(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $initiative->messageTemplates()->create([
            ...$this->validated($request),
            'purpose' => MessageTemplate::PURPOSE_INVITE,
            'position' => (int) $initiative->messageTemplates()->max('position') + 1,
        ]);

        return back()->with('success', 'Template added.');
    }

    public function update(Request $request, Initiative $initiative, MessageTemplate $messageTemplate): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $messageTemplate->update($this->validated($request));

        return back()->with('success', 'Template saved.');
    }

    public function destroy(Initiative $initiative, MessageTemplate $messageTemplate): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $messageTemplate->delete();

        return back()->with('success', 'Template removed.');
    }

    /**
     * @return array{name: string, channel: string, subject: string|null, body: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['email', 'chat'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
    }
}
