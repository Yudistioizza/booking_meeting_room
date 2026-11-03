@php
    $cards = [
        'sso' => ['Single sign-on Microsoft', 'Karyawan masuk dengan akun kantor. Akun dibuat otomatis saat pertama masuk.', 'identification', $sso['on']],
        'graph' => ['Kalender Outlook (Microsoft Graph)', 'Setiap booking menjadi event di Outlook dan mengundang peserta langsung dari Exchange.', 'calendar-days', $graphOn],
        'mail' => ['Email & undangan .ics', 'Konfirmasi, persetujuan, pembatalan, plus file undangan untuk kalender apa pun.', 'envelope', $mail['live']],
        'webhook' => ['Teams / Slack', 'Kirim ringkasan booking ke channel tim.', 'chat-bubble-left-right', $webhookOn],
    ];
@endphp
<div class="space-y-6 max-w-5xl">
    <div class="grid gap-4 md:grid-cols-2">
        @foreach ($cards as $key => [$title, $desc, $icon, $on])
            <section class="card card-pad flex flex-col gap-4" wire:key="int-{{ $key }}">
                <div class="flex items-start gap-3">
                    <span class="size-10 shrink-0 grid place-items-center rounded-lg {{ $on ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-500' }}"><flux:icon :name="$icon" class="size-5" /></span>
                    <div class="min-w-0 flex-1"><h2 class="font-bold">{{ $title }}</h2><p class="text-sm text-slate-500 mt-0.5">{{ $desc }}</p></div>
                    <span class="badge {{ $on ? 'badge-brand' : 'badge-slate' }}">{{ $on ? 'Aktif' : 'Belum aktif' }}</span>
                </div>

                <dl class="text-sm space-y-1.5 text-slate-600">
                    @if ($key === 'sso')
                        <div class="flex justify-between gap-4"><dt>Redirect URI</dt><dd class="font-mono text-xs text-right break-all">{{ $sso['redirect'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Domain diizinkan</dt><dd>{{ $sso['domains'] ? implode(', ', $sso['domains']) : 'Semua akun tenant' }}</dd></div>
                    @elseif ($key === 'graph')
                        <div class="flex justify-between gap-4"><dt>Kalender organizer</dt><dd class="font-mono text-xs">{{ $organizer ?: '—' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Booking tersinkron</dt><dd class="font-semibold tnum">{{ $synced }}</dd></div>
                    @elseif ($key === 'mail')
                        <div class="flex justify-between gap-4"><dt>Mailer</dt><dd class="font-mono text-xs">{{ $mail['mailer'] }}{{ $mail['host'] ? ' · ' . $mail['host'] : '' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>Pengirim</dt><dd class="font-mono text-xs">{{ $mail['from'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt>30 hari terakhir</dt><dd><b class="tnum">{{ $sent }}</b> terkirim · <b class="tnum {{ $failed ? 'text-busy-700' : '' }}">{{ $failed }}</b> gagal</dd></div>
                    @else
                        <div class="flex justify-between gap-4"><dt>Webhook</dt><dd>{{ $webhookOn ? 'Terpasang' : 'Isi BOOKING_WEBHOOK_URL' }}</dd></div>
                    @endif
                </dl>

                @if (isset($tests[$key]))
                    <p class="flash {{ $tests[$key][0] ? 'bg-brand-50 border-brand-100 text-brand-700' : 'bg-busy-50 border-busy-100 text-busy-700' }}"><flux:icon :name="$tests[$key][0] ? 'check-circle' : 'exclamation-triangle'" class="size-5 shrink-0" /> {{ $tests[$key][1] }}</p>
                @endif

                <div class="mt-auto flex flex-wrap items-center gap-2">
                    @if ($key === 'sso')
                        @if ($on)<a href="{{ route('login.azure') }}" class="btn btn-secondary btn-sm">Coba masuk dengan Microsoft</a>@endif
                    @else
                        <button wire:click="test{{ ucfirst($key) }}" wire:loading.attr="disabled" wire:target="test{{ ucfirst($key) }}" class="btn btn-secondary btn-sm"><span wire:loading.remove wire:target="test{{ ucfirst($key) }}">Tes koneksi</span><span wire:loading wire:target="test{{ ucfirst($key) }}">Menguji…</span></button>
                    @endif
                </div>
            </section>
        @endforeach
    </div>

    <section class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between"><h2 class="font-bold">Email terakhir</h2><span class="text-xs text-slate-500">Antrian: <b>{{ $queue }}</b></span></div>
        <div class="overflow-x-auto"><table class="table">
            <thead><tr><th>Penerima</th><th>Booking</th><th>Status</th><th>Waktu</th></tr></thead>
            <tbody>
                @forelse ($logs as $l)
                    <tr><td class="font-mono text-xs">{{ $l->to }}</td><td>{{ $l->booking?->title ?? '—' }}</td><td><span class="badge {{ $l->status === 'sent' ? 'badge-brand' : 'badge-busy' }}">{{ $l->status === 'sent' ? 'Terkirim' : 'Gagal' }}</span></td><td class="text-slate-500 whitespace-nowrap">{{ $l->created_at->translatedFormat('d M H:i') }}</td></tr>
                @empty
                    <tr><td colspan="4"><div class="empty">Belum ada email.</div></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>

    <section class="card card-pad">
        <h2 class="font-bold">Panduan penyiapan</h2>
        <div class="mt-3 grid gap-6 md:grid-cols-2 text-sm text-slate-600">
            <div>
                <h3 class="font-semibold text-slate-900">1. Microsoft SSO & Outlook</h3>
                <ol class="mt-2 list-decimal pl-5 space-y-1.5">
                    <li>Entra ID → App registrations → <b>New registration</b>.</li>
                    <li>Redirect URI (Web): <span class="font-mono text-xs">{{ $sso['redirect'] }}</span></li>
                    <li>Certificates &amp; secrets → buat <b>client secret</b>.</li>
                    <li>API permissions → Microsoft Graph → <b>Application</b>: <span class="font-mono text-xs">Calendars.ReadWrite</span> → Grant admin consent.</li>
                    <li>Isi <span class="font-mono text-xs">AZURE_CLIENT_ID/SECRET/TENANT_ID</span> dan <span class="font-mono text-xs">GRAPH_ORGANIZER</span> di <span class="font-mono text-xs">.env</span>.</li>
                </ol>
            </div>
            <div>
                <h3 class="font-semibold text-slate-900">2. Email</h3>
                <ol class="mt-2 list-decimal pl-5 space-y-1.5">
                    <li>Atur <span class="font-mono text-xs">MAIL_MAILER=smtp</span> beserta host, port, user, dan password.</li>
                    <li>Untuk Microsoft 365: <span class="font-mono text-xs">smtp.office365.com:587</span> dengan SMTP AUTH aktif pada mailbox pengirim.</li>
                    <li>Jalankan <span class="font-mono text-xs">php artisan queue:work</span> bila memakai antrian.</li>
                </ol>
                <h3 class="font-semibold text-slate-900 mt-5">3. Kalender pribadi</h3>
                <p class="mt-2">Setiap pengguna punya tautan langganan <span class="font-mono text-xs">.ics</span> di <a href="{{ route('settings.profile') }}" class="font-semibold text-brand-700 hover:underline" wire:navigate>Profil</a>. Berfungsi di Outlook, Google, dan Apple tanpa konfigurasi server.</p>
            </div>
        </div>
    </section>
</div>
