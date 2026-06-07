# Prediksi Kelulusan Mahasiswa

Aplikasi web sederhana untuk memprediksi apakah seorang mahasiswa lulus **tepat waktu**
atau tidak, berdasarkan data IPK, kehadiran, jumlah SKS lulus, dan status kerja.

Tugas mata kuliah Data Mining (klasifikasi). Ada 3 algoritma yang bisa dipilih lewat dropdown,
semuanya ditulis manual di PHP tanpa library machine learning:

- Naive Bayes
- K-Nearest Neighbors (KNN)
- Decision Tree



## Kebutuhan

- PHP 8.2+
- Composer
- MySQL 

## Cara Menjalankan


dependency Laravel:

   ```bash
   composer install
   ```

Copy file `.env`:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

database  **`mahasiswa1`**,


 file `database/mahasiswa1.sql` 

bagian database di file `.env`:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=mahasiswa1
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   
Struktur Data

Tabel `mahasiswa` berisi 500 data dengan kolom:

| Kolom | Keterangan |
|-------|------------|
| ipk | IPK mahasiswa (0 - 4) |
| kehadiran | persentase kehadiran |
| sks_lulus | jumlah SKS yang sudah lulus |
| status_kerja | Ya / Tidak |
| tepat_waktu | target prediksi: Ya / Tidak |
