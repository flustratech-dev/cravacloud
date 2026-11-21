<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\VpsInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    use Concerns\LogsAdminAudit;

    public function tickets(Request $request)
    {
        if ($request->query('tab') === 'abuse') {
            return redirect()->route('admin.abuse.index');
        }
        if ($request->query('tab') === 'broadcast') {
            return redirect()->route('admin.broadcast.index');
        }

        $query = SupportTicket::with(['customer', 'vpsInstance', 'latestMessage', 'assignee'])->orderBy('updated_at', 'desc');

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereIn('status', SupportTicket::OPEN_STATUSES);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->type === 'service') {
            $query->serviceRequests();
        } elseif ($request->filled('type') && array_key_exists($request->type, SupportTicket::typeLabels())) {
            $query->where('type', $request->type);
        }

        $openServiceRequestCount = SupportTicket::serviceRequests()->awaitingTeam()->count();
        $tickets = $query->paginate(15)->withQueryString();

        return view('admin.tickets', compact('tickets', 'openServiceRequestCount'));
    }

    public function showTicket($id)
    {
        $ticket = SupportTicket::with(['customer', 'vpsInstance', 'messages.user', 'assignee'])->findOrFail($id);
        $admins = User::where('is_admin', true)->orderBy('full_name')->get();

        return view('admin.ticket-show', compact('ticket', 'admins'));
    }

    public function completeReinstall(Request $request, $id)
    {
        $ticket = SupportTicket::with('vpsInstance')->findOrFail($id);

        if ($ticket->type !== SupportTicket::TYPE_REINSTALL) {
            return back()->with('error', 'Tiket ini bukan permintaan reinstall.');
        }
        if (!$ticket->isOpen()) {
            return back()->with('error', 'Permintaan reinstall ini sudah ditutup.');
        }

        $vps = $ticket->vpsInstance;
        if (!$vps) {
            return back()->with('error', 'Server yang terkait dengan tiket ini tidak ditemukan.');
        }

        $osOptions = $vps->reinstallOsOptions();
        $stackOptions = $vps->reinstallStackOptions();

        $rules = [
            'os' => ['required', 'string', Rule::in(array_keys($osOptions))],
            'root_password' => ['required', 'string', 'min:8', 'max:128'],
            'ssh_username' => VpsInstance::SSH_USERNAME_RULE,
            'message' => ['nullable', 'string', 'max:2000'],
        ];
        if (!$vps->hasFixedStack()) {
            $rules['control_panel'] = ['required', 'string', Rule::in(array_keys($stackOptions))];
        }
        $validated = $request->validate($rules, VpsInstance::SSH_USERNAME_MESSAGES);

        $sshUsername = trim((string) ($validated['ssh_username'] ?? '')) !== ''
            ? VpsInstance::normalizeSshUsername($validated['ssh_username'])
            : $vps->ssh_user;

        $controlPanel = $vps->hasFixedStack() ? $vps->control_panel : $validated['control_panel'];
        $osLabel = $osOptions[$validated['os']];
        $serverName = $vps->hostname ?? ('VPS-' . $vps->id);
        $ticketCode = '#TK-' . str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);

        $customerMessage = trim((string) ($validated['message'] ?? ''));
        if ($customerMessage === '') {
            $customerMessage = "Reinstall OS {$osLabel} untuk server {$serverName} sudah selesai.\n\n"
                . "Username SSH: {$sshUsername}. Password baru dapat dilihat di Dashboard > Detail VPS > tab Akses (klik ikon mata). "
                . "Setelah login, segera ganti password dengan perintah passwd.";
        }

        $completed = DB::transaction(function () use ($ticket, $vps, $validated, $controlPanel, $osLabel, $customerMessage, $ticketCode, $sshUsername) {
            $lockedTicket = SupportTicket::whereKey($ticket->id)->lockForUpdate()->first();
            if (!$lockedTicket || !$lockedTicket->isOpen()) {
                return false;
            }

            $lockedVps = VpsInstance::whereKey($vps->id)->lockForUpdate()->firstOrFail();
            $lockedVps->os = $validated['os'];
            $lockedVps->control_panel = $controlPanel;
            $lockedVps->initial_root_password = $validated['root_password'];
            $lockedVps->ssh_username = $sshUsername;
            $lockedVps->root_password_revealed_at = null;
            $lockedVps->save();

            TicketMessage::create([
                'ticket_id' => $lockedTicket->id,
                'user_id' => Auth::id(),
                'message' => $customerMessage,
                'is_admin_reply' => true,
                'is_internal' => false,
            ]);

            $lockedTicket->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'first_response_at' => $lockedTicket->first_response_at ?: now(),
            ]);

            $lockedVps->logActivity(
                action: 'reinstall_completed',
                description: "Reinstall OS {$osLabel} selesai dikerjakan tim. Password root baru tersedia di tab Akses. ({$ticketCode})",
                status: 'completed',
                userId: Auth::id()
            );

            return true;
        });

        if (!$completed) {
            return back()->with('error', 'Permintaan reinstall ini sudah diselesaikan oleh admin lain.');
        }

        try {
            $ticket->refresh()->customer?->notify(new \App\Notifications\TicketRepliedNotification($ticket, $customerMessage));
        } catch (\Throwable $e) {
            Log::error('admin.ticket_reinstall.notify_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', "Reinstall {$serverName} ditandai selesai. Password root baru tersimpan dan pelanggan sudah diberi tahu.");
    }

    public function replyTicket(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $validated = $request->validate([
            'message' => 'required|string|min:2',
            'status' => 'required|in:open,in_progress,resolved,closed',
            'is_internal' => 'nullable|boolean',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'message' => $validated['message'],
            'is_admin_reply' => true,
            'is_internal' => $request->boolean('is_internal'),
        ]);

        $updates = ['status' => $validated['status']];
        if (!$request->boolean('is_internal') && !$ticket->first_response_at) {
            $updates['first_response_at'] = now();
        }
        if ($validated['status'] === 'resolved' || $validated['status'] === 'closed') {
            $updates['resolved_at'] = $ticket->resolved_at ?: now();
        } elseif ($validated['status'] === 'open' || $validated['status'] === 'in_progress') {
            $updates['resolved_at'] = null;
        }
        $ticket->update($updates);
        $ticket->touch();

        if (!$request->boolean('is_internal')) {
            try {
                $customer = $ticket->customer;
                if ($customer) {
                    $customer->notify(new \App\Notifications\TicketRepliedNotification($ticket, $validated['message']));
                }
            } catch (\Throwable $e) {
                Log::error('admin.ticket_reply.notify_failed', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'Balasan admin berhasil dikirim ke tiket pelanggan.');
    }

    public function assignTicket(Request $request, $id)
    {
        $validated = $request->validate(['assigned_to' => 'nullable|exists:users,id']);
        if (!empty($validated['assigned_to']) && !User::where('id', $validated['assigned_to'])->where('is_admin', true)->exists()) {
            return back()->with('error', 'Petugas yang dipilih bukan administrator.');
        }
        SupportTicket::findOrFail($id)->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('success', 'Assignment tiket berhasil diperbarui.');
    }
}
