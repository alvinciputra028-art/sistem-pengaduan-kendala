# Sistem Pengaduan Kendala

Dokumen ini mencatat keputusan arsitektur, aturan bisnis, status implementasi,
pengujian, dan backlog proyek Laravel ini. Status terakhir diperbarui pada
**28 Oktober 2026**.

## Keputusan arsitektur

- Framework: Laravel dengan modular monolith.
- Database pengembangan/produksi: MySQL/MariaDB.
- UI saat ini: Blade; Livewire atau broadcasting dapat ditambahkan kemudian.
- Role aplikasi: `resident`, `staff`, dan `manager`.
- Akses laporan, triase, komentar, dan lampiran menggunakan Policy terpusat.
- Nomor tiket adalah identitas manusia; ID database tetap menjadi identitas
  internal.
- Laporan kamar menggunakan kamar pelapor. Laporan area bersama menggunakan
  deskripsi lokasi.
- Kategori disimpan sebagai tabel dinamis, bukan `ENUM`.
- Lampiran disimpan di storage non-public dan diakses melalui endpoint yang
  memeriksa hak akses.

## Aturan akses dan privasi

### Resident

- Dapat melihat laporan miliknya.
- Dapat melihat laporan `public_building` pada gedung yang sama.
- Tidak dapat melihat laporan privat milik penghuni gedung lain.
- Dapat membuat laporan untuk gedung dan kamar yang terhubung ke akunnya.
- Dapat menutup laporan miliknya ketika status `waiting_confirmation`.
- Dapat membuka kembali laporan miliknya ketika status `closed`.

### Staff

- Hanya dapat melihat dan menangani laporan yang memiliki assignment aktif
  kepadanya.
- Dapat mengubah status laporan yang di-assign melalui transisi yang valid.
- Dapat mengubah prioritas dan estimasi selama laporan belum
  `waiting_confirmation` atau `closed`.
- Tidak dapat melakukan assignment.

### Manager

- Dapat melihat seluruh laporan.
- Dapat melakukan triase, assignment, perubahan prioritas, dan perubahan
  estimasi.
- Dapat menutup laporan `waiting_confirmation` secara administratif.
- Dapat membuka kembali laporan `closed` jika penghuni tidak dapat bertindak.

Identitas dan nomor kamar penghuni hanya ditampilkan sesuai kebutuhan:
manajer dan petugas yang di-assign dapat melihat detail operasional, sedangkan
penghuni lain tidak melihat nomor kamar.

## Alur status

Status yang digunakan:

```text
submitted
  -> triaged
  -> assigned
  -> in_progress
  -> waiting_confirmation
  -> closed
```

Alur tambahan:

```text
submitted -> assigned
triaged -> in_progress
reopened -> triaged | assigned | in_progress
closed -> reopened
```

`waiting_confirmation` berarti petugas menyatakan pekerjaan selesai. Status ini
wajib dilalui sebelum laporan ditutup. Penghuni atau manajer yang berwenang
kemudian menutup laporan. Jika masalah belum selesai, laporan dapat dibuka
kembali menjadi `reopened`.

Prioritas tidak dapat diubah pada `waiting_confirmation` atau `closed`.
Perubahan prioritas wajib menyertakan alasan dan dicatat pada histori.

## Estimasi penanganan

Laporan memiliki tiga field estimasi:

- `estimated_response_at`: perkiraan kapan petugas merespons atau mulai
  menangani laporan.
- `estimated_resolution_at`: perkiraan kapan pekerjaan selesai.
- `estimate_note`: catatan tambahan untuk penghuni.

Estimasi dapat diubah oleh manajer atau petugas yang di-assign selama laporan
belum `waiting_confirmation` atau `closed`. Nilai terbaru menggantikan nilai
sebelumnya; histori estimasi belum menjadi fitur MVP.

Estimasi yang tersedia ditampilkan pada detail laporan penghuni. Estimasi
terlewati belum memicu sanksi otomatis atau notifikasi SLA.

## Struktur data inti

Entitas utama:

```text
users
buildings
rooms
service_units
categories
reports
report_assignments
report_comments
attachments
report_status_histories
report_priority_histories
notifications
```

### `users`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas pengguna. |
| `name` | string | Nama pengguna. |
| `email` | string, unique | Kredensial login. |
| `email_verified_at` | timestamp, nullable | Waktu verifikasi email. |
| `password` | string | Password terenkripsi. |
| `remember_token` | string, nullable | Token remember me Laravel. |
| `role` | string(20), index, default `resident` | `resident`, `staff`, atau `manager`. |
| `phone` | string(30), nullable | Nomor telepon, tidak ditampilkan otomatis. |
| `building_id` | FK `buildings`, nullable | Gedung resident. Null untuk manager/staff yang cakupannya melalui pivot. |
| `room_id` | FK `rooms`, nullable | Kamar resident. |
| `is_active` | boolean, default true | Status akun. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

### `buildings`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas gedung. |
| `name` | string, unique | Nama gedung. |
| `is_active` | boolean, default true | Status gedung. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

### `rooms`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas kamar. |
| `building_id` | FK `buildings`, cascade delete | Gedung pemilik kamar. |
| `room_number` | string(20) | Nomor kamar. |
| `is_active` | boolean, default true | Status kamar. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Unique key: `building_id` + `room_number`.

### `service_units`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas unit layanan. |
| `name` | string, unique | Contoh: Jaringan, Pemeliharaan, Keamanan. |
| `is_active` | boolean, default true | Status unit. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

### `categories`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas kategori. |
| `name` | string, unique | Jenis kendala. |
| `default_service_unit_id` | FK `service_units`, nullable | Unit layanan rekomendasi. |
| `default_visibility` | string(30), default `private` | Visibilitas default kategori. |
| `is_active` | boolean, default true | Kategori dapat digunakan atau tidak. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

### `reports`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas internal laporan. |
| `ticket_number` | string(40), unique | Nomor tiket untuk pengguna. |
| `reporter_id` | FK `users`, restrict delete | Penghuni pelapor. |
| `building_id` | FK `buildings`, restrict delete | Lokasi kejadian. |
| `room_id` | FK `rooms`, nullable | Kamar lokasi kejadian. |
| `category_id` | FK `categories`, restrict delete | Kategori kendala. |
| `title` | string | Judul laporan. |
| `description` | text | Uraian kendala. |
| `location_type` | string(20) | `room`, `common_area`, atau `building`. |
| `location_description` | string, nullable | Detail lokasi area bersama/gedung. |
| `visibility` | string(30), index, default `private` | `public_building` atau `participants`. |
| `identity_visibility` | string(30), default `visible` | Aturan tampilan identitas pelapor. |
| `impact_score` | unsigned tinyint, default 1 | Skor dampak. |
| `urgency_score` | unsigned tinyint, default 1 | Skor urgensi. |
| `safety_risk_score` | unsigned tinyint, default 0 | Skor risiko keselamatan. |
| `priority` | string(20), index, default `normal` | `low`, `normal`, `high`, `critical`. |
| `priority_score` | unsigned smallint, default 0 | Skor hasil asesmen. |
| `status` | string(30), index, default `submitted` | Status workflow laporan. |
| `estimated_response_at` | timestamp, nullable | Perkiraan mulai direspons/ditangani. |
| `estimated_resolution_at` | timestamp, nullable | Perkiraan pekerjaan selesai. |
| `estimate_note` | text, nullable | Catatan estimasi untuk penghuni. |
| `resolved_at` | timestamp, nullable | Waktu petugas menyatakan selesai. |
| `closed_at` | timestamp, nullable | Waktu laporan dikonfirmasi/ditutup. |
| `retained_until` | timestamp, nullable | Batas retensi, dua tahun setelah ditutup. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Index tambahan: `building_id` + `visibility` dan `reporter_id` + `status`.

`report_assignments` memisahkan assignment dari laporan agar histori
penugasan tetap tersedia dan assignment aktif dapat dicabut.

### `report_assignments`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas assignment. |
| `report_id` | FK `reports`, cascade delete | Laporan yang ditugaskan. |
| `service_unit_id` | FK `service_units`, restrict delete | Unit layanan. |
| `staff_id` | FK `users`, nullable | Petugas yang ditugaskan. |
| `assigned_by` | FK `users`, nullable | Manajer/pengguna yang membuat assignment. |
| `assigned_at` | timestamp | Waktu assignment. |
| `unassigned_at` | timestamp, nullable | Waktu assignment dicabut. Null berarti aktif. |
| `note` | text, nullable | Catatan assignment. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Index: `report_id` + `unassigned_at` dan `staff_id` + `unassigned_at`.

### `report_comments`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas komentar. |
| `report_id` | FK `reports`, cascade delete | Laporan terkait. |
| `user_id` | FK `users`, restrict delete | Pengirim komentar. |
| `body` | text | Isi komentar. |
| `visibility` | string(30), default `participants` | Cakupan pembaca komentar. |
| `deleted_by` | FK `users`, nullable | Pengguna yang menghapus secara soft delete. |
| `deleted_at` | timestamp, nullable | Waktu soft delete. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Index: `report_id` + `created_at`.

`report_comments` digunakan untuk percakapan pada laporan. Komentar yang
terhapus tidak ditampilkan oleh model aplikasi.

### `attachments`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas lampiran. |
| `report_id` | FK `reports`, nullable, cascade delete | Laporan langsung terkait. |
| `comment_id` | FK `report_comments`, nullable, cascade delete | Komentar terkait. |
| `uploaded_by` | FK `users`, restrict delete | Pengunggah. |
| `storage_path` | string | Path internal pada storage. |
| `original_name` | string | Nama file asli untuk tampilan/download. |
| `mime_type` | string(100) | MIME type file. |
| `size` | unsigned bigint | Ukuran byte. |
| `checksum` | string(64) | Checksum SHA-256. |
| `retained_until` | timestamp, nullable | Batas retensi lampiran. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Satu lampiran terkait ke laporan atau komentar. Endpoint download wajib
memeriksa akses laporan induknya.

### `report_status_histories`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas histori. |
| `report_id` | FK `reports`, cascade delete | Laporan terkait. |
| `from_status` | string(30), nullable | Status sebelumnya. |
| `to_status` | string(30) | Status tujuan. |
| `changed_by` | FK `users`, nullable | Pelaku perubahan. |
| `note` | text, nullable | Catatan/alasan perubahan. |
| `created_at`, `updated_at` | timestamps | Waktu perubahan. |

Index: `report_id` + `created_at`.

### `report_priority_histories`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas histori. |
| `report_id` | FK `reports`, cascade delete | Laporan terkait. |
| `old_priority` | string(20), nullable | Prioritas sebelumnya. |
| `new_priority` | string(20) | Prioritas baru. |
| `score` | unsigned smallint | Skor saat perubahan. |
| `changed_by` | FK `users`, nullable | Pelaku perubahan. |
| `reason` | text, nullable | Alasan perubahan. |
| `created_at`, `updated_at` | timestamps | Waktu perubahan. |

Index: `report_id` + `created_at`.

### `notifications`

| Atribut | Tipe/aturan | Keterangan |
|---|---|---|
| `id` | bigint, PK | Identitas notifikasi. |
| `user_id` | FK `users`, cascade delete | Penerima. |
| `type` | string | Jenis notifikasi. |
| `data` | JSON | Payload notifikasi. |
| `read_at` | timestamp, nullable | Waktu dibaca. |
| `created_at`, `updated_at` | timestamps | Audit waktu. |

Index: `user_id` + `read_at`.

### Tabel pivot

| Tabel | Atribut | Fungsi |
|---|---|---|
| `service_unit_building` | `service_unit_id`, `building_id` | Cakupan gedung unit layanan. Primary key gabungan. |
| `user_service_unit` | `user_id`, `service_unit_id` | Unit layanan yang dapat ditangani petugas. Primary key gabungan. |
| `user_building` | `user_id`, `building_id` | Cakupan gedung petugas. Primary key gabungan. |

### Tabel bawaan framework

Laravel juga memiliki tabel pendukung `password_reset_tokens`, `sessions`,
`cache`, `cache_locks`, `jobs`, `job_batches`, dan `failed_jobs`. Tabel-tabel
ini bukan entitas bisnis dan mengikuti migration bawaan Laravel.

Saat laporan ditutup, `retained_until` pada laporan dan lampiran diisi dua
tahun setelah `closed_at`. Saat laporan dibuka kembali, nilai retensi
dikosongkan.

## Status implementasi

| Tahap | Status | Keterangan |
|---|---|---|
| 0. Kebijakan akses dan alur | ✅ Selesai untuk aturan inti | Policy terpusat, pembatasan status, close/reopen, prioritas, retensi, dan histori sudah tersedia. |
| 1. Fondasi Laravel | ✅ Selesai untuk MVP | Login, role, user, gedung, kamar, kategori, unit layanan, dashboard dasar, dan migration tersedia. |
| 2. Tiket inti | ✅ Selesai untuk cakupan MVP | Pembuatan laporan, lokasi, visibilitas, assignment, lampiran, validasi akses, estimasi, dan histori tersedia. |
| 3. Triase dan prioritas | 🟢 MVP selesai | Skor prioritas, triase, assignment, estimasi, dan transisi status tersedia; antrean/SLA masih perlu pengembangan. |
| 4. Percakapan dan notifikasi | 🟡 Dasar tersedia | Komentar teks dan endpoint komentar tersedia; notifikasi, attachment komentar, dan status dibaca belum lengkap. |
| 5. Fitur komunitas | 🔴 Belum dimulai | Feed publik khusus, “saya juga mengalami”, moderasi, dan duplikasi belum tersedia. |
| 6. Migrasi data lama | 🔴 Belum dimulai | Importer, pemetaan legacy, rekonsiliasi, dan mode read-only belum tersedia. |

### Implementasi yang sudah tersedia

- 17 route web untuk autentikasi, dashboard, laporan, triase, komentar,
  penutupan/pembukaan kembali, assignment, dan akses lampiran.
- Policy `ReportPolicy`, `ReportCommentPolicy`, dan `AttachmentPolicy`.
- Validasi upload maksimal lima gambar, MIME gambar, dan 10 MB per file.
- Validasi kamar terhadap gedung penghuni.
- Retensi dua tahun untuk laporan dan lampiran.
- Histori status dan histori perubahan prioritas.
- Tampilan estimasi respons, estimasi selesai, dan catatan estimasi.
- PHPUnit menggunakan SQLite in-memory agar tidak bergantung pada MySQL lokal.

## Skenario pengujian Tahap 0 dan Tahap 2

Gunakan data uji: dua gedung, dua penghuni dalam gedung yang sama, satu
penghuni gedung lain, satu manajer, satu petugas assigned, satu petugas
non-assigned, satu laporan utama, dan lampiran pada laporan.

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| T-01 | Resident membuka laporan miliknya | HTTP 200. |
| T-02 | Resident satu gedung membuka laporan publik | Berhasil tanpa nomor kamar pelapor. |
| T-03 | Resident gedung lain membuka laporan privat | HTTP 403. |
| T-04 | Staff assigned membuka detail dan triase | Berhasil. |
| T-05 | Staff non-assigned membuka laporan | HTTP 403. |
| T-06 | Manager membuka dan mentriase laporan | Berhasil. |
| T-07 | Pengguna berhak mengirim komentar | Komentar tersimpan dengan user yang benar. |
| T-08 | Komentar pada laporan tertutup | HTTP 403 dan tidak tersimpan. |
| T-09 | Pengguna berhak mengunduh lampiran | File dikirim sesuai metadata. |
| T-10 | Pengguna tanpa akses mengunduh lampiran | HTTP 403. |
| T-11 | Staff mengubah `in_progress` menjadi `waiting_confirmation` | Status dan histori berubah. |
| T-12 | Resident menutup `waiting_confirmation` | Status `closed`, waktu tutup, dan retensi terisi. |
| T-13 | Manager menutup `waiting_confirmation` | Berhasil dan pelaku tercatat. |
| T-14 | Resident atau manager membuka laporan tertutup | Status `reopened`; retensi dikosongkan. |
| T-15 | Pengguna tanpa hak membuka kembali | HTTP 403. |
| T-16 | Staff assigned mengubah prioritas dengan alasan | Prioritas dan histori berubah. |
| T-17 | Perubahan prioritas tanpa alasan | Ditolak; prioritas tetap. |
| T-18 | Perubahan prioritas pada `waiting_confirmation` atau `closed` | HTTP 403. |
| T-19 | Detail laporan memiliki data estimasi | Estimasi respons, selesai, dan catatan tampil. |
| T-20 | Staff mencoba melewati transisi status | Ditolak; status tetap. |
| T-21 | Lampiran laporan ditutup | Retensi lampiran sama dengan retensi laporan. |
| T-22 | Login akun aktif dan tidak aktif | Akun aktif berhasil; akun tidak aktif ditolak. |

Kriteria penerimaan:

1. Tidak ada endpoint sensitif yang dapat dilewati dengan mengganti ID.
2. Setiap status dan prioritas memiliki histori pelaku dan waktu.
3. `closed` hanya dicapai dari `waiting_confirmation`.
4. Laporan tertutup tidak menerima komentar baru.
5. Retensi laporan dan lampiran konsisten serta di-reset saat reopen.

Perintah validasi:

```text
php artisan test
```

Suite terakhir: **31 test lulus, 125 assertion lulus**.

## Backlog berikutnya

### Tahap 3: antrean dan SLA

- ✅ Dashboard antrean kerja berdasarkan gedung, unit, status, prioritas, dan usia.
- ✅ Filter kerja untuk manajer dan petugas; petugas hanya melihat assignment aktif.
- ✅ Histori estimasi dan alasan perubahan estimasi.
- ✅ Command terjadwal untuk notifikasi ketika estimasi respons atau penyelesaian
  terlewati, dengan pencegahan notifikasi duplikat.

### Tahap 4: percakapan dan notifikasi

- Lampiran pada komentar.
- Notifikasi in-app dan status dibaca.
- Notifikasi kepada pelapor saat status, assignment, atau estimasi berubah.
- Email setelah notifikasi in-app stabil.
- Polling atau WebSocket hanya bila diperlukan.

### Pesan commit yang disarankan

`feat: implement complaint reporting workflow with queue and SLA notifications`

### Status terbaru dan tahap selanjutnya

Tahap 3 selesai untuk cakupan MVP. Antrean kerja dan daftar laporan kini
menggunakan satu halaman dengan mode operasional berdasarkan role; route lama
`/queue` tetap tersedia sebagai alias kompatibilitas untuk staff dan manager.
Tahap berikutnya adalah Tahap 4: melengkapi percakapan dan notifikasi, terutama
lampiran komentar, status notifikasi dibaca, serta notifikasi perubahan status,
assignment, dan estimasi kepada pelapor.

### Tahap 5: komunitas

- Feed laporan publik per gedung.
- “Saya juga mengalami”.
- Penandaan duplikat.
- Moderasi dan pelaporan konten.

### Tahap 6: migrasi legacy

Buat importer idempotent di salinan database:

- `penghuni` menjadi `users`;
- `teknisi` dan `manajer` menjadi user berdasarkan role;
- kategori lama dipetakan ke `categories`;
- `pengaduan` menjadi `reports`;
- bukti lama menjadi `attachments`;
- teks penanganan menjadi komentar historis.

Tambahkan penanda sumber legacy, laporan baris gagal, rekonsiliasi nomor tiket,
dan validasi akses sebelum aplikasi lama dimatikan.

## Catatan operasional

- Jalankan migration sebelum memakai fitur yang memerlukan kolom baru:
  `php artisan migrate`.
- Jangan menjalankan test terhadap database development atau production.
- Pertahankan aplikasi lama read-only sampai migrasi data tervalidasi.
- Jangan menambahkan real-time chat, video besar, aplikasi mobile, AI, atau
  microservices sebelum alur inti stabil.
