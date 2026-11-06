<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::findUsable($token);

        return Inertia::render('auth/accept-invitation', [
            'token' => $token,
            'invitation' => $invitation ? ['email' => $invitation->email, 'role' => $invitation->role->label()] : null,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findUsable($token);

        if (! $invitation) {
            return back()->withErrors(['token' => 'This invitation link is invalid, expired, or already used. Ask an admin for a new one.']);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        if (User::where('email', $invitation->email)->exists()) {
            return back()->withErrors(['token' => 'An account with this email already exists. Log in instead.']);
        }

        $user = DB::transaction(function () use ($invitation, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
                'role' => $invitation->role,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('initiatives.index')->with('success', 'Welcome to Frictionless Hub.');
    }
}
