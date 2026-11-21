<?php

namespace App\Jobs;

use App\Models\BroadcastLog;
use App\Models\User;
use App\Notifications\TemplatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim broadcast email per batch user di queue `notifications`
 * untuk mencegah HTTP 504 timeout dan memperbaiki sapaan {{nama}}.
 */
class SendBroadcastChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    /**
     * @param array<int, int> $userIds
     */
    public function __construct(
        public int $broadcastLogId,
        public array $userIds,
        public string $mailSubject,
        public string $bodyTemplate,
        public string $badgeText = 'PEMBERITAHUAN LAYANAN'
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $users = User::whereIn('id', $this->userIds)->get();
        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            if (! $user->email) {
                continue;
            }

            $displayName = $user->full_name ?: ($user->username ?: 'Pelanggan');
            $subject = str_replace(['{{nama}}', '{{email}}'], [$displayName, (string) $user->email], $this->mailSubject);
            $body = str_replace(['{{nama}}', '{{email}}'], [$displayName, (string) $user->email], $this->bodyTemplate);

            try {
                $user->notify(new TemplatedNotification(
                    $subject,
                    $body,
                    $this->badgeText
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('broadcast.chunk_send_failed', [
                    'broadcast_log_id' => $this->broadcastLogId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sent > 0) {
            BroadcastLog::whereKey($this->broadcastLogId)->increment('sent_count', $sent);
        }
        if ($failed > 0) {
            BroadcastLog::whereKey($this->broadcastLogId)->increment('failed_count', $failed);
        }
    }
}
