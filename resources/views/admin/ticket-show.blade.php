@extends('layouts.admin', ['title' => 'Tanggapi Tiket #' . $ticket->id, 'headerTitle' => 'Respon Tiket Bantuan', 'backUrl' => route('admin.tickets'), 'backLabel' => 'Kembali ke Daftar Tiket'])

@section('content')
<div class="space-y-6 max-w-3xl">
    <!-- Ticket Summary Card -->
    <div class="bg-white border border-hairline p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-hairline">
            <div>
                <span class="font-mono text-xs text-muted font-bold">#TK-{{ str_pad($ticket->id, 4, '0', STR_PAD_LEFT) }}</span>
                <h1 class="font-display text-base font-bold text-ink mt-0.5">{{ $ticket->subject }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="px-2.5 py-1 font-mono text-[10px] font-bold uppercase border border-hairline bg-creamDark text-ink">
                    {{ str_replace('_', ' ', $ticket->status) }}
                </span>
                <span class="font-mono text-[11px] text-muted">{{ $ticket->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</span>
            </div>
        </div>

        <div class="mt-4 pb-4 border-b border-hairline flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="text-muted">SLA: <span class="font-mono font-bold {{ $ticket->sla_due_at && $ticket->sla_due_at->isPast() && !$ticket->first_response_at ? 'text-electric' : 'text-ink' }}">{{ $ticket->first_response_at ? 'Sudah direspons ' . $ticket->first_response_at->diffForHumans() : ($ticket->sla_due_at?->diffForHumans() ?? '-') }}</span></div>
            <form method="POST" action="{{ route('admin.tickets.assign', $ticket->id) }}" class="flex items-center gap-2">
                @csrf
                <select name="assigned_to" onchange="this.form.submit()" class="px-2.5 py-1.5 border border-hairline bg-white font-mono text-xs text-ink focus:outline-none focus:border-electric">
                    <option value="">Belum ditugaskan</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" {{ $ticket->assigned_to === $admin->id ? 'selected' : '' }}>{{ $admin->full_name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 text-xs text-muted">
            <div>
                <span class="font-mono text-[10px] uppercase tracking-widest block">Pelapor</span>
                <span class="font-semibold text-ink mt-0.5 block">{{ $ticket->customer->full_name }}</span>
            </div>
            <div>
                <span class="font-mono text-[10px] uppercase tracking-widest block">Email</span>
                <span class="font-mono text-ink mt-0.5 block truncate">{{ $ticket->customer->email }}</span>
            </div>
            <div>
                <span class="font-mono text-[10px] uppercase tracking-widest block">Terkait VPS</span>
                <span class="font-mono font-semibold text-ink mt-0.5 block">{{ $ticket->vpsInstance ? ($ticket->vpsInstance->hostname ?? $ticket->vpsInstance->public_ip) : 'Umum' }}</span>
            </div>
            <div>
                <span class="font-mono text-[10px] uppercase tracking-widest block">Prioritas</span>
                <span class="font-mono font-bold uppercase text-ink mt-0.5 block">{{ $ticket->priority }}</span>
            </div>
        </div>
    </div>

    @if($ticket->isServiceRequest())
        @php
            $requestVps = $ticket->vpsInstance;
            $requestData = $ticket->request_data ?? [];
            $requestIp = $requestVps?->public_ip ?? '-';
        @endphp
        <!-- Panel Permintaan Layanan Server -->
        <div class="bg-white border {{ $ticket->isOpen() ? 'border-2 border-electric' : 'border-hairline' }} p-5 space-y-4 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <span class="px-2 py-0.5 font-mono text-[10px] font-bold uppercase {{ $ticket->type === 'reinstall' ? 'bg-electric text-white' : 'bg-ink text-cream' }}">
                        Permintaan Server
                    </span>
                    <h3 class="font-display font-bold text-ink text-sm mt-1.5">{{ $ticket->type_label }}</h3>
                </div>
                @if($ticket->isOpen())
                    <span class="font-mono text-[11px] text-muted">
                        Diajukan {{ $ticket->created_at->locale('id')->diffForHumans() }} &middot; maks. {{ \App\Models\SupportTicket::SERVICE_REQUEST_SLA_WORKING_HOURS }} jam kerja
                    </span>
                @else
                    <span class="font-mono text-[11px] font-bold text-electric">Selesai {{ $ticket->resolved_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 bg-creamDark/40 border border-hairline">
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block">Server</span>
                    <span class="font-mono font-semibold text-ink">{{ $requestVps?->hostname ?? '-' }}</span>
                </div>
                <div>
                    <span class="font-mono text-[10px] uppercase text-muted block">IP Publik</span>
                    <span class="font-mono font-semibold text-ink">{{ $requestIp }}</span>
                </div>
                @if($ticket->type === 'reinstall')
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">OS diminta</span>
                        <span class="font-semibold text-ink">{{ \App\Models\Order::osLabels()[$requestData['os'] ?? ''] ?? ($requestData['os'] ?? '-') }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Stack diminta</span>
                        <span class="font-semibold text-ink">{{ \App\Models\Order::stackLabels()[$requestData['control_panel'] ?? ''] ?? ($requestData['control_panel'] ?? '-') }}</span>
                    </div>
                @else
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Status di panel</span>
                        <span class="font-semibold text-ink">{{ $requestVps ? ucfirst($requestVps->status) : '-' }}</span>
                    </div>
                    <div>
                        <span class="font-mono text-[10px] uppercase text-muted block">Paket</span>
                        <span class="font-mono font-semibold text-ink">{{ $requestVps ? ($requestVps->cpu . ' vCPU / ' . $requestVps->ram . ' GB') : '-' }}</span>
                    </div>
                @endif
            </div>

            @if($ticket->isOpen() && $requestVps)
                @if($ticket->type === 'reinstall')
                    @if(($requestVps->provider ?? 'manual') === 'tencent')
                        @php
                            $reinstallTask = \App\Models\ProvisioningTask::where('vps_instance_id', $requestVps->id)
                                ->where('kind', 'reinstall')
                                ->latest('id')
                                ->first();
                        @endphp
                        <div class="p-4 border border-ink bg-creamDark/40 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="font-mono text-[10px] font-bold uppercase tracking-widest bg-ink text-cream px-2 py-0.5">Auto-Reinstall Tencent</span>
                                    <p class="font-semibold text-ink text-xs mt-1">
                                        Server ini menggunakan provisioning otomatis. Reinstall diproses langsung via upstream task tanpa input dashboard supplier manual.
                                    </p>
                                </div>
                                <a href="{{ route('admin.instances', ['search' => $requestVps->hostname]) }}"
                                   class="px-3 py-1.5 border border-ink bg-white hover:bg-creamDark text-ink font-mono text-[11px] font-bold uppercase">
                                    Kelola di Instances
                                </a>
                            </div>
                            @if($reinstallTask)
                                <div class="pt-2 border-t border-hairline font-mono text-[11px] flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        Task #{{ $reinstallTask->id }} &middot; Status: <strong class="uppercase">{{ $reinstallTask->status }}</strong> ({{ (int) $reinstallTask->progress }}%)
                                        @if($reinstallTask->current_step)
                                            &middot; Step: {{ $reinstallTask->current_step }}
                                        @endif
                                        @if($reinstallTask->error)
                                            <span class="block text-ink font-bold mt-1">Error: {{ $reinstallTask->error }}</span>
                                        @endif
                                    </div>
                                    @if($reinstallTask->status === 'failed')
                                        <form action="{{ route('admin.tasks.retry', $reinstallTask->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 bg-electric hover:bg-electricHover text-white font-mono text-[11px] font-bold uppercase cursor-pointer">
                                                Retry Reinstall Task
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @else
                        <ol class="list-decimal pl-5 space-y-1 text-ink">
                            <li>Buka dashboard supplier, cari VPS dengan IP <strong class="font-mono">{{ $requestIp }}</strong>.</li>
                            <li>Jalankan reinstall/rebuild dengan OS yang diminta. Catat password SSH baru.</li>
                            <li>Jika stack bukan "Tanpa Control Panel", pasang stack yang diminta.</li>
                            <li>Isi form di bawah. Pelanggan otomatis menerima email. Password hanya tampil di dashboard pelanggan, tidak ikut email.</li>
                        </ol>

                        @php
                            $adminOsOptions = $requestVps->reinstallOsOptions();
                            $adminStackOptions = $requestVps->reinstallStackOptions();
                            $defaultOs = old('os', $requestData['os'] ?? $requestVps->os);
                            $defaultStack = old('control_panel', $requestData['control_panel'] ?? $requestVps->control_panel);
                            $defaultSshUser = old('ssh_username', $ticket->vpsInstance?->ssh_user ?? \App\Models\VpsInstance::defaultSshUsernameForOs((string) $defaultOs));
                        @endphp
                        <form action="{{ route('admin.tickets.complete-reinstall', $ticket->id) }}" method="POST" class="space-y-3 pt-3 border-t border-hairline"
                              data-confirm="Tandai reinstall selesai? Password root baru akan tersimpan dan pelanggan menerima email.">
                            @csrf
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-mono text-[11px] font-bold uppercase text-ink mb-1">OS terpasang</label>
                                    <select name="os" required class="w-full px-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                                        @foreach($adminOsOptions as $osKey => $osName)
                                            <option value="{{ $osKey }}" @selected((string) $defaultOs === (string) $osKey)>{{ $osName }}</option>
                                        @endforeach
                                    </select>
                                    @error('os') <p class="mt-1 font-mono text-[11px] text-ink font-bold">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block font-mono text-[11px] font-bold uppercase text-ink mb-1">Stack terpasang</label>
                                    @if($requestVps->hasFixedStack())
                                        <p class="px-3 py-2 border border-hairline bg-creamDark/40 text-ink">{{ $requestVps->control_panel_label }} (bawaan paket)</p>
                                    @else
                                        <select name="control_panel" required class="w-full px-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric">
                                            @foreach($adminStackOptions as $stackKey => $stackName)
                                                <option value="{{ $stackKey }}" @selected((string) $defaultStack === (string) $stackKey)>{{ $stackName }}</option>
                                            @endforeach
                                        </select>
                                        @error('control_panel') <p class="mt-1 font-mono text-[11px] text-ink font-bold">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                            </div>

                            <div>
                                <label class="block font-mono text-[11px] font-bold uppercase text-ink mb-1">Username SSH</label>
                                <input type="text" name="ssh_username" value="{{ $defaultSshUser }}" maxlength="32" pattern="[A-Za-z_][A-Za-z0-9._\-]{0,31}" autocomplete="off" spellcheck="false"
                                       class="w-full px-3 py-2 border border-hairline bg-white font-mono text-ink focus:outline-none focus:border-electric">
                                <p class="mt-1 text-muted">Biarkan bila username tidak berubah setelah reinstall.</p>
                                @error('ssh_username') <p class="mt-1 font-mono text-[11px] text-ink font-bold">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block font-mono text-[11px] font-bold uppercase text-ink mb-1">Password SSH baru</label>
                                <input type="text" name="root_password" required minlength="8" maxlength="128" autocomplete="off" spellcheck="false"
                                       class="w-full px-3 py-2 border border-hairline bg-white font-mono text-ink focus:outline-none focus:border-electric"
                                       placeholder="Password root dari dashboard supplier">
                                <p class="mt-1 text-muted">Disimpan terenkripsi. Pelanggan membukanya lewat tombol salin di tab Akses.</p>
                                @error('root_password') <p class="mt-1 font-mono text-[11px] text-ink font-bold">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block font-mono text-[11px] font-bold uppercase text-ink mb-1">Pesan ke pelanggan (opsional)</label>
                                <textarea name="message" rows="3" maxlength="2000" class="w-full px-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric"
                                          placeholder="Kosongkan untuk memakai pesan standar: reinstall selesai, password baru ada di tab Akses.">{{ old('message') }}</textarea>
                                @error('message') <p class="mt-1 font-mono text-[11px] text-ink font-bold">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                                    Tandai Reinstall Selesai
                                </button>
                            </div>
                        </form>
                    @endif
                @else
                    <ol class="list-decimal pl-5 space-y-1 text-ink">
                        <li>Buka dashboard supplier, cek status VPS dengan IP <strong class="font-mono">{{ $requestIp }}</strong>.</li>
                        <li>Jika server hang atau mati, jalankan reboot (atau force reboot) dari dashboard supplier.</li>
                        <li>Tunggu 1-2 menit, lalu pastikan port SSH ({{ $requestVps->ssh_port ?: 22 }}) bisa diakses kembali.</li>
                        <li>Balas tiket di bawah dengan status "Resolved". Pelanggan otomatis menerima email.</li>
                    </ol>
                @endif
            @endif
        </div>
    @endif

    <!-- Messages Timeline -->
    <div class="space-y-3">
        @foreach($ticket->messages as $msg)
            <div class="p-4 border {{ $msg->is_admin_reply ? 'bg-creamDark/60 border-ink' : 'bg-white border-hairline' }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 font-mono flex items-center justify-center font-bold text-xs {{ $msg->is_admin_reply ? 'bg-electric text-white' : 'bg-creamDark border border-hairline text-ink' }}">
                            {{ strtoupper(substr($msg->user->full_name, 0, 1)) }}
                        </div>
                        <span class="font-semibold text-xs text-ink">{{ $msg->user->full_name }}</span>
                        @if($msg->is_admin_reply)
                            <span class="px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase bg-ink text-cream">
                                Admin Owner
                            </span>
                        @endif
                    </div>
                    <span class="font-mono text-[11px] text-muted">
                        {{ $msg->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                    </span>
                </div>
                <div class="text-xs text-ink leading-relaxed whitespace-pre-line pl-8">
                    {{ $msg->message }}
                </div>
            </div>
        @endforeach
    </div>

    <!-- Reply Box -->
    <div class="bg-white border border-hairline p-5">
        <h3 class="font-display font-bold text-ink text-sm uppercase tracking-wider mb-3">Balas Sebagai Admin CravaCloud</h3>
        <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST" class="space-y-3 text-xs">
            @csrf
            <div>
                <textarea name="message" rows="4" required placeholder="Tuliskan petunjuk atau balasan teknis..."
                          class="w-full px-3 py-2 border border-hairline bg-white text-ink focus:outline-none focus:border-electric"></textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-ink cursor-pointer">
                <input type="checkbox" name="is_internal" value="1" class="border-ink accent-electric">
                <span>Catatan internal (tidak terlihat pelanggan)</span>
            </label>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-hairline">
                <div class="flex items-center gap-2">
                    <label class="font-mono text-[11px] font-bold uppercase text-muted">Status Tiket:</label>
                    <select name="status" class="px-2.5 py-1.5 border border-hairline font-mono text-xs bg-white text-ink focus:outline-none focus:border-electric">
                        <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved (Selesai)</option>
                        <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed (Tutup)</option>
                        <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                    </select>
                </div>

                <button type="submit" class="px-5 py-2.5 bg-electric hover:bg-electricHover text-white font-mono text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer">
                    Kirim Balasan Admin
                </button>
            </div>
        </form>
    </div>
</div>
@endsection