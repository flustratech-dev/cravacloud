<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\SshKey;
use App\Services\Security\ApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    public function __construct(protected ApiKeyService $apiKeySvc)
    {
    }

    /** Halaman utama security settings. */
    public function index(Request $request)
    {
        $user = $request->user();
        return view('security.settings', [
            'twoFactorEnabled' => $user->hasTwoFactorEnabled(),
            'recentActivity' => $user->loginActivities()->take(20)->get(),
            'apiKeys' => $user->apiKeys()->orderByDesc('created_at')->get(),
            'sshKeys' => $user->sshKeys()->orderByDesc('created_at')->get(),
        ]);
    }

    // ============================================================
    // Login activity
    // ============================================================
    public function loginActivity(Request $request)
    {
        $activities = $request->user()
            ->loginActivities()
            ->paginate(30);
        return view('security.login-activity', compact('activities'));
    }

    // ============================================================
    // Session management (list & revoke)
    // ============================================================
    public function sessions(Request $request)
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        // Semua session milik user (Laravel database session driver).
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($currentSessionId) {
                return (object) [
                    'id' => $s->id,
                    'is_current' => $s->id === $currentSessionId,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'last_activity' => $s->last_activity ? \Carbon\Carbon::createFromTimestamp($s->last_activity) : null,
                ];
            });

        return view('security.sessions', compact('sessions'));
    }

    public function revokeSession(Request $request, string $sessionId)
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();
        if ($sessionId === $currentSessionId) {
            return back()->with('error', 'Tidak bisa mencabut session sendiri di sini. Pakai Logout.');
        }
        DB::table('sessions')->where('user_id', $user->id)->where('id', $sessionId)->delete();
        return back()->with('success', 'Session dicabut.');
    }

    public function revokeAllOtherSessions(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Password salah.']);
        }
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
        return back()->with('success', 'Semua session lain telah dicabut.');
    }

    // ============================================================
    // API keys
    // ============================================================
    public function createApiKey(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:80',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $user = $request->user();

        // Cek quota organization.
        $org = $user->currentOrganization;
        if ($org && $org->quota && $org->quota->max_api_keys) {
            $current = ApiKey::where('organization_id', $org->id)->whereNull('revoked_at')->count();
            if ($current >= $org->quota->max_api_keys) {
                return back()->with('error', 'Kuota API key untuk organisasi ini sudah habis.');
            }
        }

        $result = $this->apiKeySvc->mint(
            $user,
            $validated['name'],
            $validated['scopes'] ?? [],
            $org,
            !empty($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null
        );

        // Plaintext token hanya ditampilkan sekali via flash session.
        return back()->with('api_key_plaintext', $result['plain_token'])
            ->with('success', 'API key berhasil dibuat. Salin sekarang — token TIDAK bisa ditampilkan ulang.');
    }

    public function revokeApiKey(Request $request, int $id)
    {
        $user = $request->user();
        $key = ApiKey::where('user_id', $user->id)->findOrFail($id);
        $this->apiKeySvc->revoke($key, $user);
        return back()->with('success', "API key '{$key->name}' dicabut.");
    }

    public function rotateApiKey(Request $request, int $id)
    {
        $user = $request->user();
        $key = ApiKey::where('user_id', $user->id)->findOrFail($id);
        $result = $this->apiKeySvc->rotate($key, $user);
        return back()->with('api_key_plaintext', $result['plain_token'])
            ->with('success', 'API key berhasil di-rotasi.');
    }

    // ============================================================
    // SSH keys
    // ============================================================
    public function createSshKey(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'public_key' => 'required|string|max:8000',
        ]);

        $user = $request->user();
        $publicKey = trim($validated['public_key']);

        try {
            $fingerprint = SshKey::computeFingerprint($publicKey);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['public_key' => 'Format public key tidak valid.']);
        }

        $exists = SshKey::where('fingerprint', $fingerprint)->exists();
        if ($exists) {
            return back()->withInput()->withErrors(['public_key' => 'Public key ini sudah pernah ditambahkan.']);
        }

        $parts = preg_split('/\s+/', $publicKey);
        $type = $parts[0] ?? 'ssh-rsa';

        SshKey::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'type' => $type,
            'public_key' => $publicKey,
            'fingerprint' => $fingerprint,
        ]);

        return back()->with('success', 'SSH key berhasil ditambahkan.');
    }

    public function deleteSshKey(Request $request, int $id)
    {
        $user = $request->user();
        $key = SshKey::where('user_id', $user->id)->findOrFail($id);
        $key->delete();
        return back()->with('success', 'SSH key dihapus.');
    }
}
