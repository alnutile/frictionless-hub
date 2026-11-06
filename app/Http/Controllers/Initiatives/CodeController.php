<?php

namespace App\Http\Controllers\Initiatives;

use App\Http\Controllers\Controller;
use App\Models\Code;
use App\Models\Initiative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CodeController extends Controller
{
    /**
     * Create a code, optionally applying it straight away to a note in this initiative.
     */
    public function store(Request $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('codes')->where('initiative_id', $initiative->id)],
            'note_id' => ['nullable', 'integer'],
        ]);

        $code = $initiative->codes()->create(['name' => $validated['name']]);

        if (isset($validated['note_id'])) {
            $note = $initiative->interviewNotes()->where('interview_notes.id', $validated['note_id'])->firstOrFail();
            $note->codes()->syncWithoutDetaching([$code->id]);
        }

        return back();
    }

    public function destroy(Initiative $initiative, Code $code): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $code->delete();

        return back();
    }
}
