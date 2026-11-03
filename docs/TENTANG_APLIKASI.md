# Tentang aplikasi: Ruang (booking_meeting_room)

## Fungsi

Aplikasi **booking ruang rapat** untuk perusahaan menengah dan besar. Karyawan mencari ruang kosong, memesan dalam hitungan detik, dan undangan langsung masuk ke Outlook/Google/Apple Calendar. Manajer menyetujui ruang eksekutif, admin mengelola ruang dan pengguna, dan semua orang bisa melihat utilisasi.

Masalah yang diselesaikan: rebutan ruang rapat, jadwal bentrok, ruang "dipesan tapi tidak dipakai", persetujuan lewat chat, dan tidak adanya data pemakaian ruang untuk GA/manajemen.

## Pengguna dan peran

| Peran | Bisa |
| --- | --- |
| **Employee** (Karyawan) | Cari ruang menurut waktu, jumlah orang, fasilitas (diurutkan paling pas); pesan dari kalender dengan klik area kosong; booking berulang (harian/mingguan/bulanan); ubah jadwal dan batalkan; undang tamu eksternal lewat email. |
| **Manager** | Semua milik karyawan + antrian persetujuan ruang eksekutif, riwayat keputusan, laporan utilisasi (jam tersibuk, departemen, ekspor CSV). |
| **Admin** (IT/GA) | Semua di atas + kelola ruangan (kapasitas, fasilitas, jam operasional, jeda antar rapat, batas durasi, perlu persetujuan), kelola pengguna dan peran, audit log, status dan tes integrasi, layar tablet di depan ruang. |

Pendaftaran publik ditutup: akun dibuat admin atau lewat SSO. Admin terakhir tidak bisa dinonaktifkan atau diturunkan.

## Fitur utama

- Cek bentrok langsung saat mengetik, lengkap dengan saran jam kosong.
- Booking menunggu persetujuan juga menahan slot.
- Booking berulang (seri) dengan batas maksimal.
- Notifikasi in-app, email dengan lampiran `.ics` (METHOD REQUEST/CANCEL), dan webhook Teams/Slack.
- Kalender pribadi berupa feed `.ics` rahasia per pengguna; tombol "Tambah ke Outlook / Google".
- Sinkron ke **Microsoft Outlook (Graph API)** sebagai event Exchange Online dengan ruang sebagai *resource*. Gangguan Graph tidak menghalangi booking (jatuh ke email + `.ics`).
- **SSO Microsoft Entra ID**: akun dibuat otomatis sebagai Employee, dibatasi domain.
- **Tablet di pintu** `/display/{ruang}`: tautan bertanda tangan, tanpa login, read-only, refresh otomatis.
- Audit log (siapa, apa, kapan, IP) untuk semua perubahan penting.

## Aturan bisnis (semua di `app/Services/BookingService.php`)

Satu tempat sehingga web, API, dan impor memakai aturan yang sama:
- Tidak boleh bentrok, termasuk jeda bersih-bersih per ruang; pemesanan dikunci per ruang di dalam transaksi database.
- Jam operasional per ruang (bisa beberapa rentang, mis. istirahat siang), durasi min/maks, kapasitas, tidak melewati tengah malam, tidak di masa lalu, batas pemesanan ke depan.
- Ruang eksekutif: karyawan menghasilkan status *menunggu*; manajer/admin langsung disetujui.
- Kebijakan bisa diubah di `config/booking.php` atau `.env`.

## Arsitektur singkat

`BookingService` (aturan) · `BookingNotifier` (in-app + email + webhook) · `CalendarSync` (Outlook, gagal-aman) · `Integrations/MicrosoftGraph` · `ReportService` (utilisasi, agregasi di PHP agar portabel antar database) · `Support/Ics` (penulis RFC 5545) · `app/Livewire/*` (halaman; `Booking\Form` dan `Booking\Detail` adalah dialog yang dipakai semua halaman).

## Teknologi

Laravel 12, PHP 8.2+, Livewire 3 + Volt + Flux, Tailwind 4, Vite. Database SQLite (bawaan), MySQL, PostgreSQL, atau SQL Server. Zona waktu aplikasi `Asia/Jakarta`; `.ics` dan Graph memakai UTC.

## Status

Fungsional, 53 test (bentrok, jeda, jam operasional, persetujuan, seri, ICS, Graph dengan `Http::fake`, SSO, akses per peran), CI lint + test. Data demo: 36 pengguna, 12 ruang, 6 minggu jadwal.

## Menjalankan dan login demo

Lihat `AGENTS.md`. Login demo (sandi `password`): `rina.kusuma@nusantara.test` (Admin), `dimas.pratama@nusantara.test` (Manajer).
