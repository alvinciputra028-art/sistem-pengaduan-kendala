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

# 8. Rencana migrasi yang realistis

Saya tidak menyarankan langsung menghapus aplikasi lama dan menulis ulang seluruh sistem sekaligus.

## Tahap 0 — Tentukan kebijakan

Sebelum coding, putuskan:

- laporan apa yang publik;
- siapa yang boleh melihat data;
- apakah identitas penghuni ditampilkan;
- berapa lama lampiran disimpan;
- bagaimana petugas memberikan estimasi;
- siapa yang boleh mengubah prioritas dan status;
- kapan laporan dianggap ditutup.

## Tahap 1 — Bangun fondasi Laravel

Migrasikan:

- login;
- role dan permission;
- pengguna;
- gedung;
- kamar;
- kategori;
- profil;
- password;
- dashboard dasar.

## Tahap 2 — Migrasikan fitur tiket inti

Implementasikan:

- membuat laporan;
- nomor tiket;
- kategori dinamis;
- lokasi;
- visibilitas;
- status;
- penugasan teknisi;
- riwayat status;
- bukti lampiran.

## Tahap 3 — Tambahkan triase dan prioritas

Implementasikan:

- skor prioritas;
- prioritas otomatis;
- koreksi oleh manajer dengan alasan;
- estimasi waktu dari petugas;
- antrean unit layanan berdasarkan prioritas.

## Tahap 4 — Tambahkan komentar

Mulai dari:

- komentar teks;
- balasan teknisi;
- komentar penghuni pada laporan publik;
- notifikasi email atau notifikasi dalam aplikasi.

Setelah stabil, tambahkan:

- foto;
- audio;
- video;
- status dibaca;
- polling atau WebSocket.

## Tahap 5 — Tambahkan fitur komunitas

Implementasikan:

- feed laporan umum per gedung;
- “saya juga mengalami”;
- laporan duplikat;
- komentar;
- moderasi;
- pelaporan konten;
- penyamaran identitas untuk kategori tertentu.

## Tahap 6 — Migrasi data lama

Jangan memasukkan data lama secara sembarangan. Buat skrip migrasi:

- `penghuni` menjadi `users`;
- `teknisi` dan `manajer` menjadi user dengan role;
- kategori lama dipetakan ke kategori baru;
- `pengaduan` menjadi `reports`;
- `bukti_foto` menjadi `attachments`;
- `bukti_penyelesaian` menjadi attachment atau komentar teknisi;
- `penanganan_dini` menjadi komentar historis;
- `komentar_penyelesaian` menjadi komentar historis.

Data lama sebaiknya diberi tanda:

```text
source = legacy
```

Aplikasi lama tetap disimpan sementara dalam mode read-only sampai data baru tervalidasi.

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