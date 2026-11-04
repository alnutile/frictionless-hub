<?php

namespace App\Http\Controllers;

use App\Models\GuideEntry;
use App\Models\Initiative;
use App\Support\Walkthrough;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    /**
     * Save an admin's version of a guide entry (route is admin-gated).
     */
    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(Walkthrough::isKnownKey($key), 404);

        $validated = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        GuideEntry::updateOrCreate(['key' => $key], [
            'body' => $validated['body'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Guide updated.');
    }

    /**
     * Drop the admin's version so the default file is used again.
     */
    public function destroy(string $key): RedirectResponse
    {
        abort_unless(Walkthrough::isKnownKey($key), 404);

        GuideEntry::where('key', $key)->delete();

        return back()->with('success', 'Guide reset to default.');
    }

    public function updateNote(Request $request, Initiative $initiative, string $key): RedirectResponse
    {
        $this->authorize('update', $initiative);
        abort_unless(Walkthrough::isKnownKey($key), 404);

        $validated = $request->validate(['body' => ['nullable', 'string', 'max:20000']]);

        $note = $initiative->activityNotes()->firstOrNew(['activity_key' => $key]);
        $note->body = $validated['body'];
        $note->updated_by = $request->user()->id;
        $note->save();

        return back()->with('success', 'Notes saved.');
    }
}
