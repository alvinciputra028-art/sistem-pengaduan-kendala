# Sistem Pengaduan Kendala

Dokumen ini mencatat keputusan arsitektur, aturan bisnis, status implementasi,
pengujian, dan backlog proyek Laravel ini. Status terakhir diperbarui pada
**29 Oktober 2026**.

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

### Percakapan komunitas pada laporan publik

Gagasan chat grup pada laporan publik masuk akal karena dapat mengumpulkan
informasi dari penghuni yang mengalami kendala serupa dan mengurangi laporan
duplikat. Namun, fitur ini sebaiknya diperlakukan sebagai **thread percakapan
termoderasi yang terikat pada satu laporan**, bukan grup bebas seperti
WhatsApp. Implementasinya aman jika mengikuti batasan berikut:

- Hanya laporan `public_building` yang dapat memiliki percakapan komunitas.
- Pelapor, penghuni gedung yang memilih **“Saya juga mengalami”**, petugas
  yang memiliki assignment aktif, dan manajer dapat berpartisipasi sesuai
  policy. Penghuni tidak otomatis ditambahkan ke percakapan.
- Pengguna lain tidak dapat melihat nomor kamar, nomor telepon, alamat, atau
  detail privat pelapor. Identitas anonim tetap mengikuti
  `identity_visibility`.
- Komentar divalidasi sebagai teks biasa dengan batas panjang, rate limit, dan
  sanitasi output. Lampiran komentar tetap privat dan mengikuti policy laporan.
- Resident dan staff yang memiliki akses laporan dapat melaporkan komentar
  dengan alasan dan catatan tambahan. Manager menerima notifikasi yang
  menyebutkan ID, isi ringkas, pelapor, dan laporan komentar tersebut.
- Manager dapat menyembunyikan komentar, menonaktifkan percakapan, dan
  mencatat alasan serta pelakunya. Penghapusan tidak menghilangkan jejak audit
  dan komentar dapat dipulihkan manager.
- Percakapan hanya dapat dinonaktifkan pada status `submitted`, `triaged`,
  `assigned`, `in_progress`, `waiting_confirmation`, atau `reopened`; laporan
  `closed` tidak dapat dinonaktifkan.
  Penonaktifan hanya membatasi resident lain; pelapor dan staff yang di-assign
  tetap dapat berkomunikasi internal dan resident lain tidak dapat menulis
  maupun melaporkan komentar. Laporan `closed` menjadi read-only untuk semua
  pihak.
- Notifikasi komentar hanya dikirim kepada peserta yang relevan dan dapat
  dimatikan oleh pengguna. Notifikasi moderasi laporan, duplikat, dan komentar
  hanya dikirim kepada manager aktif. Jangan mengirim isi percakapan ke kanal
  eksternal secara otomatis.

### Moderasi laporan komunitas

- Notifikasi tentang laporan komunitas atau flag duplikat hanya dikirim kepada
  manager aktif. Pelapor, penghuni lain, dan staff terkait tidak menerima
  notifikasi moderasi tersebut.
- Manager dapat melihat identitas pengaju laporan/flag, identitas pelapor
  laporan, alasan, catatan, waktu, dan laporan utama atau duplikat yang
  terkait.
- Manager tidak seharusnya melaporkan atau menandai duplikat lalu memoderasi
  flag miliknya sendiri. Aturan ini akan ditegakkan melalui policy.
- Moderasi dilakukan langsung dari daftar laporan/antrean melalui panel aksi
  dan detail flag. Halaman khusus “Moderasi Komunitas” tidak lagi menjadi
  alur utama dan dapat dihapus setelah seluruh tautan serta notifikasinya
  dialihkan ke daftar laporan/antrean.

### Daftar lampiran

Menampilkan nama setiap file yang dipilih, bukan hanya jumlah file, masuk akal
dan memperbaiki pencegahan salah unggah. Setiap nama file memiliki tombol
hapus/minus untuk mengeluarkannya dari daftar sebelum form dikirim. Daftar ini
harus tetap tunduk pada batas maksimal lima gambar, format yang diizinkan,
ukuran maksimal 10 MB per file, serta validasi server.

Aturan tersebut sekarang diterapkan menggunakan `report_comments` dan
`report_acknowledgements`: penghuni harus memilih **“Saya juga mengalami”**
sebelum dapat menulis komentar. Pengujian mencakup isolasi antar-gedung,
kebocoran identitas, pelaporan resident/staff, detail moderasi, akses
lampiran, penonaktifan percakapan, dan kondisi laporan ditutup.

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
| `community_chat_disabled_at` | timestamp, nullable | Waktu percakapan komunitas dinonaktifkan. |
| `community_chat_disabled_by` | FK `users`, nullable | Manager yang menonaktifkan percakapan. |
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
terhapus tidak ditampilkan oleh model aplikasi. Pada laporan publik, penghuni
harus lebih dulu memilih “Saya juga mengalami” untuk dapat menulis komentar.

### `report_comment_moderation_reports`

Menyimpan laporan komentar oleh penghuni, termasuk alasan, status penanganan,
manager peninjau, dan waktu peninjauan. Komentar yang dimoderasi disembunyikan
secara soft delete dengan `deleted_by` dan `deleted_at`, sehingga jejak audit
tetap tersedia.

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
| 3. Triase dan prioritas | 🟢 Selesai untuk MVP | Skor prioritas, triase, assignment, estimasi, antrean kerja, SLA, dan transisi status tersedia. |
| 4. Percakapan dan notifikasi | 🟢 Selesai untuk MVP | Komentar, lampiran komentar, notifikasi in-app, status dibaca, pembatasan moderasi, dan notifikasi manager-only tersedia; email serta real-time masih opsional. |
| 5. Fitur komunitas | 🟢 Selesai untuk MVP | Feed publik, “saya juga mengalami”, percakapan terikat laporan, pelaporan komentar, moderasi komentar, serta moderasi laporan/duplikat inline tersedia. |

### Implementasi yang sudah tersedia

- Route web untuk autentikasi, dashboard, laporan, triase, komentar,
  penutupan/pembukaan kembali, assignment, komunitas, dan akses lampiran.
- Policy `ReportPolicy`, `ReportCommentPolicy`, dan `AttachmentPolicy`.
- Validasi upload maksimal lima gambar, MIME gambar, dan 10 MB per file.
- Validasi kamar terhadap gedung penghuni.
- Retensi dua tahun untuk laporan dan lampiran.
- Histori status dan histori perubahan prioritas.
- Tampilan estimasi respons, estimasi selesai, dan catatan estimasi.
- Percakapan komunitas publik dengan keanggotaan melalui “Saya juga
  mengalami”, pelaporan komentar, penyembunyian komentar, serta tombol
  aktif/nonaktif percakapan oleh manager. Komentar yang disembunyikan dapat
  dipulihkan kembali oleh manager dengan jejak moderasi tetap tersimpan.
- Notifikasi pelaporan komentar hanya untuk manager dan mencantumkan
  identitas serta ringkasan komentar yang dilaporkan.
- Moderasi komunitas pada halaman daftar laporan/antrean menampilkan pelapor,
  pengaju flag, alasan, catatan, kandidat laporan utama, dan aksi konfirmasi,
  penolakan, sembunyikan, pulihkan, atau batalkan duplikat. Halaman lama
  “Moderasi Komunitas” mengarahkan pengguna ke daftar laporan.
- Input lampiran laporan dan komentar menampilkan nama file yang dipilih serta
  tombol hapus/minus sebelum submit.
- PHPUnit menggunakan SQLite in-memory agar tidak bergantung pada MySQL lokal.

## Skenario pengujian inti dan komunitas

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
6. Percakapan publik terisolasi antar-gedung, memerlukan acknowledgement,
   dapat dimoderasi, dan menjadi read-only saat dinonaktifkan atau ditutup.

Perintah validasi:

```text
php artisan test
```

Suite terakhir: **55 test lulus, 235 assertion lulus**.

## Backlog berikutnya

- Menyelesaikan email setelah notifikasi in-app stabil.
- Mengevaluasi polling atau WebSocket hanya jika kebutuhan operasional memang
  memerlukannya.

Tidak ada Tahap 6 migrasi data lama. Sistem baru dimulai dengan data baru;
database lama dan data dummy tidak perlu diimpor, dipetakan, atau dipertahankan
sebagai bagian dari alur aplikasi ini.

## Catatan operasional

- Jalankan migration sebelum memakai fitur yang memerlukan kolom baru:
  `php artisan migrate`.
- Jangan menjalankan test terhadap database development atau production.
- Jangan menambahkan real-time chat, video besar, aplikasi mobile, AI, atau
  microservices sebelum alur inti stabil dan kebutuhan operasional terbukti.
