# Sistem Pengaduan Kendala

Dokumen ini mencatat keputusan arsitektur, aturan bisnis, status implementasi,
rencana pengerjaan frontend, dan kriteria penerimaan proyek Laravel ini.
Status terakhir diperbarui pada **2 November 2026**.

## Keputusan arsitektur

- Framework: Laravel dengan modular monolith.
- Database pengembangan/produksi: MySQL/MariaDB.
- UI: Blade dengan Tailwind CSS melalui Vite; JavaScript hanya digunakan untuk
  interaksi yang memang membutuhkan perilaku client-side.
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

## Rencana pengerjaan frontend

Backend MVP sudah tersedia dan test suite terakhir mencatat **55 test lulus,
235 assertion lulus**. Fokus berikutnya adalah mengubah halaman Blade dasar
menjadi antarmuka yang konsisten, mudah dipahami, responsif, dan aman untuk
ketiga role. Tahap dikerjakan berurutan; setiap tahap harus selesai dan
divalidasi sebelum masuk ke tahap berikutnya.

**Status saat ini:** Tahap 1 selesai. Tahap berikutnya adalah Tahap 2 —
Halaman login dan navigasi dasar.

### Tahap 0 — Audit dan persiapan ✅ Selesai

Tujuan: menetapkan batas perubahan UI tanpa mengubah aturan bisnis yang sudah
berjalan.

- Inventaris seluruh route dan halaman: login, dashboard, laporan, pembuatan
  laporan, detail, triase, komunitas, moderasi, dan notifikasi.
- Petakan komponen berulang: layout, navigasi, breadcrumb, tombol, badge,
  card, form field, alert, modal konfirmasi, tabel, pagination, empty state,
  dan error state.
- Tetapkan kosakata UI berbahasa Indonesia untuk status, prioritas, role,
  aksi, validasi, dan notifikasi.
- Tetapkan breakpoint mobile/tablet/desktop, ukuran container, spacing,
  hirarki heading, fokus keyboard, serta palet warna yang membedakan status
  tanpa bergantung pada warna saja.
- Pastikan semua perubahan menggunakan asset yang sudah ada di Vite dan tidak
  mengubah policy, route, validasi, atau kontrak request.

**Hasil yang sudah diselesaikan:**

- Audit route menemukan 35 route untuk autentikasi, dashboard, laporan,
  triase, assignment, komentar, komunitas, moderasi, notifikasi, dan lampiran.
- Audit view menemukan 11 halaman Blade aplikasi: login, dashboard, laporan,
  detail, pembuatan laporan, triase, komunitas, moderasi, dan notifikasi,
  termasuk partial triase.
- Ditetapkan bahwa UI tetap menggunakan Blade, dengan Tailwind CSS melalui
  Vite dan JavaScript hanya untuk interaksi client-side yang diperlukan.
- Ditetapkan kebutuhan layout bersama dan komponen reusable: navigasi,
  breadcrumb, flash/error message, button, badge, card, form field,
  pagination, empty state, dan dialog konfirmasi.
- Ditetapkan tiga perspektif UI: resident, staff, dan manager. Kontrol UI
  tidak boleh menggantikan policy server atau menampilkan data yang tidak
  berhak dilihat pengguna.
- Ditetapkan cakupan responsive mobile/tablet/desktop, fokus keyboard,
  focus-visible, kontras, hirarki heading, serta status yang tidak hanya
  dibedakan melalui warna.
- Dikonfirmasi bahwa fondasi asset tersedia di `resources/css/app.css` dan
  `resources/js/app.js`; build Vite berhasil.
- Dikonfirmasi bahwa backend tetap stabil: route terdaftar, test suite
  terakhir **55 test lulus dengan 235 assertion**, dan tidak ada kebutuhan
  perubahan backend sebagai prasyarat frontend.

**Kriteria selesai:** daftar halaman, komponen, batasan akses, keputusan
visual, dan urutan pengerjaan sudah ditetapkan. Tidak ada perubahan kode
aplikasi pada tahap ini karena tahap ini hanya menghasilkan baseline dan
ruang lingkup implementasi.

### Tahap 1 — Fondasi asset dan layout aplikasi ✅ Selesai

Tujuan: menyediakan kerangka visual yang dipakai semua halaman.

- Pastikan `resources/css/app.css` dan `resources/js/app.js` dimuat melalui
  satu layout Blade utama.
- Buat layout authenticated dengan header, navigasi desktop, navigasi mobile,
  nama pengguna, role, link notifikasi, dan tombol logout.
- Buat layout guest untuk halaman login dengan branding yang sama.
- Tambahkan flash message sukses/gagal, ringkasan error validasi, breadcrumb,
  judul halaman, dan area konten yang konsisten.
- Buat partial/component kecil untuk button primary/secondary/danger,
  status badge, priority badge, card, input, select, textarea, file list,
  pagination, dan empty state.
- Tambahkan state hover, focus-visible, disabled, loading sederhana, serta
  kontras teks yang memadai.
- Hapus markup duplikat antar halaman setelah layout baru terpasang.

**Implementasi yang sudah diselesaikan:**

- Membuat layout authenticated di `resources/views/layouts/app.blade.php`
  dengan branding, navigasi role-aware, akun pengguna, role badge, dan logout.
- Membuat layout guest di `resources/views/layouts/guest.blade.php` untuk
  login dengan asset yang sama.
- Membuat partial `resources/views/components/feedback.blade.php` untuk flash
  success/error dan ringkasan error validasi yang konsisten.
- Memasang layout baru pada seluruh halaman aplikasi: login, dashboard,
  laporan, detail, pembuatan laporan, triase, komunitas, moderasi, dan
  notifikasi. Halaman welcome Laravel tetap berdiri sendiri sebagai halaman
  publik default.
- Menambahkan fondasi visual di `resources/css/app.css`: token warna,
  typography, container, card, button, alert, form control, focus-visible,
  pagination, detail, fieldset, serta breakpoint responsive dasar.
- Menetapkan style button secara global agar seluruh elemen `<button>` pada
  form, termasuk login, kirim laporan, komentar, pelaporan, moderasi, filter,
  dan assignment, tampil sebagai tombol aksi yang konsisten tanpa harus
  mengubah setiap endpoint atau nama aksi.
- Menjaga tombol dan link tetap memakai route, policy, validasi, serta kontrak
  request yang sudah ada; tahap ini hanya mengubah struktur presentasi dan
  styling.
- Menghapus navigasi/logout duplikat dari halaman yang sekarang sudah memakai
  layout bersama.

**Validasi:** `php artisan view:cache` dan `npm run build` berhasil. Layout
authenticated digunakan oleh semua halaman aplikasi, layout guest digunakan oleh
halaman login, dan tombol submit/aksi tidak lagi tampil sebagai teks polos.
Penambahan button styling ini merupakan penyelesaian detail Tahap 1, bukan
pekerjaan baru Tahap 2.

### Tahap 2 — Halaman login dan navigasi dasar ⏭️ Berikutnya

Tujuan: membuat pintu masuk aplikasi jelas dan nyaman digunakan.

- Poles form login dengan label, helper text, validasi inline, pesan akun
  tidak aktif, tombol submit yang jelas, dan dukungan keyboard.
- Tampilkan kredensial development hanya pada environment lokal seperti aturan
  saat ini; jangan tampilkan pada production.
- Pastikan redirect setelah login dan logout tetap berjalan.
- Uji menu pada viewport kecil, termasuk menu yang dapat dibuka dan ditutup
  tanpa mengganggu isi halaman.

**Selesai jika:** pengguna dapat login, memahami error, bernavigasi ke halaman
yang sesuai role, dan logout dari desktop maupun mobile.

### Tahap 3 — Dashboard per role

Tujuan: menjadikan dashboard sebagai titik awal kerja, bukan sekadar kumpulan
link.

- Resident: tampilkan ringkasan laporan milik sendiri, status terakhir,
  laporan yang menunggu konfirmasi, shortcut buat laporan, dan akses komunitas.
- Staff: tampilkan antrean assignment aktif, laporan yang perlu ditangani,
  status pekerjaan, estimasi terdekat, serta shortcut ke antrean.
- Manager: tampilkan ringkasan seluruh laporan, antrean triase, laporan
  berprioritas tinggi/kritis, moderasi yang menunggu, dan shortcut assignment.
- Tambahkan empty state untuk akun baru dan pesan ketika tidak ada pekerjaan.
- Pastikan angka ringkasan berasal dari data server, bukan angka statis.

**Selesai jika:** setiap role melihat dashboard yang relevan dan setiap kartu
atau shortcut mengarah ke halaman yang benar dengan policy yang tetap berlaku.

### Tahap 4 — Daftar laporan dan antrean kerja

Tujuan: memudahkan pencarian dan pemindaian laporan.

- Buat tampilan list/table responsif untuk resident, staff, dan manager.
- Tampilkan ticket number, judul, kategori, lokasi yang aman, status, prioritas,
  assignment, waktu dibuat, dan estimasi yang sesuai hak akses.
- Poles filter status, prioritas, pencarian, serta filter antrean staff/manager.
- Pertahankan query filter ketika berpindah halaman.
- Gunakan badge dan ikon pendukung, tetapi selalu sertakan teks.
- Bedakan empty state, hasil pencarian kosong, error server, dan loading.
- Sediakan aksi moderasi inline hanya bagi manager dan sesuai status laporan.

**Selesai jika:** daftar tetap terbaca pada mobile, filter dapat dipahami,
pagination tidak kehilangan konteks, dan data privat tidak muncul dalam
kolom, preview, atau tooltip yang tidak berhak melihatnya.

### Tahap 5 — Form pembuatan laporan

Tujuan: membuat pelaporan kendala cepat, jelas, dan mencegah salah input.

- Susun form bertahap secara visual: lokasi, kategori, uraian, visibilitas,
  dampak/urgensi/risiko, dan lampiran.
- Tampilkan pilihan kamar hanya jika relevan dan pertahankan aturan kamar milik
  gedung pelapor.
- Jelaskan perbedaan `private` dan `public_building` sebelum submit.
- Tampilkan batas file: maksimal lima gambar dan 10 MB per file.
- Tampilkan nama file, ukuran, preview yang aman jika memungkinkan, serta
  tombol hapus/minus sebelum submit.
- Pertahankan input lama ketika validasi server gagal.
- Tampilkan error per field dan ringkasan error di bagian atas.
- Tambahkan konfirmasi atau state sukses setelah laporan berhasil dibuat.

**Selesai jika:** form dapat digunakan dengan keyboard dan mobile, validasi
client-side tidak menggantikan validasi server, dan seluruh skenario lokasi,
visibilitas, serta lampiran tetap lulus.

### Tahap 6 — Detail laporan dan timeline

Tujuan: menyajikan satu laporan sebagai sumber informasi yang lengkap.

- Susun header dengan ticket number, judul, status, prioritas, kategori, dan
  tanggal.
- Tampilkan informasi lokasi dan identitas hanya sesuai `identity_visibility`
  serta policy pengguna.
- Buat panel terpisah untuk deskripsi, lampiran, assignment, estimasi, dan
  histori status/prioritas.
- Tampilkan timeline perubahan status dengan pelaku, waktu, dan catatan.
- Sediakan tombol close, reopen, dan aksi lain hanya ketika status dan role
  mengizinkan.
- Tambahkan dialog konfirmasi untuk close, reopen, hide, restore, dan aksi
  moderasi yang berdampak permanen atau sulit dibatalkan.
- Tampilkan status read-only pada laporan `closed` dan pesan alasan ketika
  komentar atau aksi tidak tersedia.

**Selesai jika:** detail dapat dipahami tanpa membaca source code, aksi ilegal
tidak ditawarkan oleh UI, dan seluruh data sensitif tetap tersaring.

### Tahap 7 — Triase, assignment, dan estimasi

Tujuan: mendukung pekerjaan staff dan manager tanpa membuat form operasional
terlihat seperti form biasa.

- Kelompokkan prioritas, skor dampak/urgensi/risiko, unit layanan, staff,
  estimasi respons, estimasi selesai, dan catatan estimasi.
- Tampilkan alasan wajib ketika mengubah prioritas atau estimasi.
- Tampilkan transisi status yang valid sesuai role dan status saat ini.
- Bedakan aksi manager (triase/assignment) dari aksi staff (menangani laporan).
- Tampilkan hasil sukses dan error tanpa menghilangkan konteks laporan.
- Gunakan tabel atau kartu assignment yang jelas bila histori assignment tersedia.

**Selesai jika:** staff tidak melihat kontrol assignment yang tidak boleh dipakai,
transisi ilegal tidak ditawarkan, dan manager dapat menyelesaikan triase dari
halaman yang mudah dipindai.

### Tahap 8 — Komentar, lampiran, komunitas, dan moderasi

Tujuan: membuat percakapan aman, terbaca, dan mudah dimoderasi.

- Tampilkan thread komentar dengan identitas sesuai aturan visibility dan
  indikator komentar yang disembunyikan bila manager memiliki hak audit.
- Bedakan komentar internal, komentar komunitas, dan laporan publik melalui
  label yang jelas.
- Tampilkan syarat “Saya juga mengalami” sebelum resident dapat berkomentar.
- Tampilkan keadaan percakapan aktif, dinonaktifkan, atau read-only karena
  laporan `closed`.
- Poles form komentar, rate-limit feedback, daftar lampiran, dan tombol lapor.
- Buat panel moderasi inline untuk alasan, catatan, kandidat duplikat,
  konfirmasi, penolakan, sembunyikan, pulihkan, dan batalkan duplikat.
- Pastikan nomor kamar, kontak, dan detail privat tidak muncul di feed publik,
  preview, atau komentar.

**Selesai jika:** resident, staff, dan manager memahami batas interaksi mereka,
moderasi tidak ambigu, dan isolasi antar-gedung tetap terlihat benar dari UI.

### Tahap 9 — Notifikasi dan feedback interaksi

Tujuan: memastikan pengguna mengetahui perubahan tanpa kehilangan konteks.

- Buat indikator jumlah notifikasi belum dibaca di navigasi.
- Tampilkan daftar notifikasi dengan waktu, tipe, ringkasan, status dibaca,
  dan link ke laporan terkait.
- Sediakan aksi tandai sudah dibaca serta state sukses/gagal yang jelas.
- Pastikan notifikasi manager-only tidak bocor ke resident atau staff.
- Tambahkan feedback submit: disabled/loading, flash message, error server,
  dan pencegahan double submit.

**Selesai jika:** setiap aksi penting menghasilkan feedback yang dapat dipahami
dan pengguna dapat kembali ke objek asal dari notifikasi.

### Tahap 10 — Responsif, aksesibilitas, dan hardening visual

Tujuan: memastikan UI siap dipakai pada perangkat dan kondisi nyata.

- Uji viewport mobile, tablet, laptop, dan desktop.
- Uji keyboard-only: tab order, focus-visible, form, menu, dialog, dan tombol.
- Tambahkan label yang benar, `aria-*` seperlunya, alt text, dan struktur
  heading yang logis.
- Periksa kontras, ukuran target sentuh, teks panjang, file name panjang,
  empty state, error state, dan data dalam jumlah besar.
- Pastikan tidak ada informasi privat pada title, tooltip, source HTML, atau
  komponen yang disembunyikan secara visual saja.
- Optimalkan asset build dan hindari JavaScript untuk kebutuhan yang dapat
  diselesaikan dengan Blade/CSS.

**Selesai jika:** tidak ada layout overflow yang menghalangi aksi utama,
semua alur utama dapat diselesaikan tanpa mouse, dan hasil build production
berhasil.

### Tahap 11 — Pengujian end-to-end dan penyelesaian

Tujuan: memvalidasi frontend terhadap backend yang sudah selesai.

- Jalankan `php artisan test` dan pastikan seluruh test backend tetap lulus.
- Jalankan `npm run build` dan pastikan asset production berhasil dibuat.
- Uji manual dengan akun resident, staff assigned, staff non-assigned, dan
  manager.
- Uji alur utama: login, dashboard, buat laporan, upload, filter, detail,
  triase, assignment, komentar, notifikasi, close, reopen, dan moderasi.
- Uji seluruh kombinasi penting role/status untuk memastikan UI tidak
  menampilkan aksi yang ditolak server.
- Uji respons error 403, 404, 419, 422, dan 500 dengan tampilan yang layak.
- Catat bug visual dan fungsional, perbaiki, lalu ulangi validasi sampai bersih.
- Perbarui screenshot atau dokumentasi penggunaan hanya jika memang
  diperlukan untuk operasional.

**Kriteria frontend selesai:**

1. Semua halaman aplikasi memakai layout dan komponen visual yang konsisten.
2. Semua role memperoleh navigasi dan dashboard yang relevan.
3. Semua form memiliki label, error state, success state, dan state loading
   yang jelas.
4. UI tidak menawarkan aksi yang melanggar role, policy, atau transisi status.
5. Informasi identitas, kamar, lampiran, dan laporan tetap mengikuti aturan
   privasi.
6. Tampilan usable pada mobile, desktop, dan keyboard-only.
7. `php artisan test` dan `npm run build` berhasil.

### Di luar lingkup frontend saat ini

Email, polling/WebSocket, aplikasi mobile, video besar, AI, microservices,
migrasi data lama, dan redesign backend bukan bagian dari roadmap ini. Fitur
tersebut hanya dipertimbangkan setelah UI MVP dipakai dan kebutuhan
operasionalnya terbukti.

## Catatan operasional

- Jalankan migration sebelum memakai fitur yang memerlukan kolom baru:
  `php artisan migrate`.
- Jangan menjalankan test terhadap database development atau production.
- Jangan menambahkan real-time chat, video besar, aplikasi mobile, AI, atau
  microservices sebelum alur inti stabil dan kebutuhan operasional terbukti.
