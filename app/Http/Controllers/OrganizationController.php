<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgs = $user->organizations()->with('quota')->get();
        return view('organizations.index', [
            'orgs' => $orgs,
            'currentOrgId' => $user->current_organization_id,
        ]);
    }

    public function create()
    {
        return view('organizations.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'type' => 'required|in:personal,company',
            'billing_email' => 'nullable|email',
            'billing_name' => 'nullable|string|max:150',
            'billing_address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:40',
        ]);

        $user = $request->user();
        $org = Organization::create(array_merge($validated, [
            'owner_id' => $user->id,
            'country' => 'ID',
            'currency' => 'IDR',
        ]));

        $ownerRole = Role::bySlug('owner');
        if ($ownerRole) {
            $org->attachMember($user, $ownerRole);
        }
        $org->quota()->create([
            'max_vps' => null,
            'max_members' => 5,
            'max_api_keys' => 10,
        ]);

        $user->switchToOrganization($org);

        return redirect()->route('organizations.show', $org->id)
            ->with('success', "Organisasi '{$org->name}' berhasil dibuat.");
    }

    public function show(Request $request, int $id)
    {
        $org = Organization::with(['members.user', 'members.role', 'quota', 'invitations.role'])->findOrFail($id);
        if (!$org->hasMember($request->user())) abort(403);

        return view('organizations.show', compact('org'));
    }

    public function switch(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        if (!$request->user()->switchToOrganization($org)) {
            return back()->with('error', 'Anda bukan anggota organisasi tersebut.');
        }
        return back()->with('success', "Sekarang aktif di {$org->name}.");
    }

    public function updateBilling(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        $role = $org->memberRole($request->user());
        if (!$role || !in_array($role->slug, ['owner', 'admin', 'billing'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'billing_email' => 'nullable|email',
            'billing_name' => 'nullable|string|max:150',
            'billing_address' => 'nullable|string|max:500',
            'tax_id' => 'nullable|string|max:40',
        ]);

        $org->update($validated);
        return back()->with('success', 'Info billing diperbarui.');
    }

    // ============================================================
    // Members
    // ============================================================
    public function invite(Request $request, int $id)
    {
        $org = Organization::findOrFail($id);
        $actor = $request->user();
        $actorRole = $org->memberRole($actor);
        if (!$actorRole || !in_array($actorRole->slug, ['owner', 'admin'], true)) abort(403);

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role_slug' => 'required|string|in:admin,billing,operator,viewer',
        ]);

        // Cek quota.
        if ($org->quota && !$org->quota->canAddMember()) {
            return back()->with('error', 'Kuota member sudah habis.');
        }

        $role = Role::bySlug($validated['role_slug']);
        if (!$role) return back()->with('error', 'Role tidak ditemukan.');

        // Kalau user sudah ada, langsung attach tanpa invitation.
        $existingUser = User::where('email', strtolower($validated['email']))->first();
        if ($existingUser && !$org->hasMember($existingUser)) {
            $org->attachMember($existingUser, $role, $actor);
            return back()->with('success', "{$existingUser->email} langsung ditambahkan (user sudah ada di sistem).");
        }

        $invitation = OrganizationInvitation::create([
            'organization_id' => $org->id,
            'email' => strtolower($validated['email']),
            'role_id' => $role->id,
            'invited_by' => $actor->id,
        ]);

        try {
            \Illuminate\Support\Facades\Notification::route('mail', $validated['email'])
                ->notify(new OrganizationInvitationNotification($invitation));
        } catch (\Throwable $e) {
            // ignore
        }

        return back()->with('success', "Undangan sudah dikirim ke {$validated['email']}.");
    }

    public function removeMember(Request $request, int $id, int $memberId)
    {
        $org = Organization::findOrFail($id);
        $actor = $request->user();
        $actorRole = $org->memberRole($actor);
        if (!$actorRole || !in_array($actorRole->slug, ['owner', 'admin'], true)) abort(403);

        $member = OrganizationMember::where('organization_id', $org->id)->findOrFail($memberId);
        if ($member->user_id === $org->owner_id) {
            return back()->with('error', 'Owner tidak bisa dihapus.');
        }
        $member->delete();
        return back()->with('success', 'Anggota dihapus.');
    }

    public function updateMemberRole(Request $request, int $id, int $memberId)
    {
        $org = Organization::findOrFail($id);
        $actor = $request->user();
        $actorRole = $org->memberRole($actor);
        if (!$actorRole || !in_array($actorRole->slug, ['owner', 'admin'], true)) abort(403);

        $validated = $request->validate(['role_slug' => 'required|string']);
        $role = Role::bySlug($validated['role_slug']);
        if (!$role) return back()->with('error', 'Role tidak ditemukan.');

        $member = OrganizationMember::where('organization_id', $org->id)->findOrFail($memberId);
        if ($member->user_id === $org->owner_id) {
            return back()->with('error', 'Role owner tidak bisa diubah.');
        }
        $member->update(['role_id' => $role->id]);
        return back()->with('success', 'Role member diperbarui.');
    }
}
