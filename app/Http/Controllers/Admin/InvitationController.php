<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'role' => ['required', Rule::enum(Role::class)],
        ], [
            'email.unique' => 'Someone with that email already has an account.',
        ]);

        // One open invite per email: replace any pending one.
        Invitation::where('email', $validated['email'])->whereNull('accepted_at')->delete();

        $invitation = new Invitation([...$validated, 'invited_by' => $request->user()->id]);
        $token = $invitation->issueToken();

        return back()->with('invite_link', ['email' => $invitation->email, 'url' => route('invitations.show', $token)]);
    }

    /**
     * Issue a fresh link (the old one stops working), e.g. if the first one was lost or expired.
     */
    public function regenerate(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 422, 'This invitation was already accepted.');

        $token = $invitation->issueToken();

        return back()->with('invite_link', ['email' => $invitation->email, 'url' => route('invitations.show', $token)]);
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return back()->with('success', "Invitation for {$invitation->email} revoked.");
    }
}
