<?php

use App\Models\Code;

it('takes interview notes, codes them, sends a pain point to the friction log and completes the interview', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);
    $marta = $initiative->participants()->where('name', 'Marta Kowalski')->firstOrFail();
    $reviewing = $initiative->stages()->firstWhere('name', 'Reviewing');
    $reviewWait = Code::firstWhere('name', 'review-wait');

    $page = visit(route('initiatives.interviews', $initiative))
        ->assertSeeIn('@code-frequency', 'slow-ci')
        ->click("@start-interview-{$marta->id}")
        ->assertSee('Interview: Marta Kowalski')
        ->type('@note-input-pain_point', 'Large frontend PRs sit for days before anyone looks at them.')
        ->select('@note-stage-pain_point', (string) $reviewing->id)
        ->click('@note-add-pain_point')
        ->assertSeeIn('@section-pain_point', 'Large frontend PRs sit for days');

    $note = $marta->fresh()->interview->notes()->firstOrFail();

    $page->click("@note-codes-{$note->id}")
        ->click("@code-chip-{$note->id}-{$reviewWait->id}")
        ->assertSeeIn("@note-{$note->id}", 'review-wait')
        ->click("@promote-{$note->id}")
        ->assertSee('Added to the friction log.')
        ->assertSeeIn("@note-{$note->id}", 'In friction log')
        ->click('@interview-complete')
        ->assertSee('Interview saved and marked complete.')
        ->assertSeeIn('@interview-status', 'Complete')
        ->assertNoJavaScriptErrors();

    expect($note->fresh()->codes()->pluck('name')->all())->toBe(['review-wait'])
        ->and($note->fresh()->friction_point_id)->not->toBeNull()
        ->and($marta->fresh()->status->value)->toBe('interviewed');
});

it('asks before completing an interview with no notes and surfaces follow-ups', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);
    $kenji = $initiative->participants()->where('name', 'Kenji Mori')->firstOrFail();
    $interview = $initiative->interviews()->create(['participant_id' => $kenji->id, 'status' => 'draft']);

    // Playwright dismisses confirm() dialogs, i.e. the user answers "Cancel".
    visit(route('initiatives.interviews.show', [$initiative, $interview]))
        ->assertSeeIn('@jump-follow_up', 'Follow-ups (0)')
        ->click('@interview-complete')
        ->assertSeeIn('@interview-status', 'Draft')
        ->assertNoJavaScriptErrors();

    expect($interview->fresh()->status->value)->toBe('draft')
        ->and($kenji->fresh()->status->value)->toBe('candidate');

    visit(route('initiatives.interviews', $initiative))
        ->assertSeeIn('@open-follow-ups', 'Ask the platform team whether preview environments could be self-service.')
        ->assertPresent('@follow-ups-tile');
});
