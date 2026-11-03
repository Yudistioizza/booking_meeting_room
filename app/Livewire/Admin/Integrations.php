<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Services\Integrations\MicrosoftGraph;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Read-only status of every connection, with one-click tests. Secrets stay in .env. */
#[Layout('components.layouts.app')]
#[Title('Integrasi')]
class Integrations extends Component
{
    public array $tests = [];

    public function testMail(): void
    {
        $to = auth()->user()->email;
        try {
            Mail::raw("Ini email uji dari Ruang ({$_SERVER['HTTP_HOST']}). Jika Anda menerimanya, pengiriman email berfungsi.", fn ($m) => $m->to($to)->subject('Uji email · Ruang'));
            $this->tests['mail'] = [true, "Terkirim ke $to" . (config('mail.default') === 'log' ? ' (mailer "log": cek storage/logs)' : '.')];
        } catch (\Throwable $e) {
            $this->tests['mail'] = [false, $e->getMessage()];
        }
        AuditLog::record('integration.tested', null, 'Uji email SMTP');
    }

    public function testGraph(MicrosoftGraph $graph): void
    {
        if (! $graph->configured()) {
            $this->tests['graph'] = [false, 'Belum dikonfigurasi. Isi GRAPH_* / AZURE_* dan GRAPH_ORGANIZER di .env.'];

            return;
        }
        try {
            $this->tests['graph'] = [true, 'Terhubung. Kalender milik: ' . $graph->test()];
        } catch (\Throwable $e) {
            $this->tests['graph'] = [false, $e->getMessage()];
        }
        AuditLog::record('integration.tested', null, 'Uji koneksi Microsoft Graph');
    }

    public function testWebhook(): void
    {
        $url = config('services.webhook.url');
        if (! $url) {
            $this->tests['webhook'] = [false, 'BOOKING_WEBHOOK_URL belum diisi.'];

            return;
        }
        try {
            $res = Http::timeout(8)->post($url, ['text' => '✅ Uji notifikasi dari Ruang berhasil.']);
            $this->tests['webhook'] = [$res->successful(), $res->successful() ? 'Pesan uji terkirim.' : 'Ditolak: HTTP ' . $res->status()];
        } catch (\Throwable $e) {
            $this->tests['webhook'] = [false, $e->getMessage()];
        }
    }

    public function render(MicrosoftGraph $graph)
    {
        return view('livewire.admin.integrations', [
            'mail' => ['mailer' => config('mail.default'), 'host' => config('mail.mailers.' . config('mail.default') . '.host'), 'from' => config('mail.from.address'), 'live' => ! in_array(config('mail.default'), ['log', 'array'], true)],
            'sso' => ['on' => filled(config('services.azure.client_id')), 'redirect' => config('services.azure.redirect') ?: url('/login/azure/callback'), 'domains' => config('booking.sso_domains')],
            'graphOn' => $graph->configured(),
            'organizer' => config('services.graph.organizer'),
            'webhookOn' => filled(config('services.webhook.url')),
            'synced' => Booking::whereNotNull('outlook_event_id')->count(),
            'sent' => EmailLog::where('status', 'sent')->where('created_at', '>=', now()->subDays(30))->count(),
            'failed' => EmailLog::where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count(),
            'logs' => EmailLog::with('booking:id,title')->latest('id')->limit(8)->get(),
            'queue' => config('queue.default'),
        ]);
    }
}
