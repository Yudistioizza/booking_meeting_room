@php
    $tone = ['approved' => '#0d9488', 'created' => '#0d9488', 'updated' => '#4f46e5', 'pending' => '#b45309', 'approval' => '#b45309', 'rejected' => '#be123c', 'cancelled' => '#be123c'][$kind] ?? '#0f172a';
    $lead = [
        'created' => 'Ruang sudah dipesan untuk Anda.',
        'pending' => 'Permintaan Anda sudah diterima dan menunggu persetujuan.',
        'approval' => $b->creator->name . ' meminta persetujuan untuk booking berikut.',
        'approved' => 'Booking Anda disetujui. Ruang sudah diamankan.',
        'rejected' => 'Maaf, booking Anda tidak dapat disetujui.',
        'cancelled' => 'Booking berikut dibatalkan.',
        'updated' => 'Jadwal booking berikut berubah.',
    ][$kind];
@endphp
<!DOCTYPE html>
<html lang="id">
<body style="margin:0;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#0f172a">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px"><tr><td align="center">
  <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">
    <tr><td style="background:#0f172a;color:#ffffff;padding:16px 24px;font-size:14px;font-weight:600">{{ config('booking.company') }} · Ruang</td></tr>
    <tr><td style="padding:28px 24px 8px">
      <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $tone }}">{{ $heading }}</p>
      <h1 style="margin:0;font-size:22px;line-height:1.25">{{ $b->title }}</h1>
      <p style="margin:10px 0 0;color:#475569;font-size:14px">{{ $lead }}</p>
    </td></tr>
    <tr><td style="padding:16px 24px">
      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:8px;font-size:14px">
        <tr><td style="padding:12px 16px;color:#64748b;width:110px">Waktu</td><td style="padding:12px 16px;font-weight:600">{{ $b->start_time->translatedFormat('l, d F Y') }}<br>{{ $b->start_time->format('H:i') }} – {{ $b->end_time->format('H:i') }} {{ config('app.timezone') === 'Asia/Jakarta' ? 'WIB' : config('app.timezone') }}</td></tr>
        <tr><td style="padding:12px 16px;color:#64748b;border-top:1px solid #e2e8f0">Ruang</td><td style="padding:12px 16px;border-top:1px solid #e2e8f0;font-weight:600">{{ $b->room->name }}@if ($b->room->location)<br><span style="font-weight:400;color:#475569">{{ $b->room->location }}</span>@endif</td></tr>
        <tr><td style="padding:12px 16px;color:#64748b;border-top:1px solid #e2e8f0">Pemesan</td><td style="padding:12px 16px;border-top:1px solid #e2e8f0">{{ $b->creator->name }}@if ($b->creator->department) · {{ $b->creator->department }}@endif</td></tr>
        @if ($b->attendee_count > 1)<tr><td style="padding:12px 16px;color:#64748b;border-top:1px solid #e2e8f0">Peserta</td><td style="padding:12px 16px;border-top:1px solid #e2e8f0">{{ $b->attendee_count }} orang</td></tr>@endif
      </table>
      @if ($note)<p style="margin:16px 0 0;padding:12px 16px;background:#f8fafc;border-left:3px solid {{ $tone }};font-size:14px;color:#334155"><b>Catatan:</b> {{ $note }}</p>@endif
    </td></tr>
    <tr><td style="padding:8px 24px 28px">
      @if ($kind === 'approval')
        <a href="{{ route('approvals') }}" style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 20px;border-radius:8px">Tinjau permintaan</a>
      @elseif (! in_array($kind, ['cancelled', 'rejected'], true))
        <a href="{{ $links['outlook'] }}" style="display:inline-block;background:#0d9488;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 18px;border-radius:8px;margin:0 8px 8px 0">Tambah ke Outlook</a>
        <a href="{{ $links['google'] }}" style="display:inline-block;background:#ffffff;color:#0f172a;text-decoration:none;font-weight:600;font-size:14px;padding:10px 18px;border:1px solid #cbd5e1;border-radius:8px;margin:0 8px 8px 0">Google Calendar</a>
        <p style="margin:8px 0 0;font-size:12px;color:#64748b">File undangan (.ics) terlampir; buka untuk menambahkan ke aplikasi kalender apa pun.</p>
      @endif
    </td></tr>
  </table>
  <p style="max-width:560px;margin:14px 0 0;font-size:12px;color:#94a3b8">Email otomatis dari sistem booking ruang rapat {{ config('booking.company') }}.</p>
</td></tr></table>
</body>
</html>
