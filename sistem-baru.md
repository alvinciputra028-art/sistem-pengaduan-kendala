Requested chat rename
Completed with input: {
  "title": "Strategi modernisasi sistem pengaduan",
  "automatic": true
}

Read , line 1 to the end
Completed with input: {
  "path": "C:\\xampp_new\\htdocs\\sistem-pengaduan-kendala\\README.md",
  "view_range": [
    1,
    -1
  ]
}

Saya sudah membaca README. Untuk memberi rekomendasi yang tidak hanya berdasarkan deskripsi fitur, saya akan mencocokkannya dengan skema database dan alur implementasi utama sistem lama, lalu menyusun usulan arsitektur, prioritas, dan rencana migrasi.

Read , line 1 to the end
Completed with input: {
  "path": "C:\\xampp_new\\htdocs\\sistem-pengaduan-kendala\\complaint.sql",
  "view_range": [
    1,
    -1
  ]
}

Read , line 1 to the end
Completed with input: {
  "path": "C:\\xampp_new\\htdocs\\sistem-pengaduan-kendala\\penghuni\\proses_pengaduan.php",
  "view_range": [
    1,
    -1
  ]
}

Read , line 1 to the end
Completed with input: {
  "path": "C:\\xampp_new\\htdocs\\sistem-pengaduan-kendala\\teknisi\\update_status.php",
  "view_range": [
    1,
    -1
  ]
}

Read , line 1 to the end
Completed with input: {
  "path": "C:\\xampp_new\\htdocs\\sistem-pengaduan-kendala\\shared\\detail_pengaduan.php",
  "view_range": [
    1,
    -1
  ]
}

## Kesimpulan singkat

Proyek lama ini **layak dijadikan fondasi**, tetapi sebaiknya tidak dikembangkan dengan menambah banyak kondisi baru ke PHP Native yang sekarang. Struktur saat ini sudah memiliki konsep dasar yang benar—autentikasi, peran pengguna, tiket, status, teknisi, bukti, dan laporan—namun model datanya masih terlalu sederhana untuk mendukung forum gedung, percakapan dua arah, lampiran multi-format, prioritas, moderasi, dan audit.

Untuk kebutuhan Anda, pilihan yang paling objektif adalah:

> **Laravel + MySQL/MariaDB + Blade/Livewire terlebih dahulu**, lalu menambahkan Laravel Reverb/WebSockets jika notifikasi atau percakapan real-time benar-benar diperlukan.

Tidak perlu langsung menggunakan arsitektur microservices atau aplikasi mobile. Bangun modular monolith yang rapi terlebih dahulu.

---

# 1. Evaluasi sistem lama

## Bagian yang layak dipertahankan

### 1. Konsep ticketing

Konsep nomor tiket pada `README.md` sudah tepat. Setiap laporan memiliki identitas yang dapat dirujuk penghuni, teknisi, dan manajer.

Sebaiknya dipertahankan, tetapi generator tiket perlu diperbaiki. Implementasi sekarang menghitung jumlah tiket pada hari berjalan di `penghuni/proses_pengaduan.php`. Cara ini bisa menghasilkan nomor tiket ganda jika dua penghuni mengirim laporan hampir bersamaan.

Sebaiknya:

- gunakan `UUID` atau ID internal sebagai identitas utama;
- nomor tiket tetap dibuat untuk kebutuhan manusia;
- gunakan counter database atau kombinasi tahun-bulan dan sequence;
- tambahkan unique index pada `ticket_number`.

### 2. Alur status dasar

Status:

- Menunggu
- Diproses
- Selesai

adalah fondasi yang baik. Namun untuk sistem yang lebih serius, status sebaiknya diperluas menjadi:

- `draft`
- `submitted`
- `triaged`
- `assigned`
- `in_progress`
- `resolved`
- `closed`
- `reopened`
- `rejected`
- `duplicate`

Tidak semua status harus ditampilkan kepada penghuni. Sistem internal boleh lebih detail, sedangkan penghuni melihat status yang lebih sederhana.

### 3. Pembagian peran

Sistem lama sudah memisahkan:

- penghuni;
- teknisi;
- manajer.

Hal ini perlu dipertahankan dengan tiga role aplikasi saja:

- `resident`
- `staff`
- `manager`

`staff` menggantikan istilah teknisi karena tanggung jawabnya dapat mencakup
jaringan, kebersihan, keamanan, administrasi, dan layanan lain. Manajer
menjalankan fungsi pengawasan, termasuk tindakan yang sebelumnya disebut
sebagai fungsi supervisor. Tidak perlu role supervisor, administrator, atau
moderator pada tahap ini.

Fungsi teknis administrasi sistem seperti mengelola kategori, akun, dan unit
layanan dapat diberikan kepada manajer melalui izin aplikasi. Jika kelak
organisasi membesar, izin tersebut dapat dipisahkan menjadi role baru tanpa
mengubah data laporan.

### 4. Pembatasan berdasarkan gedung

Aturan bahwa penghuni hanya dapat melihat laporan dari gedung yang sama merupakan keputusan yang baik. Di `shared/detail_pengaduan.php`, aturan ini sudah mulai diterapkan.

Namun “gedung yang sama” sebaiknya tidak disimpan sebagai teks atau enum yang
tersebar di banyak tabel. Gunakan:

- `buildings`
- `rooms`
- `users`

Nomor kamar seperti `301` dapat digunakan untuk menampilkan lantai, tetapi
lantai tidak perlu menjadi tabel tersendiri. Laporan area bersama menyimpan
deskripsi lokasi, misalnya “lorong lantai 3 dekat kamar 307”.

### 5. Bukti penyelesaian

Kemampuan teknisi mengunggah bukti penyelesaian adalah fitur yang berguna dan sebaiknya dipertahankan. Tetapi jangan menyimpan hanya satu kolom seperti `bukti_foto` dan `bukti_penyelesaian`.

Model baru sebaiknya memungkinkan banyak lampiran:

- foto sebelum;
- foto sesudah;
- video;
- rekaman suara;
- dokumen pendukung.

---

# 2. Masalah utama pada sistem lama

Berdasarkan `complaint.sql` dan alur di `teknisi/update_status.php`, beberapa keterbatasan utama adalah sebagai berikut.

## 1. Data pengaduan terlalu datar

Saat ini satu tabel `pengaduan` menampung:

- informasi laporan;
- teknisi;
- penanganan dini;
- komentar penyelesaian;
- bukti laporan;
- bukti penyelesaian.

Struktur ini akan cepat bermasalah ketika Anda menambahkan percakapan banyak pesan dan banyak lampiran.

Pisahkan menjadi entitas:

```text
users
buildings
rooms
categories
reports
report_assignments
report_status_histories
comments
attachments
notifications
priorities
```

## 2. Belum ada konsep percakapan

`penanganan_dini` dan `komentar_penyelesaian` hanya cocok untuk komunikasi satu atau dua kali. Kebutuhan Anda sudah mengarah ke **thread percakapan**.

Contoh:

```text
reports
  └── comments
        ├── komentar penghuni
        ├── balasan teknisi
        ├── komentar manajer
        └── lampiran pada masing-masing komentar
```

Jangan menjadikan komentar sebagai kolom baru di tabel laporan.

## 3. Kategori menggunakan ENUM

Di database lama, kategori menggunakan ENUM:

```text
Internet, Listrik, Air, Bangunan, Perabotan, Elektronik, Lainnya
```

Ini kurang fleksibel ketika Anda ingin menambahkan:

- keamanan;
- kebersihan;
- administrasi;
- fasilitas umum;
- ketertiban;
- kesehatan;
- parkir;
- keuangan;
- keluhan sosial;
- lainnya.

Sebaiknya gunakan tabel `categories`, misalnya:

```text
categories
- id
- name
- slug
- description
- is_active
- default_service_unit_id
```

Kategori dapat ditambahkan dari panel administrasi tanpa mengubah struktur database.

## 4. Kategori lokasi belum cukup

Laporan seperti:

- Wi-Fi bermasalah;
- kebisingan di lantai 3;
- tumpukan sampah di dapur;

bukan hanya memiliki kategori, tetapi juga memiliki **lokasi**.

Laporan sebaiknya memiliki pilihan:

- kamar tertentu;
- gedung;
- area bersama;
- area publik;
- lokasi rahasia atau privat, jika diperlukan.

## 5. Upload file masih terlalu terbatas

Implementasi lama di `penghuni/proses_pengaduan.php` hanya memeriksa ekstensi file dan ukuran file. Ini belum cukup untuk sistem produksi.

Untuk upload baru, lakukan:

- validasi MIME type menggunakan isi file, bukan hanya ekstensi;
- batas ukuran berbeda berdasarkan jenis file;
- nama file acak;
- penyimpanan di luar direktori publik jika memungkinkan;
- pemeriksaan virus atau file berbahaya;
- pembatasan jumlah lampiran;
- thumbnail untuk gambar;
- kontrol siapa yang boleh mengakses lampiran;
- penghapusan metadata sensitif bila relevan;
- larangan file executable seperti PHP, HTML, SVG berbahaya, dan sejenisnya.

Untuk tahap awal, saya menyarankan:

| Jenis | Batas awal |
|---|---:|
| Gambar | 5 MB |
| Audio | 10–20 MB |
| Video | 50–100 MB |
| Dokumen | 10 MB |

Batas ini dapat disesuaikan setelah melihat kapasitas server.

## 6. Privasi masih terlalu longgar

Pada detail laporan lama, beberapa pihak dapat melihat nomor HP pengguna. Untuk sistem baru, jangan menampilkan nomor telepon secara otomatis kepada semua pihak.

Contoh aturan:

- penghuni lain hanya melihat nama tampilan, bukan nomor kamar lengkap;
- teknisi dapat melihat lokasi yang diperlukan untuk bekerja;
- manajer dapat melihat data lengkap sesuai kewenangan;
- nomor HP hanya ditampilkan jika memang diperlukan dan diizinkan;
- laporan sensitif tidak masuk forum publik.

Ini penting terutama untuk kategori keamanan, konflik antar penghuni, pelecehan, atau administrasi pribadi.

## 7. Tidak ada audit trail

Perubahan status, penugasan teknisi, penghapusan komentar, dan perubahan prioritas harus dicatat.

Contoh:

```text
report_status_histories
- report_id
- from_status
- to_status
- changed_by
- reason
- created_at
```

Dengan ini manajer dapat mengetahui siapa yang mengubah status dan kapan.

---

# 3. Framework yang disarankan

## Pilihan utama: Laravel

Laravel paling sesuai untuk kondisi Anda karena:

1. Anda sudah menggunakan PHP Native.
2. Laravel tetap menggunakan PHP dan MySQL/MariaDB.
3. Dokumentasi dan komunitasnya besar.
4. Memiliki migration, validation, authentication, authorization, queue, notification, storage, dan testing.
5. Cocok untuk aplikasi internal berbasis CRUD dan workflow.
6. Dapat dikembangkan bertahap tanpa harus langsung membuat API dan aplikasi mobile.
7. Memiliki ekosistem untuk real-time event melalui Laravel Reverb atau layanan broadcasting lain.

Stack yang saya sarankan:

```text
PHP 8.3 atau versi stabil yang kompatibel
Laravel
MySQL/MariaDB
Blade
Livewire
Tailwind CSS atau Bootstrap
Redis opsional
Laravel Reverb opsional
PHPUnit/Pest
```

### Mengapa Blade + Livewire?

Untuk tahap awal, Anda belum perlu membuat frontend SPA kompleks. Blade + Livewire dapat memberikan:

- formulir interaktif;
- filter laporan;
- komentar;
- upload lampiran;
- notifikasi sederhana;
- pembaruan data tanpa banyak JavaScript.

Jika nanti diperlukan aplikasi mobile atau integrasi pihak ketiga, Laravel dapat menambahkan API dengan Sanctum.

## Alternatif framework

### CodeIgniter 4

Kelebihan:

- lebih ringan;
- cukup dekat dengan gaya PHP Native;
- relatif mudah dipelajari.

Kekurangan:

- ekosistem dan pola bawaan tidak selengkap Laravel;
- Anda kemungkinan akan membangun sendiri lebih banyak komponen;
- untuk fitur komentar, notifikasi, permission, dan media, Laravel biasanya lebih nyaman.

CodeIgniter masuk akal jika server sangat terbatas atau Anda menginginkan framework minimalis. Untuk proyek Anda, saya tetap lebih merekomendasikan Laravel.

### Symfony

Sangat baik secara arsitektur dan cocok untuk sistem enterprise. Namun kurva belajar dan konfigurasi biasanya lebih berat untuk tahap migrasi pertama dari PHP Native.

### Node.js/NestJS atau Django

Keduanya bisa digunakan, tetapi perpindahan dari PHP Native menjadi lebih besar. Anda harus mengganti bahasa, toolchain, deployment, dan pola pengembangan sekaligus. Tidak ada keuntungan yang cukup besar untuk membenarkan kompleksitas tersebut pada tahap ini.

---

# 4. Desain produk yang disarankan

Sebaiknya sistem dibagi menjadi dua jenis ruang.

## A. Laporan komunitas atau forum gedung

Digunakan untuk kendala umum yang relevan bagi penghuni lain:

- Wi-Fi gedung bermasalah;
- lampu lorong mati;
- kebisingan area bersama;
- sampah menumpuk;
- kerusakan fasilitas umum;
- gangguan air di lantai tertentu.

Fitur:

- dapat dilihat penghuni dalam gedung yang sama;
- penghuni dapat memberi komentar;
- penghuni dapat memberi reaksi atau “saya juga mengalami”;
- sistem dapat menggabungkan laporan yang sama;
- petugas dapat memberikan pembaruan resmi;
- identitas pelapor dapat ditampilkan atau disamarkan sesuai kebijakan.

## B. Laporan privat

Digunakan untuk hal-hal sensitif atau personal:

- kerusakan di kamar pribadi;
- masalah administrasi individu;
- konflik antar penghuni;
- keamanan pribadi;
- laporan yang mengandung data sensitif.

Fitur:

- hanya pelapor, petugas yang berkaitan, dan manajer;
- tidak muncul di forum umum;
- dapat memiliki komentar internal dan komentar yang terlihat penghuni;
- lampiran memiliki kontrol akses khusus.

Jangan menjadikan semua laporan otomatis publik. Saat membuat laporan, pengguna sebaiknya memilih:

```text
Visibilitas:
- Umum untuk penghuni gedung
- Privat
- Rahasia untuk pengelola
```

Untuk kategori keamanan, administrasi, dan konflik, sebaiknya default-nya privat atau rahasia.

---

# 5. Komentar: forum dan percakapan jangan dicampur

Anda menyebut dua kebutuhan:

1. komentar antar penghuni pada laporan umum;
2. percakapan seperti WhatsApp antara penghuni dan petugas.

Keduanya sebaiknya memakai model teknis yang mirip, tetapi kebijakan aksesnya berbeda.

## Komentar forum

Komentar terhubung ke laporan publik dan dapat dilihat penghuni yang berhak melihat laporan tersebut.

Kemampuan yang dibutuhkan:

- balasan;
- edit dalam batas waktu tertentu;
- hapus oleh pemilik atau manajer;
- penguncian diskusi oleh manajer jika diperlukan;
- pagination;
- pembatasan spam;
- lampiran opsional.

## Percakapan laporan

Percakapan terhubung ke laporan dan hanya dapat dilihat oleh pihak yang berwenang.

Contoh:

```text
Resident: Foto tambahan kondisi plafon
Technician: Kami akan datang pukul 14.00
Resident: Baik, saya ada di kamar
Technician: Perbaikan selesai, silakan dicek
```

Setiap pesan dapat mempunyai:

- isi teks;
- pengirim;
- waktu;
- status dibaca;
- lampiran;
- tipe pesan;
- metadata edit atau hapus.

Untuk versi pertama, gunakan refresh/polling sederhana. Real-time WebSocket dapat ditambahkan setelah alur dasar stabil. Ini mengurangi risiko teknis saat MVP.

---

# 6. Rekomendasi sistem prioritas

Jangan membiarkan pengguna memilih prioritas akhir secara bebas. Pengguna boleh memilih tingkat urgensi awal, tetapi sistem atau petugas melakukan triase.

## Model yang mudah dipahami

Gunakan tiga faktor:

### 1. Dampak

| Nilai | Keterangan |
|---:|---|
| 1 | Hanya satu pengguna atau satu kamar |
| 2 | Beberapa pengguna |
| 3 | Satu area atau satu lantai |
| 4 | Satu gedung |
| 5 | Banyak gedung atau layanan utama |

### 2. Urgensi

| Nilai | Keterangan |
|---:|---|
| 1 | Tidak mendesak |
| 2 | Bisa menunggu beberapa hari |
| 3 | Mengganggu aktivitas |
| 4 | Harus ditangani hari yang sama |
| 5 | Harus segera ditangani |

### 3. Risiko keselamatan

| Nilai | Keterangan |
|---:|---|
| 0 | Tidak ada risiko |
| 3 | Risiko kerusakan atau gangguan serius |
| 5 | Potensi bahaya terhadap keselamatan |

Rumus awal:

```text
Skor = (Dampak x 2) + (Urgensi x 2) + Risiko Keselamatan
```

Contoh:

- tumpukan sampah kecil di dapur: dampak 2, urgensi 2, risiko 0  
  skor = 8
- Wi-Fi satu lantai mati: dampak 3, urgensi 3, risiko 0  
  skor = 12
- kabel listrik terbuka di area umum: dampak 4, urgensi 5, risiko 5  
  skor = 23

## Level prioritas

| Prioritas | Skor | Contoh | Target respons |
|---|---:|---|---|
| P1 Kritis | 20+ atau ada bahaya keselamatan | kebakaran, kabel terbuka, kebocoran besar | segera |
| P2 Tinggi | 14–19 | air satu lantai mati, pintu akses rusak | hari yang sama |
| P3 Normal | 7–13 | Wi-Fi terganggu, lampu area tertentu mati | 1–3 hari |
| P4 Rendah | 0–6 | permintaan kecil, kosmetik, saran | sesuai antrean |

Tambahkan pengecualian:

- laporan keamanan otomatis minimal P1/P2;
- laporan yang melibatkan banyak penghuni mendapat peningkatan dampak;
- laporan yang berulang dapat dinaikkan prioritas;
- manajer dapat mengoreksi prioritas dengan alasan;
- setiap perubahan prioritas dicatat di audit log.

Jangan menjadikan prioritas sebagai ukuran “siapa yang melapor lebih dulu”. Prioritas harus menggambarkan **dampak dan risiko**, bukan status sosial pelapor.

---

# 7. Rancangan database yang telah disepakati

Rancangan ini sengaja tidak membuat tabel `floors`, `roles`,
`supervisors`, `administrators`, atau `moderators`. Tiga role disimpan pada
pengguna. Jika Laravel digunakan, pembatasan akses diterapkan melalui
middleware dan policy, bukan melalui banyak kondisi di halaman.

## Entitas pengguna dan lokasi

```text
users
- id
- role                 -- resident | staff | manager
- name
- email
- password
- phone nullable
- building_id nullable -- wajib untuk resident; null untuk manager
- room_id nullable     -- wajib untuk resident jika tinggal di kamar
- is_active
- created_at
- updated_at

buildings
- id
- name
- is_active
- created_at
- updated_at

rooms
- id
- building_id
- room_number
- is_active
- created_at
- updated_at

service_units
- id
- name                 -- Jaringan, Kebersihan, Keamanan, Administrasi, dll.
- is_active
- created_at
- updated_at

service_unit_building
- service_unit_id
- building_id

user_service_unit
- user_id              -- digunakan untuk staff
- service_unit_id

user_building
- user_id              -- digunakan untuk staff dengan cakupan khusus
- building_id
```

`buildings` menunjukkan lokasi fisik. `service_units` menunjukkan unit kerja.
Keduanya dipisahkan karena satu unit dapat melayani banyak gedung dan satu
gedung dapat dilayani banyak unit. `user_building` membatasi petugas tertentu
jika cakupannya berbeda dari cakupan umum unit layanan. Untuk manajer,
pembatasan gedung tidak diterapkan.

## Entitas klasifikasi dan laporan

```text
categories
- id
- name                 -- Internet, Kebersihan, Keamanan, Administrasi, dll.
- default_service_unit_id nullable
- default_visibility   -- public_building | private | manager_only
- is_active
- created_at
- updated_at

reports
- id
- ticket_number unique
- reporter_id
- building_id
- room_id nullable
- category_id
- title
- description
- location_type        -- room | common_area | building
- location_description
- visibility           -- public_building | private | manager_only
- identity_visibility  -- visible | masked_to_residents | private
- impact_score
- urgency_score
- safety_risk_score
- priority             -- critical | high | normal | low
- priority_score
- status               -- submitted | assigned | in_progress |
                         -- waiting_confirmation | closed | reopened |
                         -- rejected | duplicate
- estimated_response_at nullable
- estimated_resolution_at nullable
- estimate_note nullable
- resolved_at nullable
- closed_at nullable
- created_at
- updated_at

report_assignments
- id
- report_id
- service_unit_id
- staff_id nullable
- assigned_by nullable  -- null jika dibuat oleh sistem
- assigned_at
- unassigned_at nullable
- note nullable
```

`categories` adalah jenis masalah, bukan tipe kamar. Atribut `slug` dan
`buildings.code` sengaja tidak digunakan pada MVP karena bukan kebutuhan
bisnis inti. Keduanya dapat ditambahkan kemudian jika URL, integrasi, atau
kode internal yang stabil benar-benar diperlukan.

`reports.building_id` dan `reports.room_id` adalah lokasi kejadian, bukan
salinan lokasi tempat tinggal pelapor. Keduanya tidak diisi manual sebagai
identitas penghuni: formulir mengambil lokasi kamar penghuni dari data akun
untuk laporan kamar pribadi, sedangkan laporan area bersama meminta penghuni
memilih gedung dan mengisi deskripsi lokasi. Data lokasi disimpan pada
`reports` agar tetap benar jika penghuni pindah gedung atau kamar setelah
laporan dibuat.

`room_id` boleh kosong untuk laporan gedung atau area bersama. Jika
`room_id` terisi, database dan validasi aplikasi harus memastikan kamar
tersebut berada pada `building_id` yang sama.

Sistem menetapkan prioritas berdasarkan dampak, urgensi, dan risiko
keselamatan. Penghuni tidak memilih prioritas akhir. Petugas memberikan
estimasi waktu respons atau penyelesaian; estimasi yang terlewati memicu
pengingat, bukan sanksi otomatis.

Penugasan awal dibuat oleh sistem ke unit layanan berdasarkan kategori dan
gedung. Manajer kemudian menetapkan petugas tertentu melalui
`report_assignments`. Pada rancangan awal tidak ada fitur petugas mengambil
laporan sendiri. Dengan demikian, pembagian tanggung jawab jelas dan semua
laporan memiliki pihak yang dapat dimintai pertanggungjawaban. Laporan
keamanan dan `manager_only` wajib melalui penugasan manajer.

## Percakapan, lampiran, dan histori

```text
report_comments
- id
- report_id
- user_id
- body
- visibility          -- public | participants | manager_only
- created_at
- updated_at
- deleted_at nullable
- deleted_by nullable

attachments
- id
- report_id nullable
- comment_id nullable
- uploaded_by
- storage_path
- original_name
- mime_type
- size
- checksum
- retained_until nullable
- created_at

report_status_histories
- id
- report_id
- from_status nullable
- to_status
- changed_by nullable  -- null jika perubahan otomatis oleh sistem
- note nullable
- created_at

report_priority_histories
- id
- report_id
- old_priority nullable
- new_priority
- score
- changed_by nullable
- reason nullable
- created_at

notifications
- id
- user_id
- type
- data
- read_at nullable
- created_at
```

Lampiran dapat melekat langsung pada laporan atau pada komentar. Dengan
demikian foto, audio, video, dan dokumen tidak memerlukan kolom terpisah.
Retensi awal yang disarankan adalah dua tahun setelah laporan ditutup;
metadata histori dapat disimpan lebih lama untuk evaluasi.

Tabel `report_watchers` tidak digunakan pada MVP. Istilah watcher bukan
pengguna yang sedang membuka halaman. Dalam aplikasi, watcher berarti
pengguna yang berlangganan pembaruan laporan dan menerima notifikasi.
Penerima notifikasi dapat ditentukan dari pelapor, petugas yang ditugaskan,
manajer, dan peserta komentar. Fitur berlangganan eksplisit dapat ditambah
kemudian jika benar-benar dibutuhkan.

## Aturan akses inti

```text
resident:
- laporan milik sendiri
- laporan public_building pada building_id yang sama

staff:
- laporan yang memiliki assignment aktif kepadanya
- manajer dapat melihat antrean sebelum petugas ditetapkan

manager:
- seluruh laporan dan histori
- dapat mengelola kategori, unit layanan, penugasan, dan koreksi prioritas
```

Penghuni lain melihat nama pelapor sesuai `identity_visibility`. Petugas
melihat nama dan nomor kamar bila diperlukan untuk pekerjaan. Manajer dapat
melihat nama, kamar, dan gedung. Nomor telepon tidak ditampilkan otomatis.

Status `waiting_confirmation` berarti petugas menyatakan pekerjaan selesai,
tetapi laporan belum ditutup. Penghuni dapat mengonfirmasi dan menutup
laporan atau memilih `reopened` jika masalah belum terselesaikan. Manajer
dapat menutup laporan secara administratif dengan alasan yang tercatat.

---

# 8. Tahap implementasi aktual dan rencana berikutnya

Bagian ini menggantikan roadmap awal yang hanya bersifat usulan. Penilaian
berikut dibuat berdasarkan isi proyek yang saat ini ada, termasuk migration,
model, controller, view, route, dan test. Status diperbarui pada **27 Oktober
2026**.

## Ringkasan status

| Tahap | Status | Penilaian |
|---|---|---|
| 0. Kebijakan akses dan alur | 🟡 Sebagian | Aturan privasi, visibilitas, role, dan transisi sudah dirumuskan serta sebagian diterapkan, tetapi belum seluruhnya menjadi policy/audit yang konsisten. |
| 1. Fondasi Laravel | ✅ Selesai untuk MVP | Login, role, user, gedung, kamar, kategori, unit layanan, profil, dashboard dasar, dan migration sudah tersedia. |
| 2. Tiket inti | 🟢 Hampir selesai | Pembuatan laporan, nomor tiket unik, lokasi, visibilitas, assignment, histori, dan lampiran gambar sudah berjalan. |
| 3. Triase dan prioritas | 🟢 MVP selesai | Skor otomatis, koreksi manajer, estimasi, transisi status, dan assignment sudah tersedia; antrean operasional masih sederhana. |
| 4. Percakapan dan notifikasi | 🔴 Belum dimulai | Model komentar dan notifikasi sudah ada, tetapi belum ada endpoint, form, UI, atau pengiriman notifikasi yang dapat digunakan. |
| 5. Fitur komunitas | 🔴 Belum dimulai | Belum ada feed khusus, dukungan “saya juga mengalami”, moderasi, laporan konten, atau duplikasi. |
| 6. Migrasi data lama | 🔴 Belum dimulai | Belum ada importer, pemetaan data legacy, validasi hasil, atau mode read-only untuk aplikasi lama. |

### Bukti implementasi yang sudah ada

- `routes/web.php` memiliki 13 route untuk login, dashboard, daftar/detail
  laporan, pembuatan laporan, triase, perubahan status, dan assignment.
- Migration sudah memisahkan `reports`, `report_assignments`,
  `report_comments`, `attachments`, histori status, histori prioritas, dan
  `notifications`.
- `ReportController` sudah memvalidasi kategori aktif, lokasi kamar/area,
  visibilitas, jawaban asesmen, serta maksimal lima gambar 10 MB.
- `ReportTriageController` sudah menerapkan transisi status sampai
  `waiting_confirmation`, assignment yang dibatasi unit layanan dan gedung,
  estimasi, serta histori perubahan.
- Test feature sudah mencakup pembuatan laporan, pembatasan kamar, visibilitas
  antar-gedung, dan penyamaran identitas.

### Batasan verifikasi saat ini

`php artisan route:list` berhasil menampilkan 13 route. `php artisan test`
belum dapat digunakan sebagai bukti lulus karena environment test mengarah ke
MySQL `127.0.0.1:3306`, sedangkan server MySQL tidak tersedia saat pemeriksaan
(connection refused). Sebelum menyimpulkan implementasi selesai, konfigurasi
test perlu diarahkan ke database SQLite sementara atau MySQL test yang
tersedia, lalu seluruh suite harus dijalankan ulang.

## Tahap 0 — Menutup kebijakan yang masih terbuka

**Status: ✅ Selesai untuk aturan inti.**

Kebijakan inti sekarang tercermin pada `ReportPolicy`, `ReportCommentPolicy`,
dan `AttachmentPolicy`, yang didaftarkan terpusat melalui `AppServiceProvider`.
Selesaikan pengerasan berikut sebelum menambah fitur baru:

- buat Policy/Gate terpusat untuk akses laporan, triase, komentar, dan lampiran;
- tetapkan siapa yang dapat menutup, membuka kembali, dan mengubah prioritas;
- tambahkan audit log untuk perubahan akses, assignment, prioritas, status, dan
  penghapusan lampiran;
- tetapkan masa retensi lampiran dan aturan penghapusan datanya;
- status `waiting_confirmation` wajib dilalui petugas sebelum penghuni atau
  manajer menutup laporan;
- penghuni pemilik laporan dapat menutup atau membuka kembali laporan;
- manajer dapat menutup atau membuka kembali laporan secara administratif;
- petugas aktif yang di-assign dan manajer dapat mengubah prioritas, dengan
  alasan wajib saat nilainya berubah;
- laporan dan lampiran yang ditutup memiliki masa retensi dua tahun.

### Skenario pengujian Tahap 0

Gunakan data uji berikut agar skenario dapat diulang:

| Data | Nilai |
|---|---|
| Gedung | Gedung A dan Gedung B |
| Penghuni | `resident_a` di Gedung A, `resident_b` di Gedung A, `resident_c` di Gedung B |
| Manajer | `manager_a` |
| Petugas aktif | `staff_a`, di-assign ke laporan Gedung A |
| Petugas lain | `staff_b`, tidak di-assign ke laporan tersebut |
| Laporan utama | `report_a`, pelapor `resident_a`, status awal `assigned` |
| Lampiran | satu lampiran pada `report_a` dan satu lampiran pada komentar |

#### Matriks skenario

| ID | Area | Pra-kondisi | Langkah uji | Hasil yang diharapkan |
|---|---|---|---|---|
| T0-01 | Akses laporan | `report_a` milik `resident_a` | Buka detail sebagai `resident_a` | Berhasil, HTTP 200. |
| T0-02 | Akses laporan publik | Laporan `public_building` berada di Gedung A | Buka sebagai `resident_b` | Berhasil; identitas kamar tidak dibocorkan. |
| T0-03 | Privasi antar-gedung | Laporan privat berada di Gedung A | Buka sebagai `resident_c` | Ditolak HTTP 403. |
| T0-04 | Akses petugas aktif | `staff_a` memiliki assignment aktif | Buka detail dan halaman triase | Berhasil. |
| T0-05 | Akses petugas non-assigned | `staff_b` tidak memiliki assignment aktif | Buka detail atau triase | Ditolak HTTP 403. |
| T0-06 | Akses manajer | `manager_a` aktif | Buka detail dan triase | Berhasil tanpa bergantung pada assignment. |
| T0-07 | Komentar peserta | Pengguna dapat melihat laporan dan status bukan `closed` | Kirim komentar teks valid | Redirect berhasil; baris `report_comments` tercipta dengan `user_id` yang benar. |
| T0-08 | Komentar pada laporan tertutup | `report_a` berstatus `closed` | Kirim komentar sebagai pelapor atau manajer | Ditolak HTTP 403; komentar tidak tercipta. |
| T0-09 | Validasi komentar | Pengguna berhak akses | Kirim body kosong atau lebih dari 5.000 karakter | Validasi gagal pada `body`; tidak ada komentar tersimpan. |
| T0-10 | Akses lampiran | Lampiran terhubung ke `report_a` | Unduh sebagai pelapor, `staff_a`, dan manajer | Berhasil; nama file dan content type sesuai metadata. |
| T0-11 | Lampiran tanpa akses | Lampiran laporan Gedung A | Unduh sebagai `resident_c` atau `staff_b` | Ditolak HTTP 403; file tidak dikirim. |
| T0-12 | Status pekerjaan petugas | `report_a` berstatus `in_progress`, `staff_a` aktif | Petugas mengirim status `waiting_confirmation` dengan catatan | Berhasil; status berubah dan histori status tercatat. |
| T0-13 | Konfirmasi penghuni | `report_a` berstatus `waiting_confirmation` | `resident_a` menekan “Konfirmasi selesai” | Status menjadi `closed`, `closed_at` terisi, dan histori `waiting_confirmation` → `closed` tercatat. |
| T0-14 | Penutupan terlalu dini | Status laporan `assigned`, `submitted`, atau `in_progress` | Penghuni mencoba menutup laporan | Ditolak HTTP 403; status tetap. |
| T0-15 | Penutupan oleh manajer | Status `waiting_confirmation` | `manager_a` menutup laporan | Berhasil; status menjadi `closed` dan histori mencatat manajer sebagai pelaku. |
| T0-16 | Pembukaan kembali oleh penghuni | Status `closed`, pelapor tersedia | Pelapor mengirim alasan lalu membuka kembali | Status menjadi `reopened`; `closed_at` dan `retained_until` menjadi `NULL`. |
| T0-17 | Pembukaan kembali oleh manajer | Status `closed` dan pelapor tidak dapat bertindak | Manajer mengirim alasan lalu membuka kembali | Berhasil; status menjadi `reopened` dan histori tercatat. |
| T0-18 | Pembukaan kembali tanpa hak | Status `closed` | `resident_b` atau petugas mencoba membuka kembali | Ditolak HTTP 403; status tetap `closed`. |
| T0-19 | Validasi alasan buka kembali | Status `closed` | Kirim alasan kosong | Validasi gagal pada `note`; status tetap. |
| T0-20 | Prioritas oleh petugas aktif | `staff_a` aktif dan ter-assign | Ubah prioritas dengan alasan | Berhasil; `priority` dan histori prioritas berubah. |
| T0-21 | Prioritas tanpa alasan | Prioritas baru berbeda dari nilai lama | Petugas/manajer mengirim perubahan tanpa alasan | Validasi bisnis gagal pada `reason`; prioritas tidak berubah. |
| T0-22 | Prioritas petugas non-assigned | `staff_b` tidak ter-assign | Ubah prioritas | Ditolak HTTP 403; histori tidak tercipta. |
| T0-23 | Prioritas penghuni | Penghuni bukan manajer/petugas | Akses endpoint triase | Ditolak HTTP 403. |
| T0-24 | Retensi laporan | Laporan berhasil ditutup pada waktu `T` | Periksa `retained_until` | Nilai sama dengan `T + 2 tahun`. |
| T0-25 | Retensi lampiran laporan | Lampiran langsung terhubung ke laporan | Tutup laporan lalu periksa lampiran | `attachments.retained_until` sama dengan batas retensi laporan. |
| T0-26 | Retensi lampiran komentar | Lampiran terhubung ke komentar | Tutup laporan lalu periksa lampiran komentar | Batas retensi ikut diisi dua tahun setelah penutupan. |
| T0-27 | Reset retensi saat reopen | Laporan dan lampiran sudah memiliki retensi | Buka kembali laporan | `retained_until` laporan dan lampiran kembali `NULL`. |
| T0-28 | Transisi ilegal | Laporan `assigned` | Petugas mencoba langsung ke `waiting_confirmation` tanpa `in_progress` | Ditolak; status tetap `assigned`. |
| T0-29 | Histori lengkap | Jalankan alur assigned → in_progress → waiting_confirmation → closed → reopened | Periksa histori | Setiap perubahan memiliki status asal, tujuan, pelaku, waktu, dan catatan yang benar. |
| T0-30 | Regresi login | Akun aktif dan tidak aktif tersedia | Login dengan kedua akun | Akun aktif berhasil; akun tidak aktif ditolak dan tetap guest. |

#### Kriteria penerimaan

Implementasi Tahap 0 dinyatakan lulus apabila:

1. T0-01 sampai T0-30 menghasilkan hasil yang diharapkan.
2. Tidak ada endpoint sensitif yang dapat dilewati dengan mengganti ID laporan
   atau ID lampiran.
3. Setiap perubahan status, prioritas, penutupan, dan pembukaan kembali memiliki
   histori yang dapat ditelusuri.
4. Status `closed` hanya dapat dicapai dari `waiting_confirmation`.
5. Tidak ada komentar atau lampiran baru yang dapat ditambahkan ke laporan
   tertutup.
6. Nilai retensi laporan dan seluruh lampiran konsisten dan di-reset ketika
   laporan dibuka kembali.

#### Pemetaan ke pengujian otomatis

Skenario berikut sebaiknya menjadi method PHPUnit di
`tests/Feature/ReportTest.php`:

- T0-01–T0-06: policy `view` dan `triage`;
- T0-07–T0-09: endpoint komentar dan validasi;
- T0-10–T0-11: policy dan endpoint lampiran;
- T0-12, T0-14, T0-28: transisi petugas;
- T0-13, T0-15–T0-19: close/reopen dan policy;
- T0-20–T0-23: perubahan prioritas;
- T0-24–T0-27: retensi laporan/lampiran;
- T0-29: histori status;
- T0-30: `tests/Feature/AuthenticationTest.php`.

Perintah validasi utama:

```text
php artisan test
```

Perintah tersebut harus dijalankan dengan database testing terisolasi, bukan
database development atau production.

## Tahap 1 — Fondasi Laravel

**Status: ✅ Selesai untuk MVP.**

Tidak perlu mengulang migration fondasi. Yang tersisa adalah pengerasan:

- tambahkan test untuk role pada setiap route sensitif;
- pindahkan pemeriksaan akses yang tersebar di controller ke Policy/Gate;
- lengkapi dashboard dengan metrik dan daftar kerja berdasarkan role;
- pastikan akun staf memiliki cakupan gedung dan unit layanan yang konsisten.

## Tahap 2 — Tiket inti

**Status: 🟢 Hampir selesai.**

Fitur inti sudah dapat membuat laporan dengan nomor tiket, kategori dinamis,
lokasi, visibilitas, prioritas awal, penugasan, histori, dan lampiran gambar.

Sebelum tahap ini ditutup:

- tambahkan route untuk mengunduh/menampilkan lampiran dengan pemeriksaan akses;
- validasi `room_id` terhadap gedung secara eksplisit di level request/database;
- tangani kegagalan penyimpanan file agar transaksi database tidak meninggalkan
  data lampiran yang tidak lengkap;
- tambahkan test untuk upload, akses lampiran, nomor tiket bersamaan, dan
  penghapusan/retensi file.

## Tahap 3 — Triase dan prioritas

**Status: 🟢 MVP selesai.**

Asesmen laporan menghasilkan `priority_score` dan prioritas otomatis. Manajer
dapat mengubah prioritas dengan alasan, mengatur estimasi, memilih unit layanan
dan staf, sedangkan staf dapat mengubah status melalui transisi yang dibatasi.

Pekerjaan penutupan tahap:

- buat antrean kerja nyata untuk staf dan manajer, bukan hanya daftar laporan;
- tampilkan filter gedung, unit layanan, status, prioritas, dan usia laporan;
- pisahkan aksi transisi manajer dan staf secara eksplisit;
- tambahkan test negatif untuk setiap transisi dan assignment lintas gedung;
- definisikan SLA serta notifikasi ketika estimasi terlewati.

## Tahap 4 — Percakapan dan notifikasi

**Status: 🔴 Belum dimulai (model dasar tersedia).**

Migration dan relasi `report_comments`/`attachments` sudah menjadi fondasi,
tetapi route dan UI komentar belum tersedia. Implementasikan berurutan:

1. endpoint dan form komentar teks dengan izin berdasarkan visibilitas laporan;
2. balasan penghuni, staf, dan manajer pada thread laporan;
3. lampiran yang terkait komentar, bukan hanya laporan;
4. notifikasi dalam aplikasi dan status sudah dibaca;
5. email setelah alur stabil;
6. polling atau WebSocket hanya bila kebutuhan real-time sudah terbukti.

## Tahap 5 — Fitur komunitas

**Status: 🔴 Belum dimulai.**

Kerjakan setelah akses komentar stabil: feed laporan publik per gedung,
“saya juga mengalami”, penandaan duplikat, moderasi, pelaporan konten, dan
penyamaran identitas untuk kategori sensitif. Fitur ini tidak boleh membuka
laporan `participants` atau data kamar kepada penghuni lain.

## Tahap 6 — Migrasi data lama

**Status: 🔴 Belum dimulai.**

Buat command/importer idempotent dan jalankan di salinan database terlebih
dahulu:

- `penghuni` menjadi `users`;
- `teknisi` dan `manajer` menjadi user dengan role;
- kategori lama dipetakan ke kategori baru;
- `pengaduan` menjadi `reports`;
- `bukti_foto` dan `bukti_penyelesaian` menjadi `attachments`;
- `penanganan_dini` dan `komentar_penyelesaian` menjadi komentar historis.

Tambahkan penanda `source = legacy`, laporan jumlah baris yang berhasil/gagal,
rekonsiliasi nomor tiket, dan validasi akses setelah import. Aplikasi lama
dipertahankan read-only sampai hasil migrasi disetujui.

## Urutan kerja yang disarankan sekarang

1. Perbaiki environment test dan pastikan suite lulus.
2. Selesaikan Policy/Gate serta test otorisasi.
3. Tutup Tahap 2 dengan akses lampiran yang aman dan test upload.
4. Sempurnakan antrean/SLA pada Tahap 3.
5. Implementasikan komentar teks dan notifikasi in-app pada Tahap 4.
6. Setelah data baru stabil, kerjakan importer Tahap 6 sebelum fitur komunitas
   yang lebih luas.

---

# 9. Perbaikan penting sebelum migrasi

Beberapa hal pada sistem lama sebaiknya tidak dibawa apa adanya:

1. Jangan menggunakan nomor HP sebagai bagian utama komunikasi sistem.
2. Jangan menampilkan data penghuni lain lebih banyak dari yang diperlukan.
3. Jangan menyimpan komentar sebagai kolom tetap pada tabel laporan.
4. Jangan menggunakan ENUM untuk kategori yang akan sering berubah.
5. Jangan mengandalkan ekstensi file untuk validasi upload.
6. Jangan membuat nomor tiket dengan `COUNT(*) + 1`.
7. Jangan membolehkan update status tanpa validasi transisi.
8. Jangan menghapus lampiran fisik tanpa memperbarui database.
9. Jangan menganggap laporan `Selesai` selalu benar-benar selesai—tambahkan konfirmasi penghuni atau mekanisme buka kembali.
10. Jangan menambahkan real-time chat sebelum model akses, moderasi, dan penyimpanan lampiran sudah benar.

---

# 10. Rekomendasi MVP

Agar cakupan tidak melebar terlalu cepat, versi pertama framework sebaiknya hanya berisi:

- login dan role;
- data gedung dan kamar;
- kategori dinamis;
- pembuatan laporan;
- lokasi dan visibilitas;
- prioritas otomatis;
- status dan penugasan;
- komentar teks antara penghuni dan petugas;
- lampiran gambar;
- riwayat status;
- dashboard teknisi dan manajer;
- forum laporan umum sederhana;
- audit log dasar.

Tunda terlebih dahulu:

- video berukuran besar;
- voice call;
- chat global seperti WhatsApp;
- aplikasi mobile;
- AI otomatis;
- microservices;
- sistem rating yang kompleks;
- integrasi WhatsApp resmi.

---

## Rekomendasi akhir

Gunakan **Laravel sebagai fondasi baru**, tetapi lakukan migrasi secara bertahap. Pertahankan konsep ticketing, role, pembagian berdasarkan gedung, alur teknisi, dan bukti penyelesaian dari sistem lama. Ganti model satu tabel pengaduan menjadi model laporan dengan komentar, lampiran, lokasi, visibilitas, prioritas, penugasan, dan histori.

Prioritas terpenting bukan tampilan atau real-time chat, melainkan:

1. desain data;
2. kontrol akses dan privasi;
3. alur status;
4. validasi lampiran;
5. audit trail;
6. mekanisme triase prioritas;
7. pemisahan laporan publik dan privat.

Jika fondasi ini benar, fitur forum, percakapan seperti WhatsApp, notifikasi real-time, aplikasi mobile, dan laporan analitik dapat ditambahkan tanpa harus merombak sistem lagi.