<?php

use App\Ai\Agents\GuideAssistant;
use App\Models\Setting;
use App\Support\AiSettings;

it('chats with the assistant from the guide panel', function () {
    GuideAssistant::fake(['Start with the Hedgehog CI suite; it is the highest pain point.']);
    Setting::put(AiSettings::KEY, 'sk-or-test');
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.friction', $initiative))
        ->click('@guide-toggle')
        ->type('@assistant-input', 'What should we tackle first?')
        ->click('@assistant-send')
        ->assertSeeIn('@assistant-messages', 'What should we tackle first?')
        ->assertSeeIn('@assistant-messages', 'Start with the Hedgehog CI suite')
        ->assertNoJavaScriptErrors();
});

it('tells non-admins when the assistant is not set up', function () {
    $initiative = larkspur();
    $this->actingAs($initiative->owner);

    visit(route('initiatives.friction', $initiative))
        ->click('@guide-toggle')
        ->assertSeeIn('@assistant', 'An admin has not set up the assistant yet.');
});
