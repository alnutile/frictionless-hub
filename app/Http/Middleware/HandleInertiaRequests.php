<?php

namespace App\Http\Middleware;

use App\Models\AssistantMessage;
use App\Models\Initiative;
use App\Services\Guide;
use App\Support\AiSettings;
use App\Support\Walkthrough;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'can' => [
                    'admin' => (bool) $request->user()?->isAdmin(),
                    'facilitate' => (bool) $request->user()?->canFacilitate(),
                ],
            ],
            'guide' => fn () => $this->guide($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }

    /**
     * Guide panel payload for the current route, or null if the route has no guide.
     *
     * @return array<string, mixed>|null
     */
    private function guide(Request $request): ?array
    {
        $plannedKey = $request->route('key');
        $key = Walkthrough::keyForRoute($request->route()?->getName(), is_string($plannedKey) ? $plannedKey : null);

        if ($key === null || $request->user() === null) {
            return null;
        }

        $initiative = $request->route('initiative');
        $initiative = $initiative instanceof Initiative ? $initiative : null;
        $body = Guide::body($key);
        $isAdmin = $request->user()->isAdmin();

        return [
            'key' => $key,
            'title' => Walkthrough::titleFor($key),
            'html' => Guide::render($body),
            'markdown' => $isAdmin ? $body : null,
            'customized' => $isAdmin && Guide::isCustomized($key),
            'canEdit' => $isAdmin,
            'activities' => Walkthrough::forFrontend(),
            'initiativeId' => $initiative?->id,
            'note' => $initiative
                ? $initiative->activityNotes()->where('activity_key', $key)->value('body')
                : null,
            'canEditNote' => $initiative !== null && $request->user()->can('update', $initiative),
            'assistant' => [
                'configured' => AiSettings::isConfigured(),
                'canConfigure' => $isAdmin,
                'endpoint' => $initiative ? route('initiatives.assistant.store', $initiative) : route('assistant.store'),
                'clearEndpoint' => $initiative ? route('initiatives.assistant.destroy', $initiative) : route('assistant.destroy'),
                'messages' => AssistantMessage::conversation($request->user(), $initiative)
                    ->latest('id')
                    ->limit(30)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn (AssistantMessage $message) => [
                        'id' => $message->id,
                        'role' => $message->role,
                        'content' => $message->content,
                        'html' => $message->role === AssistantMessage::ROLE_ASSISTANT ? Guide::render($message->content) : null,
                    ]),
            ],
        ];
    }
}
