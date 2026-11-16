# Sistem Informasi Santri — Pondok Pesantren Terpadu Khairul Ummah

Aplikasi manajemen data santri: pendaftaran, berkas, pembayaran, dan laporan.
Pengguna adalah **Wali Santri** dan **Admin / Tata Usaha**. Santri tidak login
sendiri.

Status implementasi: **Phase 8 selesai — tagihan SPP bulanan santri aktif.**
Admin membuat tagihan SPP satu bulan untuk setiap santri berstatus **Aktif**
(sekali tekan, idempoten: menekan ulang tidak menggandakan), wali melihat tagihan
bulanannya dan mengunggah bukti, lalu admin memutuskan `lunas` / `ditolak`.
Nominal diambil dari `pengaturan.biaya_spp_bulanan` dan tidak pernah datang dari
payload. Sebelumnya, sejak Phase 7 wali bisa menghubungkan data Santri lama ke
akunnya lewat pencarian NIS/NISN + nama + tanggal lahir, tanpa membuat Santri
baru atau mengubah statusnya; keputusan terima/tolak, pembayaran, dan aktivasi
berjalan sejak Phase 6. Berkas dokumen selain bukti pembayaran belum dibangun.

## Arsitektur

Monorepo dengan dua aplikasi terpisah; tidak ada Inertia, tidak ada Blade view.

| Path       | Isi                                                              |
| ---------- | ---------------------------------------------------------------- |
| `backend/` | Laravel 13 — REST API JSON, MySQL, Sanctum 4                    |
| `frontend/`| React 19 + TypeScript SPA, React Router 7, Axios, Tailwind 4      |

Frontend berjalan di port `5173`, API di `8000`. Keduanya dijalankan terpisah:

```
Terminal 1  →  cd backend  && php artisan serve      # http://localhost:8000
Terminal 2  →  cd frontend && npm run dev             # http://localhost:5173
```

## Prasyarat

| Kebutuhan  | Versi / lokasi                                                 |
| ----------- | -------------------------------------------------------------- |
| PHP         | 8.5 (`C:\php83\php.exe`) dengan `pdo_mysql` aktif                |
| Composer    | v2                                                              |
| Node.js     | 24 (`node --version`)                                           |
| MySQL       | MariaDB 10.4 dari XAMPP, port `3306`, user `root`, password kosong |
| XAMPP       | Service **MySQL** harus berjalan                                 |

> Di mesin ini `C:\php83\php.ini` semula mengomentari `extension=pdo_sqlite`
> dan `extension=sqlite3`, sehingga test gagal dengan `could not find driver`.
> Keduanya sudah diaktifkan. `memory_limit` juga dinaikkan ke `1G` karena
> PHPStan menabrak batas 128M. Cadangan: `C:\php83\php.ini.bak-opencode`.

## Setup

### 1. Database

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS khairul_ummah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Database `khairul_ummah` sudah dibuat. Yang sudah ada sebelumnya — `laravel`,
`erd_santri`, dan lainnya — tidak disentuh.

### 2. Backend

```powershell
cd backend
composer install
copy .env.example .env        # lewati bila .env sudah ada
php artisan key:generate
php artisan migrate
```

Nilai `.env` yang penting (`backend/.env`):

```
APP_NAME="Pondok Pesantren Terpadu Khairul Ummah"
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=khairul_ummah
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Frontend

```powershell
cd frontend
npm install
copy .env.example .env        # lewati bila .env sudah ada
```

`frontend/.env`:

```
VITE_API_URL=http://localhost:8000
```

## Database

Tabel di `khairul_ummah`. Lima tabel pertama bawaan Laravel, sisanya domain
(Phase 2).

| Tabel | Isi |
| ----- | --- |
| `users` | Akun. `role` = `enum('wali','admin')`, default `wali`, `no_hp` nullable varchar(25) |
| `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Bawaan Laravel |
| `tahun_ajaran` | Tahun ajaran, satu aktif pada satu waktu |
| `pengaturan` | Konfigurasi key/value, `kunci` jadi primary key |
| `santris` | Data mastersantri. `wali_id` bisa null (sejak Phase 7) untuk "santri lama" yang belum ditautkan ke akun wali |
| `pendaftaran` | Satu pendaftaran per santri per tahun ajaran. Unique `(santri_id, tahun_ajaran_id)`. Memuat `alasan_ditolak` sejak Phase 6 |
| `pembayarans` | Pembayaran, metode `dana` / `transfer_bank` / `tunai`. Phase 6 menambah pipa `jenis_pembayaran` (`pendaftaran`/`spp`), `pendaftaran_id` (unique, satu tagihan per pendaftaran), status `menunggu_verifikasi`/`ditolak`. Phase 8 menambah `periode` (hari pertama bulan yang ditagih), `jatuh_tempo`, metadata bukti (`bukti_path`, `bukti_mime_type`, `bukti_nama_asli`, `bukti_ukuran_bytes`), dan `diverifikasi_oleh` / `diverifikasi_pada`. Index unique `uniq_pembayaran_periode_spp` atas `(santri_id, tahun_ajaran_id, periode)` menjamin satu tagihan SPP per santri per tahun ajaran per bulan |
| `dokumen` | Berkas unggahan. Unique `(santri_id, jenis)`; jenis `bukti_pembayaran` dipakai untuk bukti bayar |
| `histori_status` | Audit trail transisi status, append-only |

## Model

| Model | Relasi | Scope |
| ----- | ------ | ----- |
| `Santri` | `wali`, `tahunAjaran`, `pendaftarans`, `pembayarans`, `dokumen`, `historiStatus`, `pendaftaranTerbaru` | `aktif()` |
| `TahunAjaran` | `santris`, `pendaftarans`, `pembayarans` | `active()` |
| `Pendaftaran` | `santri`, `tahunAjaran`, `diprosesOleh` | — |
| `Pembayaran` | `santri`, `tahunAjaran`, `dicatatOleh`, `diverifikasiOleh` | `lunas()`, `tagihanSpp()` |
| `Dokumen` | `santri`, `diverifikasiOleh` | — |
| `HistoriStatus` | `santri`, `olehOleh` | — |
| `User` | `santris`, `pembayaransDicatat`, `dokumenDiverifikasi` | — |

`Santri` juga punya `berkasLengkap()` — `true` hanya bila semua
`JenisDokumen::cases()` sudah berstatus `diterima`.
`Pengaturan` memakai `kunci` sebagai primary key dengan `read()` / `write()`.

### Enum

`UserRole`, `SantriStatus`, `JenisKelamin`, `MetodePembayaran`,
`JenisPembayaran`, `StatusPembayaran`, `JenisDokumen`, `StatusDokumen`,
`StatusPendaftaran` — semuanya di `backend/app/Enums/` dengan `label()` untuk UI.

> `SantriStatus`, `StatusPendaftaran`, `StatusPembayaran`, dan `JenisKelamin`
> sudah dicerminkan di `frontend/src/types/auth.ts` karena `WaliDashboardPage`
> dan `WaliSantriPage` menampilkannya; label di sana harus selalu sama dengan
> `label()` enum-nya. Sejak Phase 6, `StatusDokumen`, `MetodePembayaran`, dan
> `JenisPembayaran` dicerminkan di `frontend/src/types/pembayaran.ts` karena
> halaman pembayaran menampilkan *proof* dan tagihan. `JenisDokumen` belum
> dicerminkan — hanya `bukti_pembayaran` yang tampil di layar sejauh ini.

## Factory

Semua di `backend/database/factories/`, dengan state untuk kasus umum:
`UserFactory` `->wali()` / `->admin()` · `TahunAjaranFactory` `->aktif()` ·
`SantriFactory` `->aktif()`, `->verifikasi()`, `->menungguPembayaran()`,
`->ditolak()`, `->pendaftaran()`, `->dibatalkan()`, `->untukWali()`,
`->untukTahunAjaran()` ·
`PembayaranFactory` `->lunasTunai()`, `->lunasDana()`,
`->lunasTransferBank()`, `->spp()` · `DokumenFactory` `->diterima()`, `->ditolak()` ·
`HistoriStatusFactory` `->oleh()`, `->transisi()`.

> `SantriFactory::pendaftaran()` dan `->dibatalkan()` ada karena Phase 8 hanya
> menagih santri `Aktif`; keduanya dipakai untuk membuktikan santri yang **tidak**
> aktif tidak pernah mendapat tagihan. `PembayaranFactory::spp()` hanya menandai
> jenis dan `periode` — nominalnya sengaja tidak ditetapkan, karena generator
> yang menentukannya dari `pengaturan`.

## Seeder

`php artisan db:seed` menjalankan 8 seeder berurutan: `UserSeeder` →
`TahunAjaranSeeder` → `PengaturanSeeder` → `SantriSeeder` →
`PendaftaranSeeder` → `PembayaranSeeder` → `DokumenSeeder` →
`HistoriStatusSeeder`.

Semuanya berbasis `updateOrCreate`, jadi **aman dijalankan berulang kali**.
Data awal:

| Tabel | Jumlah | Isi |
| ----- | ------ | --- |
| `users` | 3 | 1 admin + 2 wali, password `password` |
| `tahun_ajaran` | 3 | tahun lalu, tahun berjalan (aktif), tahun depan |
| `pengaturan` | 12 | nama/alamat pondok, nomor DANA, rekening, biaya daftar & SPP, dan `spp_tanggal_jatuh_tempo` (ditambahkan Phase 8) |
| `santris` | 3 | 2 milik satu wali, 1 milik wali lain |
| `pendaftaran` | 3 | status bervariasi (`diterima`, `diverifikasi`, `diajukan`) |
| `pembayarans` | 3 | 1 `lunas` via DANA, 2 `belum` |
| `dokumen` | 7 | 5 diterima untuk santri aktif, 2 menunggu |
| `histori_status` | 9 | rantai transisi dari `pendaftaran` sampai `aktif` |

Akun seed (semua password `password`):
`admin@khairulummah.test`, `wali.ahmad@example.com`, `wali.siti@example.com`.

| Method | URI                        | Auth      | Keterangan                                        |
| ------ | -------------------------- | --------- | ------------------------------------------------- |
| GET    | `/api/health`               | —         | Liveness check + envelope JSON standar            |
| GET    | `/`                        | —         | Redirect ke `/api/health`                         |
| GET    | `/up`                      | —         | Health check bawaan Laravel                       |
| GET    | `/sanctum/csrf-cookie`      | —         | Menerbitkan cookie `XSRF-TOKEN`                  |
| POST   | `/api/auth/register`        | —         | Daftar wali; sesi langsung aktif                 |
| POST   | `/api/auth/login`           | —         | Login; sesi langsung aktif                       |
| POST   | `/api/auth/forgot-password` | —         | Kirim tautan reset; respons selalu sama           |
| POST   | `/api/auth/reset-password`  | —         | Simpan kata sandi baru, cabut semua token          |
| POST   | `/api/logout`               | sanctum   | Hapus semua token milik user, invalidasi session  |
| GET    | `/api/user`                 | sanctum   | Profil user yang sedang login                     |
| GET    | `/api/wali/dashboard`       | role wali | Ringkasan sendiri: Santri, pendaftaran, tagihan   |
| GET    | `/api/wali/profile`         | role wali | Profil wali yang sedang login                     |
| PUT    | `/api/wali/profile`         | role wali | Ubah `name` dan `no_hp` saja                       |
| PUT    | `/api/wali/password`        | role wali | Ganti password; wajib password lama yang benar     |
| GET    | `/api/wali/santri`          | role wali | Santri milik wali tersebut saja                  |
| POST   | `/api/wali/santri-lama/match` | role wali | Cari Santri lama dari NIS/NISN + nama + tanggal lahir; 404 bila tidak cocok |
| POST   | `/api/wali/santri-lama/register` | role wali | Hubungkan Santri hasil pencarian ke akun wali yang sedang login |
| POST   | `/api/wali/pendaftaran`     | role wali | Ajukan pendaftaran; buat baris `santris` + `pendaftaran` |
| GET    | `/api/wali/pendaftaran`     | role wali | Pendaftaran milik wali tersebut saja             |
| GET    | `/api/wali/pendaftaran/{id}`| role wali | Detail satu pendaftaran milik wali tersebut       |
| GET    | `/api/wali/pembayaran`      | role wali | Tagihan biaya pendaftaran milik wali tersebut saja |
| GET    | `/api/wali/pembayaran/{id}` | role wali | Detail satu tagihan milik wali, dengan status bukti |
| POST   | `/api/wali/pembayaran/{id}/bukti` | role wali | Unggah / ganti bukti bayar; wajib pendaftaran sudah diterima |
| GET    | `/api/wali/pembayaran/{id}/bukti` | role wali | Streaming berkas bukti milik wali tersebut        |
| GET    | `/api/admin/overview`       | role admin| Ringkasan seluruh pondok                          |
| GET    | `/api/admin/pendaftaran`    | role admin| Seluruh pendaftaran dari semua wali                |
| GET    | `/api/admin/pendaftaran/{id}`| role admin| Detail satu pendaftaran, termasuk pemohon          |
| PUT    | `/api/admin/pendaftaran/{id}/status` | role admin | Terima/tolak pendaftaran; menerima membuka tagihan biaya pendaftaran |
| GET    | `/api/admin/pembayaran`     | role admin| Seluruh tagihan biaya pendaftaran (antrean tinjauan)|
| GET    | `/api/admin/pembayaran/{id}`| role admin| Detail satu tagihan, termasuk bukti                |
| PUT    | `/api/admin/pembayaran/{id}/status` | role admin | Konfirmasi `lunas` (sekalian mengaktifkan Santri) atau tolak bukti |
| GET    | `/api/admin/pembayaran/{id}/bukti` | role admin| Streaming berkas bukti untuk peninjauan            |
| GET    | `/api/wali/spp`             | role wali | Tagihan SPP bulanan milik wali tersebut saja       |
| GET    | `/api/wali/spp/{id}`        | role wali | Detail satu tagihan SPP milik wali, dengan status bukti |
| POST   | `/api/wali/spp/{id}/bukti`  | role wali | Unggah / ganti bukti SPP; wajib tagihan `belum` atau `ditolak` |
| GET    | `/api/wali/spp/{id}/bukti`  | role wali | Streaming berkas bukti SPP milik wali tersebut     |
| GET    | `/api/admin/spp/tahun-ajaran` | role admin | Daftar tahun ajaran untuk pemilih generator SPP  |
| POST   | `/api/admin/spp/generate`   | role admin| Buat tagihan SPP satu bulan untuk seluruh santri Aktif (idempoten) |
| GET    | `/api/admin/spp`            | role admin| Antrean seluruh tagihan SPP; filter `tahun_ajaran_id`, `periode`, `status` |
| GET    | `/api/admin/spp/{id}`       | role admin| Detail satu tagihan SPP, termasuk bukti            |
| PUT    | `/api/admin/spp/{id}/status` | role admin | Konfirmasi `lunas` atau tolak bukti; **tidak** mengubah status Santri |
| GET    | `/api/admin/spp/{id}/bukti` | role admin| Streaming berkas bukti SPP untuk peninjauan        |

Tidak ada `PUT` / `DELETE` polos atas `/api/admin/pendaftaran/{id}` maupun
`/api/admin/pembayaran/{id}` — route tersebut memang tidak ada, jadi menjawab
`405`. Satu-satunya cara mengubah status adalah dua endpoint
`.../{id}/status` di atas.

Kegagalan validasi `422` memakai envelope yang sama, dengan `message` diawali
pesan field pertama dan `data.errors` berisi peta per field. Pesan `404`
(disumber, termasuk pendaftaran milik wali lain) dan `401` juga dinormalkan ke
envelope yang sama, bukan JSON polos dari Laravel.

Bentuk respons:

```json
{
    "success": true,
    "message": "Layanan berjalan",
    "data": { "service": "…", "environment": "local", "api_prefix": "api" }
}
```

Respons 403 dari middleware role selalu menyertakan `data.your_role` dan
`data.required_roles` supaya SPA bisa menjelaskan penolakan.

Kegagalan validasi (422) dinormalisasi di `bootstrap/app.php` supaya tetap
memakai envelope yang sama: `{ success: false, message, data: { errors } }`.
Key `errors` di level atas sengaja dipertahankan agar
`assertJsonValidationErrors()` milik Laravel tetap berfungsi; SPA hanya membaca
`data.errors`. `message` memakai pesan field pertama, bukan ringkasan
`"(and N more errors)"` yang hanya berguna untuk developer.

CORS dikonfigurasi di `backend/config/cors.php` dan mengizinkan origin dari
`FRONTEND_URL` / `FRONTEND_URL_ALT` dengan `supports_credentials` aktif, supaya
Sanctum cookie flow bisa dipasang tanpa mengubah config.

## Alur pendaftaran, pembayaran, dan aktivasi (Phase 6)

Satu-satunya jalan menuju Santri **Aktif** adalah: pendaftaran **diterima**
*dan* tagihan biaya pendaftaran **lunas**.

1. Wali mengajukan pendaftaran (`POST /api/wali/pendaftaran`) → pendaftaran
   `diajukan`, santri `pendaftaran`.
2. Wali (bisa tanpa campur tangan) → santri `verifikasi` saat berkas diverifikasi;
   pembatasan antarstatus *tidak* otomatis di Phase 6.
3. Admin memutuskan lewat `PUT /api/admin/pendaftaran/{id}/status` dengan
   `status` = `diterima` atau `ditolak` (alasan wajib saat menolak, *dilarang*
   saat menerima). Keputusan satu kali: setelah diputus, status terminal.
   - **Diterima** → santri `menunggu_pembayaran` dan, dalam transaksi yang sama,
     tagihan biaya pendaftaran dibuat dari `pengaturan.biaya_pendaftaran`
     (nominal tidak pernah datang dari payload) dan dihubungkan ke pendaftaran
     ini. Index unique `(pendaftaran_id)` di `pembayarans` menjamin satu tagihan.
   - **Ditolak** → `alasan_ditolak` disimpan dan dibacakan kembali oleh wali.
   Menerima **bukan** mengaktifkan.
4. Wali mengunggah bukti lewat `POST /api/wali/pembayaran/{id}/bukti` → tagihan
   `menunggu_verifikasi`. Unggahan hanya legal saat pendaftaran sudah `diterima`
   dan tagihan `belum` / `ditolak` (unggah ulang setelah ditolak).
5. Admin meninjau bukti lewat `PUT /api/admin/pembayaran/{id}/status`:
   - `lunas` → tagihan lunas, dokumen bukti `diterima`, dan **santri `aktif`** —
     ketiganya ditulis dalam satu transaksi dengan row lock, jadi tidak ada
     keadaan "tagihan lunas tapi santri belum aktif" atau sebaliknya.
   - `ditolak` → tagihan `ditolak`, dokumen bukti `ditolak`, antrean unggah ulang
     terbuka kembali. Catatan admin ikut disimpan dan dilihat wali.

State machine hidup di enum (`StatusPendaftaran::canTransitionTo()`,
`StatusPembayaran::canTransitionTo()`, `StatusDokumen::canTransitionTo()`),
bukan di controller: tidak ada endpoint yang bisa memaksa transisi ilegal seperti
`ditolak → lunas` atau `belum → lunas`.

Keamanan berkas: aturan `mimetypes` tidak dipakai karena klien bisa mengaku apa
saja. `UploadBuktiPembayaranRequest` *menyaring byte asli* berkas dengan `finfo`
(`mimeTerpercaya()`) dan hanya menerima `application/pdf`, `image/jpeg`, atau
`image/png`, maksimal 2 MB. Nilai hasil penyaringan itulah yang disimpan sebagai
`dokumen.mime_type` dan dikirim sebagai `Content-Type` saat streaming. Penyimpanan
di disk privat `local` dengan nama file dihasilkan server; `nama_asli` hanya label
tampilan. Berkas lama dihapus ketika diganti.

Kepemilikan wali adalah bagian dari query, bukan filter pasca-ambil: membaca,
mengunggah, maupun me-stream berjalan lewat `whereHas('santri', wali_id = sesi)`,
jadi id tagihan milik wali lain menjawab `404`, bukan `403`. Admin tidak punya
filter itu — meninjau semua memang pekerjaannya — sehingga wali yang mencoba
route admin menerima `403` dari middleware `role:admin`, bukan `404`.
`bukti.url` di resource juga dibentuk sesuai peran pemanggil (`api.wali.*` vs
`api.admin.*`), jadi tidak ada URL yang menunjuk route yang tak bisa dipakai.

## Alur registrasi Santri Lama (Phase 7)

Website ini dibuat setelah data Santri Lama sudah ada di basis data, sehingga
ada baris `santris` yang belum punya `wali_id` — belum terhubung ke akun wali
mana pun. Untuk menutup celah itu, wali bisa menghubungkan data tersebut ke
akunnya sendiri lewat dua endpoint, keduanya di balik `auth:sanctum` +
`role:wali`:

1. **Cari — `POST /api/wali/santri-lama/match`.** Wali mengirim tiga data:
   NIS/NISN, nama lengkap, dan tanggal lahir. Ketiganya dipakai bareng untuk
   pencocokan; nomor saja atau nama saja tidak cukup. Response berisi proyeksi
   aman (`id`, `nis`, `nisn`, `nama_lengkap`, `jenis_kelamin`,
   `tempat_lahir`, `tanggal_lahir`, `status` + label) — tanpa `wali_id`, tanpa
   data akun, tanpa password. Tidak ketemu: `404` dengan pesan **"Data Santri
   Tidak Ditemukan."**, dan alur berhenti di situ — tidak ada loop input ulang,
   tidak ada kolom tambahan.
2. **Daftarkan — `POST /api/wali/santri-lama/register`.** Wali mengirim tiga
   data identifikasi yang sama; server mengulang pencarian itu sendiri di
   dalam request dan menempelkan baris yang ditemukan ke `$request->user()`.

Yang dijamin di dua endpoint tersebut:

- **Bukan `CREATE`, tapi `MATCH` + `LINK`.** Baris yang diklaim adalah baris
  yang sudah ada; tidak ada Santri baru yang dibuat dan `status` tidak
  disentuh — Santri yang sudah aktif tetap aktif.
- **Tidak ada `santri_id` atau `wali_id` di payload mana pun.** Karena
  `register` mengulang pencarian, satu-satunya baris yang bisa diklaim adalah
  baris yang cocok dengan tiga data yang diketik wali; tidak ada cara menunjuk
  baris atau pemilik lain dari sisi klien. `wali_id` ditulis dari
  `$request->user()`.
- **Anti-duplikasi.** Sudah milik akun yang sama → `409` "Data Santri sudah
  terdaftar pada akun Anda." Sudah milik wali lain → `409` "Data Santri sudah
  terhubung dengan akun lain." — tanpa memindahkan baris dan tanpa menyebut
  pemilik lain. Pencarian, pengecekan, dan penulisan terjadi dalam satu
  transaksi dengan row lock, jadi dua wali tidak bisa sama-sama membaca "belum
  punya wali" lalu saling menimpa.
- **Tidak ada kebocoran data.** Response hanya memuat kolom yang sudah diketik
  wali sendiri, dan pesan galat tidak membedakan "tidak pernah ada" dari
  "sudah dipakai wali lain".

Kolom `santris.wali_id` sekarang nullable (migrasi additive Phase 7) untuk
mengakomodasi baris Santri Lama. Constraint lama "setiap Santri pasti punya
wali" tidak lagi berlaku; endpoint ini adalah satu-satunya jalan yang menulis
kolom itu untuk baris yang belum punya wali.

## Alur tagihan SPP bulanan (Phase 8)

SPP adalah tagihan berulang, bukan sekali per pendaftaran. Karena itu tagihannya
**baris `pembayarans` yang persisten** (dibuat lebih dulu oleh admin), bukan
sesuatu yang dibuat saat wali membuka halaman — kalau dibuat malas, setiap kali
halaman dibuka bisa muncul tagihan baru.

Satu tagihan SPP yang sah dikenali dari `jenis_pembayaran = 'spp'` **dan**
`periode IS NOT NULL`. Scope `Pembayaran::tagihanSpp()` memakai syarat itu, jadi
baris SPP lama yang tidak punya `periode` tidak pernah ikut terdaftar.

1. **Admin membuat tagihan satu bulan — `POST /api/admin/spp/generate`.**
   Payload hanya `periode` (`YYYY-MM`) dan `tahun_ajaran_id` opsional (default
   tahun ajaran aktif). **Tidak ada field nominal** — jumlahnya dibaca dari
   `pengaturan.biaya_spp_bulanan`. Untuk setiap Santri berstatus **Aktif** pada
   tahun ajaran itu, dibuat satu baris dengan `periode` = hari pertama bulan
   tersebut dan `jatuh_tempo` = tanggal `spp_tanggal_jatuh_tempo` di bulan itu
   (dijepit `1..28` supaya tidak ada "31 Februari").
   - **Idempoten.** Index unique `uniq_pembayaran_periode_spp` atas
     `(santri_id, tahun_ajaran_id, periode)` yang menjaganya; menekan tombol dua
     kali membalas jumlah `dibuat` vs `dilewati`, bukan menggandakan tagihan.
   - **Hanya santri Aktif, dan tidak ada pengisian ulang otomatis.** Santri yang
     baru aktif belakangan tidak mendapat tagihan bulan-bulan sebelumnya, dan
     santri yang dinonaktifkan setelah ditagih tetap punya tagihan itu. Tapi
     karena filter membaca status **sekarang**, menjalankan ulang bulan yang sudah
     lewat akan menagih santri yang baru aktif setelah bulan itu. Bulan yang
     sudah selesai dianggap beres kecuali memang itu yang diinginkan.
   - Nominal **disimpan** pada baris tagihan. Mengubah `biaya_spp_bulanan` nanti
     tidak mengubah tagihan yang sudah terbit, sama seperti `jatuh_tempo`.
2. **Wali melihat dan membayar.** `GET /api/wali/spp` dan `/api/wali/spp/{id}`
   hanya menampilkan tagihan milik santri di bawah wali tersebut
   (`whereHas('santri', wali_id = sesi)`). Unggah bukti lewat
   `POST /api/wali/spp/{id}/bukti` hanya legal saat tagihan `belum` atau `ditolak`
   (unggah ulang setelah ditolak), dan **hanya** bila barisnya benar-benar tagihan
   SPP. Pipeline berkasnya sama dengan Phase 6 — sniff `finfo`, disk privat
   `local`, nama file dari server, PDF/JPG/PNG maksimal 2 MB — tetapi buktinya
   disimpan di kolom `pembayarans`, bukan di tabel `dokumen`, karena satu
   pembayaran SPP tidak perlu entri dokumen tersendiri. Berkas lama dihapus saat
   diganti.
3. **Admin memutuskan — `PUT /api/admin/spp/{id}/status`.** `lunas` hanya boleh
   dari `menunggu_verifikasi` yang benar-benar punya bukti; `ditolak` membuka
   antrean unggah ulang. Keputusan dibaca dan ditulis dalam satu transaksi dengan
   row lock, jadi dua admin tidak bisa memutus tagihan yang sama dari keadaan
   basi.
   - **`lunas` di SPP tidak mengubah status Santri.** Berbeda dengan tagihan
     `pendaftaran` yang mengaktifkan santri, santri penerima SPP sudah Aktif sejak
     sebelum ditagih. Tidak ada langkah aktivasi di sini, dan tidak seharusnya ada.

Yang dijaga pada Phase 8:

- **Klien tidak bisa menentukan uang atau status akhir.** Tidak ada nominal di
  payload generator, dan tidak ada endpoint yang menerima `lunas` dari wali.
  Satu-satunya penulis keputusan adalah `.../status` milik admin.
- **Kepemilikan sebagai kondisi query.** Id tagihan SPP milik wali lain menjawab
  `404`, bukan `403`; route admin dari wali menjawab `403` dari middleware
  `role:admin` — sama seperti alur pendaftaran.
- **Filter yang salah ditolak, bukan diabaikan.** `FilterSppRequest`
  memvalidasi `tahun_ajaran_id`, `periode` (pola `YYYY-MM`), dan `status`, jadi
  filter cacat menjawab `422`, bukan tabel kosong yang tak bisa dibedakan dari
  "memang tidak ada".
- **Baris SPP lama tetap aman.** Data dev punya tiga baris `jenis_pembayaran=spp`
  tanpa `periode`. MySQL memperlakukan `NULL` sebagai nilai unik pada index
  `(santri_id, tahun_ajaran_id, periode)`, jadi baris-baris itu koeksis dengan
  tagihan baru dan tidak pernah muncul di antrean Phase 8.

## Autentikasi

Laravel Sanctum 4.3.3 resmi, mode **stateful**: SPA memakai cookie sesi dan
tidak pernah menyimpan token di `localStorage`. Token yang dikembalikan di body
hanya ada untuk pemanggil non-browser, dan tidak pernah sampai ke SPA.

Alur di browser:

1. SPA memanggil `GET /sanctum/csrf-cookie` sekali per muat halaman. Cookie
   `XSRF-TOKEN` terbaca JavaScript; cookie sesi `httpOnly` tidak.
2. Setiap POST didahului oleh pemanggilan itu, lalu Axios mengirim
   `X-XSRF-TOKEN` (`withXSRFToken: true`). Tanpa header ini Laravel membalas
   `419`.
3. `AuthContext` memanggil `GET /api/user` saat mount untuk mencari tahu sesi
   yang ada. `401` di sana adalah hal normal untuk pengunjung, bukan error.

Masa berlaku tujuh hari, disetel di dua tempat yang harus selalu sama:
`config/sanctum.php` (`expiration`) untuk token, dan `SESSION_LIFETIME` untuk
cookie sesi. Sesi inilah yang sebenarnya mengakhiri login di browser.

Tautan reset kata sandi diarahkan ke SPA memakai hook resmi
`ResetPassword::createUrlUsing()` di `AppServiceProvider`. Tanpa itu, notifikasi
bawaan akan memanggil `route('password.reset')` yang tidak ada di aplikasi API
saja dan berakhir `500`.

`POST /api/auth/forgot-password` menjawab pesan yang persis sama apakah email
terdaftar atau tidak. Membocorkan itu sama dengan membocorkan email mana yang
memiliki akun.

## Akun Wali

`users.no_hp` adalah **nomor wali**, bukan nomor Santri — `santris.no_hp` sudah
ada terpisah. Kolomnya nullable untuk semua user, tapi hanya `PUT
/api/wali/profile` yang mengisinya; akun admin dibiarkan kosong.

Wali boleh mengubah **nama** dan **nomor HP** saja. `email`, `role`, dan `id`
tidak pernah diterima dari client, dan `WaliProfileController` menetapkan
atributnya satu per satu, bukan mass assignment — jadi perubahan pada form
request di masa depan tidak bisa diam-diam melebar ke kolom sensitif.

Tidak ada route profil yang menerima ID. `GET /api/wali/profile/3` balas `404`,
bukan `403`: permukaannya memang tidak pernah dibangun, dan itu pernyataan yang
lebih kuat daripada sekadar memeriksa role. `user_id` di query string juga
diabaikan, karena subjeknya selalu `$request->user()`.

Nomor HP kosong atau hanya spasi disimpan sebagai `null`, bukan `""`, supaya
"belum diisi" dan "diisi lalu dikosongkan" tidak jadi dua keadaan berbeda yang
keduanya tampak kosong.

Ganti password mewajibkan password lama yang benar, memakai aturan `current_password`
milik Laravel. Setelah berhasil, **semua token dicabut** — dalam mode stateful
tidak ada cara mengetahui token mana yang milik browser mana, jadi penggantian
password harus gagal tertutup. Cookie sesi sendiri dibiarkan, supaya wali yang
baru saja mengganti password tidak langsung terkeluar dari tab yang sedang
dipakai.

Dashboard menghitung semuanya di server lewat relasi `wali_id`. Tidak ada filter
di browser: filter di sisi klien berarti angkanya dihitung dari baris yang
seharusnya tidak pernah dikirim ke sana. Nominal uang dikembalikan sebagai string
`decimal(15,2)` dua desimal dan tidak pernah dibulatkan di SPA.

## Halaman SPA

| Rute               | Isi                                                              |
| ------------------ | ---------------------------------------------------------------- |
| `/login`           | Form login sungguhan                                             |
| `/register`        | Pendaftaran wali                                                 |
| `/forgot-password` | Minta tautan reset                                               |
| `/reset-password`  | Simpan kata sandi baru dari `?token=&email=`                     |
| `/wali/dashboard`  | Ringkasan, dari `GET /api/wali/dashboard`                        |
| `/wali/santri`     | Daftar Santri milik wali, dari `GET /api/wali/santri`            |
| `/wali/santri-lama` | Form identifikasi (NIS/NISN + nama + tanggal lahir), hasil pencarian, dan tombol "Daftarkan Data Santri" |
| `/wali/pendaftaran` | Daftar pendaftaran wali + formulir pengajuan baru              |
| `/wali/pendaftaran/:id` | Detail satu pendaftaran milik wali sendiri, termasuk alasan penolakan |
| `/wali/pembayaran` | Daftar tagihan biaya pendaftaran milik wali                    |
| `/wali/pembayaran/:id` | Detail tagihan + unggah bukti bayar / ganti bukti yang ditolak |
| `/wali/spp`        | Daftar tagihan SPP bulanan milik wali                            |
| `/wali/spp/:id`    | Detail tagihan SPP + unggah bukti (saat `belum` / `ditolak`)      |
| `/wali/profil`     | Ubah nama / no HP dan ganti password                             |
| `/admin/dashboard` | Ringkasan, dari `GET /api/admin/overview`                        |
| `/admin/pendaftaran` | Antrean tinjauan seluruh pendaftaran                           |
| `/admin/pendaftaran/:id` | Detail pendaftaran + tombol terima/tolak (alasan wajib saat menolak) |
| `/admin/pembayaran` | Antrean tagihan biaya pendaftaran, sorot yang menunggu verifikasi |
| `/admin/pembayaran/:id` | Detail tagihan + keputusan `lunas` (mengaktifkan santri) / tolak bukti |
| `/admin/spp`       | Pembuat tagihan bulanan + antrean SPP dengan filter tahun/periode/status |
| `/admin/spp/:id`   | Detail tagihan SPP + keputusan `lunas` / tolak (tidak mengubah status santri) |
| `*`                | 404                                                              |

Struktur penting: `src/lib/api.ts` (Axios + CSRF + interceptor 401),
`src/router.tsx`, `src/routes/RequireRole.tsx` (gerbang peran, menunggu sesi
selesai dimuat), `src/context/AuthContext.tsx`, `src/layouts/DashboardLayout.tsx`
(admin), `src/layouts/WaliLayout.tsx` (sidebar wali), `src/types/pendaftaran.ts`
(mirror dari resource dan form request backend). Phase 8 menambah
`src/types/spp.ts` sebagai mirror `TagihanSppResource`.

Komponen pendaftaran yang dipakai bersama kedua peran:
`src/components/PendaftaranForm.tsx` (khusus wali), `PendaftaranDetailView.tsx`,
dan `StatusBadge.tsx`. `SelectField.tsx` serta `TextAreaField.tsx` adalah saudara
`FormField.tsx`: ketiganya menyambungkan `aria-invalid` dan `aria-describedby`
dengan pesan galat yang sama.

Phase 8 memakai kembali pola yang sama: `src/components/TagihanSppDetailView.tsx`
adalah rincian tagihan SPP yang dipakai halaman wali maupun admin, dan
`src/components/DetailRow.tsx` (`Baris`, `formatBytes`) diekstrak dari
`PembayaranDetailView`/`PendaftaranDetailView` supaya blok baris detail tidak
disalin untuk ketiga kalinya.

`WaliLayout` terpisah karena navigasi wali adalah sidebar vertikal dengan
section-nya sendiri. `DashboardLayout` hanya menampilkan link milik peran yang
sedang masuk — menampilkan link admin ke wali akan berakhir `403` dari
`role:admin`. Item Notifikasi dirender sebagai `<span aria-disabled>` bertanda
"Segera", bukan `<NavLink>`, jadi tidak ada href yang bisa diikuti atau
diaktifkan lewat keyboard.

`AuthContext` memuat user dari API, bukan data palsu. `RequireRole` hanya
kenyamanan antarmuka; API menegakkan pemisahan yang sama lewat middleware
`role`.

## Verifikasi

```powershell
# Backend
cd backend
composer ci:check          # pint --test + phpstan (level 7) + pest

# Frontend
cd frontend
npm run typecheck
npm run build
```

Tes memakai SQLite `:memory:` (`backend/phpunit.xml`) supaya cepat dan tidak
menyentuh MySQL; CI juga menjalankan `migrate:fresh` terhadap MySQL sungguhan
untuk memastikan schema cocok.

Suite saat ini: **294 test / 1131 assertion**, identik di SQLite dan MySQL. Ini
naik dari 205 test / 802 assertion di akhir Phase 7; **89 test** di antaranya
adalah suite SPP baru di `backend/tests/Feature/SppTest.php`, tanpa ada tes lama
yang diubah atau dihapus.

> **Menjalankan suite terhadap MySQL itu wajib, bukan opsional.** SQLite
> melonggarkan `GROUP BY`, MySQL tidak. Saat Phase 4 sebuah query dashboard
> yang tersusun `select id, status, count(*) ... group by status` lulus di
> SQLite dan gagal `500` di MySQL dengan `ONLY_FULL_GROUP_BY` (error 1055),
> karena `reorder()` menghapus `ORDER BY` tetapi tidak menghapus proyeksi
> `select('id')` yang sudah ada, dan `selectRaw()` menambahkan kolom alih-alih
> menggantinya.
>
> ```powershell
> cd backend
> $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='khairul_ummah_test'
> & C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS khairul_ummah_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
> php artisan test
> ```
>
> Jangan dilewati kalau menyentuh agregat, `groupBy`, atau apa pun yang punya
> `select`. Suite SQLite hanya dijamin cepat, bukan benar untuk semua SQL.

Uji manual:

```powershell
cd backend
php artisan serve --port=8000
Invoke-WebRequest http://127.0.0.1:8000/api/health -UseBasicParsing | Select-Object -ExpandProperty Content
```

Untuk mengecek preflight CORS:

```powershell
Invoke-WebRequest http://127.0.0.1:8000/api/health -Method OPTIONS `
  -Headers @{ Origin = 'http://localhost:5173'; 'Access-Control-Request-Method' = 'GET' } `
  -UseBasicParsing
```

Harus memuat header `Access-Control-Allow-Origin: http://localhost:5173`.

## Yang belum ada (Phase 9+)

- **Berkas pendaftaran selain bukti pembayaran.** Hanya jenis `bukti_pembayaran`
  yang bisa diunggah. Verifikasi dokumen lain (ijazah, KK, dst.) dan status
  `verifikasi` santri yang terkait belum dibangun.
- **Pengingat dan penagihan SPP otomatis.** Tagihan SPP bulanan sudah dibuat dan
  dibayar (Phase 8), tetapi belum ada notifikasi jatuh tempo, penandaan telat, atau
  pembuatan tagihan terjadwal tanpa ditekan admin.
- **Pelaporan.** Ringkasan yang ada (overview admin, dashboard wali) adalah anekdot,
  bukan laporan.
- **Verifikasi email dan policy otorisasi per-model** (`Santri::see`,
  `Dokumen::review`, dan seterusnya). Saat ini otorisasi hanya pada level role,
  dengan kepemilikan dicek lewat `whereHas` di level query.
- **Ubah email.** Kolomnya sengaja read-only sejak Phase 4 karena email adalah
  identitas login sekaligus tujuan tautan reset, jadi menggantiinya butuh
  konfirmasi di alamat baru.

## Catatan jaringan

Jaringannya sempat tidak bisa menjangkau `github.com`, `api.github.com`,
`codeload.github.com`, dan registry npm, sehingga Phase 2 sempat berhenti di
sanctum 3.x yang tidak kompatibel dengan Laravel 13. Sekarang sudah normal dan
Sanctum 4.3.3 terpasang. Kalau jaringan memblokir lagi, paket React ada di cache
npm (`npm install --offline`), sementara paket Composer baru kemungkinan perlu
diambil dari cache atau dari mesin lain.
#   K h a i r u l U m m a h  
 