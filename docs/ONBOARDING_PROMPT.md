# Prompt onboarding untuk AI agent

Salin blok di bawah ke Claude (atau AI lain) yang baru. Syarat: repo harus **public** agar bisa dibaca tanpa login. Prompt ini identik di keempat repo.

---

```text
Kamu akan membantu Yudistio Izza Al Farisi (GitHub: Yudistioizza), full-stack developer
(Laravel, React, Node.js, PostgreSQL) di Bekasi, Indonesia. Ada 4 repo miliknya, semuanya public.
Balas dalam Bahasa Indonesia, singkat dan langsung ke inti.

LANGKAH PERTAMA (wajib, sebelum menulis kode atau memberi saran)
1. Tentukan repo yang akan dikerjakan dari tabel di bawah.
2. Baca TIGA file repo itu, berurutan: AGENTS.md (aturan + perintah), docs/TENTANG_APLIKASI.md
   (fungsi lengkap aplikasi), README.md. Pakai tool fetch/web dengan URL raw di bawah. Jika kamu
   punya akses terminal, lebih baik clone: git clone -b <branch> <URL repo>
3. Jika perlu konteks repo lain, baca AGENTS.md dan docs/TENTANG_APLIKASI.md miliknya juga.
4. Sebelum mengubah apa pun, ringkas pemahamanmu dalam 5 poin (fungsi aplikasi, pengguna,
   stack, cara menjalankan, aturan) dan ajukan pertanyaan yang belum jelas.

REPO
1) skripsi_kos: Sistem manajemen rumah kos (kamar, penghuni, tagihan sewa, bukti bayar, laporan).
   Laravel 12 + Livewire + PostgreSQL. Branch: redesign
   Repo : https://github.com/Yudistioizza/skripsi_kos/tree/redesign
   Clone: git clone -b redesign https://github.com/Yudistioizza/skripsi_kos.git
   Raw  : https://raw.githubusercontent.com/Yudistioizza/skripsi_kos/redesign/AGENTS.md
          https://raw.githubusercontent.com/Yudistioizza/skripsi_kos/redesign/docs/TENTANG_APLIKASI.md
          https://raw.githubusercontent.com/Yudistioizza/skripsi_kos/redesign/README.md

2) booking_meeting_room ("Ruang"): Booking ruang rapat enterprise (cek bentrok, persetujuan,
   booking berulang, laporan utilisasi, SSO + kalender Outlook). Laravel 12 + Livewire. Branch: redesign
   Repo : https://github.com/Yudistioizza/booking_meeting_room/tree/redesign
   Clone: git clone -b redesign https://github.com/Yudistioizza/booking_meeting_room.git
   Raw  : https://raw.githubusercontent.com/Yudistioizza/booking_meeting_room/redesign/AGENTS.md
          https://raw.githubusercontent.com/Yudistioizza/booking_meeting_room/redesign/docs/TENTANG_APLIKASI.md
          https://raw.githubusercontent.com/Yudistioizza/booking_meeting_room/redesign/README.md

3) portal_aplikasi: Katalog aplikasi perusahaan "AppHub" (monorepo: apphub-frontend React,
   apphub-api Node/Express, apphub-admin Laravel 11). Merek fiktif, data dummy. Branch: main
   Repo : https://github.com/Yudistioizza/portal_aplikasi
   Clone: git clone https://github.com/Yudistioizza/portal_aplikasi.git
   Raw  : https://raw.githubusercontent.com/Yudistioizza/portal_aplikasi/main/AGENTS.md
          https://raw.githubusercontent.com/Yudistioizza/portal_aplikasi/main/docs/TENTANG_APLIKASI.md
          https://raw.githubusercontent.com/Yudistioizza/portal_aplikasi/main/README.md

4) portal_dashboard_api: Portal dashboard BI berbasis role "InsightHub" (menu per role,
   dashboard di iframe aman, 5 dashboard contoh). Laravel 12 + AdminLTE. Merek fiktif, data dummy.
   Branch: main
   Repo : https://github.com/Yudistioizza/portal_dashboard_api
   Clone: git clone https://github.com/Yudistioizza/portal_dashboard_api.git
   Raw  : https://raw.githubusercontent.com/Yudistioizza/portal_dashboard_api/main/AGENTS.md
          https://raw.githubusercontent.com/Yudistioizza/portal_dashboard_api/main/docs/TENTANG_APLIKASI.md
          https://raw.githubusercontent.com/Yudistioizza/portal_dashboard_api/main/README.md

Catatan: skripsi_kos dan booking_meeting_room memakai branch `redesign` (perbaikan belum di-merge
ke main). Jika URL branch tidak ditemukan, coba `main`.

ATURAN UMUM (semua repo)
- Jangan push, merge ke main, deploy, atau membuat/menghapus repo tanpa konfirmasi eksplisit.
- Jangan commit .env, sandi, token, dump database, atau data pribadi.
- portal_aplikasi dan portal_dashboard_api adalah salinan bersih (merek fiktif, example.com, data
  dummy). Jangan menambahkan nama perusahaan nyata, IP internal, atau rahasia ke dalamnya.
- Teks UI semua aplikasi memakai Bahasa Indonesia.
- Pertahankan palet: skripsi_kos teal #004d50 + amber #f59e0b tanpa gradasi; booking_meeting_room
  teal #0d9488 + indigo #6366f1; portal teal #0d9488 + navy #0b1f33.
- Jalankan tes/lint/build sesuai AGENTS.md sebelum menyatakan selesai dan laporkan hasilnya apa
  adanya, termasuk yang gagal.
- Ubah seminimal mungkin; sederhana lebih baik daripada over-engineering.

Setelah langkah pertama selesai, tunggu tugas dari pemilik.
```

---

## Catatan untuk pemilik

- Ubah visibilitas ke public: GitHub, Settings, General, Danger Zone, Change visibility.
- Setelah `redesign` di-merge ke `main`, ganti `redesign` menjadi `main` pada prompt.
