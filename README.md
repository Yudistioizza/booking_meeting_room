<div align="center">

# Ruang

**Booking ruang rapat untuk perusahaan: cari ruang kosong, pesan dalam hitungan detik, undangan langsung masuk ke Outlook.**

Laravel 12 · Livewire 3 · Tailwind 4 · SQLite / MySQL / PostgreSQL / SQL Server

</div>

---

## Apa yang bisa dilakukan

| Untuk | Fitur |
|---|---|
| **Karyawan** | Cari ruang berdasarkan waktu, jumlah orang, dan fasilitas (hasil diurutkan paling pas) · pesan dari kalender dengan klik area kosong · pengecekan bentrok langsung saat mengetik, lengkap dengan saran jam kosong · booking berulang (harian / mingguan / bulanan) · ubah jadwal & batalkan · undang tamu eksternal lewat email |
| **Manajer** | Antrian persetujuan untuk ruang eksekutif · riwayat keputusan · laporan utilisasi, jam tersibuk, departemen, ekspor CSV |
| **Admin IT / GA** | Kelola ruangan (kapasitas, fasilitas, jam operasional, jeda antar rapat, batas durasi, perlu persetujuan) · kelola pengguna & peran · audit log · status & tes integrasi · tablet status di depan ruang |

### Aturan yang ditegakkan di server

Satu tempat (`App\Services\BookingService`), sehingga web, API, dan impor memakai aturan yang sama:

- tidak boleh bentrok (booking **menunggu persetujuan** juga menahan slot), termasuk jeda bersih-bersih per ruang
- jam operasional per ruang (beberapa rentang, mis. istirahat siang), durasi min/maks, kapasitas, tidak melewati tengah malam, tidak di masa lalu, batas pemesanan ke depan
- ruang eksekutif: karyawan → *menunggu*, manajer/admin → langsung disetujui; tidak bisa menyetujui booking sendiri
- hanya pemesan atau admin yang boleh membatalkan / memindahkan; memindahkan ke ruang yang butuh persetujuan mengulang persetujuan
- booking berulang: tanggal yang bentrok dilewati dan dilaporkan, atau seluruh seri ditolak (pilihan pemesan)
- pemesanan dikunci per ruang di dalam transaksi database, jadi dua orang tidak bisa merebut slot yang sama

## Terhubung ke mana saja

| Integrasi | Cara kerja | Perlu konfigurasi? |
|---|---|---|
| **Email + undangan .ics** | Konfirmasi, permintaan persetujuan, keputusan, pembatalan, perubahan jadwal. Lampiran `.ics` (METHOD REQUEST/CANCEL, UID & SEQUENCE benar) dibuka di Outlook, Google, Apple | SMTP biasa |
| **Kalender pribadi (.ics feed)** | Tiap pengguna punya URL langganan rahasia di Profil; kalender di ponsel/PC selalu terbarui | Tidak |
| **Link "Tambah ke Outlook / Google"** | Tombol sekali klik di detail booking dan email | Tidak |
| **Microsoft Outlook (Graph API)** | Setiap booking jadi event di Exchange Online, tamu diundang langsung dari Outlook, ruang ditambahkan sebagai *resource*. Ubah/batalkan ikut tersinkron. Gangguan Graph tidak pernah menghalangi booking: otomatis jatuh ke email + `.ics` | Entra app registration |
| **Microsoft SSO (Entra ID)** | Masuk dengan akun kantor, akun dibuat otomatis sebagai *Employee*, dibatasi domain, akun nonaktif tetap ditolak | Entra app registration |
| **Teams / Slack** | Ringkasan booking ke channel lewat incoming webhook | URL webhook |
| **Tablet di pintu** | `/display/{ruang}` dengan tautan bertanda tangan, tanpa login, read-only, auto-refresh | Tidak |

Halaman **Admin → Integrasi** menampilkan status tiap koneksi dan tombol uji.

## Instalasi

Butuh PHP 8.2+, Composer, Node 20+.

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite          # atau atur DB_* untuk MySQL / PostgreSQL
php artisan migrate --seed              # seed = data demo (36 pengguna, 12 ruang, 6 minggu jadwal)
npm install && npm run build
php artisan serve
```

Login demo (kata sandi semuanya `password`):

| Peran | Email |
|---|---|
| Admin | `rina.kusuma@nusantara.test` |
| Manajer | `dimas.pratama@nusantara.test` |
| Karyawan | pilih salah satu dari menu Admin → Pengguna |

> Untuk produksi **jangan** jalankan `--seed` (itu data demo). Buat peran `Admin`, `Manager`, `Employee` dan akun admin pertama lewat `php artisan tinker`, atau masuk dengan SSO lalu promosikan akun.

### Produksi

```bash
APP_ENV=production APP_DEBUG=false
QUEUE_CONNECTION=database   # lalu: php artisan queue:work
php artisan config:cache route:cache view:cache
```

Gunakan MySQL/PostgreSQL di produksi: penguncian per ruang (`SELECT … FOR UPDATE`) hanya efektif pada database server.

### Microsoft Entra ID (SSO + Outlook)

1. Entra ID → App registrations → **New registration**. Redirect URI (Web): `https://ruang.perusahaan.com/login/azure/callback`
2. Certificates & secrets → buat **client secret**.
3. API permissions → Microsoft Graph → **Application** → `Calendars.ReadWrite` → *Grant admin consent*.
4. Isi `.env`:

```env
AZURE_CLIENT_ID=...
AZURE_CLIENT_SECRET=...
AZURE_TENANT_ID=...
AZURE_REDIRECT_URI="${APP_URL}/login/azure/callback"
SSO_ALLOWED_DOMAINS=perusahaan.com
GRAPH_ORGANIZER=rooms@perusahaan.com     # mailbox pemilik event
```

Tambahkan email resource Exchange tiap ruang di **Admin → Ruangan → Email kalender ruang** agar terlihat sibuk di Outlook Room Finder.
Batasi hak aplikasi ke satu mailbox dengan [ApplicationAccessPolicy](https://learn.microsoft.com/graph/auth-limit-mailbox-access) bila kebijakan keamanan mengharuskan.

### Email

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=rooms@perusahaan.com
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=rooms@perusahaan.com
```

## Kebijakan yang bisa diubah

`config/booking.php` (atau lewat `.env`): jam operasional standar, hari kerja, durasi maksimal, batas pemesanan ke depan, maksimal pertemuan per seri, nama peran, domain SSO.

## Keamanan

- Pendaftaran publik ditutup; akun dibuat admin atau SSO. Akun nonaktif tidak bisa masuk (kata sandi maupun SSO).
- Peran: **Employee** (memesan) · **Manager** (+ persetujuan, laporan) · **Admin** (+ ruangan, pengguna, integrasi, audit). Admin terakhir tidak bisa dinonaktifkan atau diturunkan.
- Semua perubahan penting dicatat di audit log (siapa, apa, kapan, IP).
- URL kalender pribadi memakai token 48 karakter yang bisa diganti kapan saja; tautan layar pintu bertanda tangan.
- Throttling pada login dan feed kalender. Rahasia integrasi hanya di `.env`, tidak pernah di database.

## Arsitektur singkat

```
app/Services/BookingService.php         aturan bisnis: bentrok, jam, persetujuan, seri, batal, pindah
app/Services/BookingNotifier.php        notifikasi in-app + email (.ics) + webhook
app/Services/CalendarSync.php           sinkron Outlook, gagal-aman
app/Services/Integrations/MicrosoftGraph.php
app/Services/ReportService.php          utilisasi (agregasi di PHP, portabel antar database)
app/Support/Ics.php                     penulis RFC 5545 (UTC, line folding, escape)
app/Livewire/                           halaman; Booking\Form & Booking\Detail adalah dialog tunggal yang dipakai semua halaman
```

## Pengujian

```bash
php artisan test        # 53 test, termasuk bentrok, jeda, jam operasional, persetujuan, seri, ICS, Graph (Http::fake), SSO, akses per peran
```

## Catatan

- Tabel `email_templates` dari skema awal belum dipakai; email memakai template Blade `resources/views/emails/booking.blade.php`.
- Zona waktu aplikasi `Asia/Jakarta` (`APP_TIMEZONE`). Undangan `.ics` dan Graph selalu memakai UTC sehingga benar di zona waktu mana pun.
