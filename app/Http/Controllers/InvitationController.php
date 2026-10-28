<?php

namespace App\Http\Controllers;

use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::with(['organization', 'role', 'inviter'])
            ->where('token', $token)
            ->firstOrFail();

        if (!$invitation->isValid()) {
            return view('invitations.expired', compact('invitation'));
        }

        return view('invitations.show', compact('invitation'));
    }

    public function accept(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)->firstOrFail();
        if (!$invitation->isValid()) {
            return back()->with('error', 'Undangan sudah kadaluarsa atau dicabut.');
        }

        $user = $request->user();
        if (!$user) {
            // Redirect ke login/register dengan carry email.
            session(['pending_invitation_token' => $token]);
            return redirect()->route('login')->with('info', 'Silakan login/daftar untuk menerima undangan.');
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return back()->with('error', 'Undangan ini dialamatkan ke email lain.');
        }

        $org = $invitation->organization;
        $org->attachMember($user, $invitation->role, $invitation->inviter);
        $invitation->update(['accepted_at' => now()]);

        $user->switchToOrganization($org);

        return redirect()->route('organizations.show', $org->id)
            ->with('success', "Anda sekarang jadi {$invitation->role->name} di {$org->name}.");
    }

    public function revoke(Request $request, int $id)
    {
        $invitation = OrganizationInvitation::findOrFail($id);
        $org = $invitation->organization;
        $actorRole = $org->memberRole($request->user());
        if (!$actorRole || !in_array($actorRole->slug, ['owner', 'admin'], true)) abort(403);

        $invitation->update(['revoked_at' => now()]);
        return back()->with('success', 'Undangan dicabut.');
    }
}
