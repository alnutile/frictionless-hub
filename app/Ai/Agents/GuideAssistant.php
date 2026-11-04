<?php

namespace App\Ai\Agents;

use App\Models\AssistantMessage;
use App\Support\AiSettings;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Messages\AssistantMessage as AiAssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The assistant in the guide panel: the admin's system prompt plus the
 * context of the screen the person is on, with their conversation so far.
 */
class GuideAssistant implements Agent, Conversational
{
    use Promptable;

    /**
     * @param  iterable<AssistantMessage>  $history
     */
    public function __construct(
        public string $context,
        public iterable $history = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return trim(AiSettings::systemPrompt())."\n\n---\n\n# Context for this conversation\n\n".$this->context;
    }

    /**
     * @return Message[]
     */
    public function messages(): iterable
    {
        return collect($this->history)
            ->map(fn (AssistantMessage $message): Message => $message->role === AssistantMessage::ROLE_ASSISTANT
                ? new AiAssistantMessage($message->content)
                : new UserMessage($message->content))
            ->values()
            ->all();
    }
}
