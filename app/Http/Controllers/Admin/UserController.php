<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $user->created_at->toDateString(),
            ]),
            'invitations' => Invitation::with('inviter:id,name')->whereNull('accepted_at')->latest()->get()->map(fn (Invitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->label(),
                'invited_by' => $i->inviter?->name,
                'expires_at' => $i->expires_at->toDateString(),
                'status' => $i->status(),
            ]),
            'roles' => array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], Role::cases()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        if ($user->is($request->user()) && $validated['role'] !== Role::Admin->value) {
            return back()->withErrors(['role' => 'You cannot remove your own admin role.']);
        }

        $user->update($validated);

        return back()->with('success', "{$user->name} is now {$user->role->label()}.");
    }
}
