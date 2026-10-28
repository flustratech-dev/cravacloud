<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\LinkedAccounts\LinkedAccountSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Percobaan pertama mengirim perubahan akun ke CravaCloud WA Gateway.
 *
 * Didispatch "setelah respons", bukan ke antrean: worker antrean di sini
 * opsional, dan pengiriman yang bergantung padanya bisa diam tidak pernah
 * berjalan. Hanya membawa id pengguna — payload disusun saat berjalan, jadi
 * yang terkirim selalu keadaan terbaru. Yang gagal tetap bertanda tertunda
 * dan diulang `akun-tertaut:kirim` tiap menit.
 */
class PushLinkedAccountJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $userId) {}

    public function handle(LinkedAccountSync $sync): void
    {
        $user = User::find($this->userId);

        if (! $user || $user->linked_sync_pending_at === null) {
            return;
        }

        $sync->push($user);
    }
}
