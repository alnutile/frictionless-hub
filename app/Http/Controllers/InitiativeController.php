<?php

namespace App\Http\Controllers;

use App\Enums\Phase;
use App\Http\Requests\Initiatives\InitiativeRequest;
use App\Models\Initiative;
use App\Services\InitiativeProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InitiativeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Initiative::class);

        $initiatives = Initiative::query()
            ->with('owner:id,name')
            ->withCount(['stages', 'frictionPoints'])
            ->latest()
            ->get()
            ->map(fn (Initiative $initiative) => [
                'id' => $initiative->id,
                'name' => $initiative->name,
                'scope' => $initiative->scope,
                'phase' => $initiative->phase->label(),
                'owner' => $initiative->owner->name,
                'stages_count' => $initiative->stages_count,
                'friction_points_count' => $initiative->friction_points_count,
                'updated_at' => $initiative->updated_at->toDateString(),
            ]);

        return Inertia::render('initiatives/index', [
            'initiatives' => $initiatives,
            'canCreate' => $request->user()->can('create', Initiative::class),
        ]);
    }

    public function store(InitiativeRequest $request): RedirectResponse
    {
        $this->authorize('create', Initiative::class);

        $initiative = $request->user()->initiatives()->create($request->validated());
        $initiative->seedDefaults();

        return to_route('initiatives.show', $initiative)->with('success', 'Initiative created.');
    }

    public function show(Request $request, Initiative $initiative): Response
    {
        $this->authorize('view', $initiative);

        return Inertia::render('initiatives/show', [
            'initiative' => $this->summary($initiative),
            'canManage' => $request->user()->can('update', $initiative),
            'phases' => Phase::options(),
            'progress' => InitiativeProgress::for($initiative),
        ]);
    }

    public function update(InitiativeRequest $request, Initiative $initiative): RedirectResponse
    {
        $this->authorize('update', $initiative);

        $initiative->update($request->validated());

        return back()->with('success', 'Initiative updated.');
    }

    public function destroy(Initiative $initiative): RedirectResponse
    {
        $this->authorize('delete', $initiative);

        $initiative->delete();

        return to_route('initiatives.index')->with('success', 'Initiative deleted.');
    }

    /**
     * Shared header payload used by every initiative sub-page.
     *
     * @return array{id: int, name: string, scope: string|null, description: string|null, phase: string, phase_label: string, owner: string}
     */
    public static function summary(Initiative $initiative): array
    {
        return [
            'id' => $initiative->id,
            'name' => $initiative->name,
            'scope' => $initiative->scope,
            'description' => $initiative->description,
            'phase' => $initiative->phase->value,
            'phase_label' => $initiative->phase->label(),
            'owner' => $initiative->owner->name,
        ];
    }
}
