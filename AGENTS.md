# AGENTS.md: booking_meeting_room ("Ruang")

Panduan untuk AI agent. Baca file ini dulu, lalu `README.md` (lengkap: fitur, SSO/Outlook, email, kebijakan, keamanan, arsitektur).
Penjelasan lengkap fungsi aplikasi: `docs/TENTANG_APLIKASI.md`.

## Ringkasan

Aplikasi booking ruang rapat untuk perusahaan besar: cari ruang kosong, pesan, cek bentrok, persetujuan ruang eksekutif, booking berulang, laporan utilisasi, undangan masuk ke Outlook. UI Bahasa Indonesia.

## Stack

Laravel 12, PHP 8.2+, Livewire 3 + Volt + Flux, Tailwind 4, Vite. DB: SQLite (bawaan), MySQL, PostgreSQL, atau SQL Server. Integrasi: Microsoft Entra ID (SSO) dan Microsoft Graph (kalender Outlook) lewat `socialiteproviders/microsoft-azure`. Palet: teal `#0d9488` + indigo `#6366f1`, netral zinc.

## Perintah

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # 36 pengguna, 12 ruang, 6 minggu jadwal (data demo)
npm install && npm run build
php artisan serve
composer test                     # test suite
./vendor/bin/pint
```

Login demo (kata sandi semua `password`): `rina.kusuma@nusantara.test` (Admin), `dimas.pratama@nusantara.test` (Manajer).

## Peta kode

- `app/Services/BookingService.php`: **satu-satunya tempat aturan booking** (bentrok, jam operasional, durasi, kapasitas, persetujuan, recurring). Web, API, dan impor memakai ini. Jangan menduplikasi aturan di komponen.
- `app/Services/{BookingNotifier,CalendarSync,ReportService}.php`, `Integrations/MicrosoftGraph.php`
- `app/Support/{Ics,Times}.php`, `app/Mail/BookingMail.php`
- `app/Livewire/{Booking,Admin,Auth,Settings}`: halaman
- `app/Models/`: 12 model (Room, Booking, Role, User, AuditLog, dst.)
- `routes/web.php`; `tests/Feature`; `.github/workflows` (lint + test)

## Aturan kerja

- Tambah aturan booking baru di `BookingService` dan sertakan test.
- Teks UI Bahasa Indonesia. Setiap aksi penting masuk audit log.
- Jangan jalankan `--seed` di produksi (data demo). Jangan commit `.env` atau rahasia Entra.
- Jalankan `composer test` dan Pint sebelum menyatakan selesai.
- Jangan push atau merge ke `main` tanpa konfirmasi pemilik.

## Repo terkait

Penjelasan lengkap aplikasi: `docs/TENTANG_APLIKASI.md`. Prompt onboarding + daftar repo: `docs/ONBOARDING_PROMPT.md`.
