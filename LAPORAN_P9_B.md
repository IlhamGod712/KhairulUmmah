# Laporan Hasil P9-B — Unggah dan Verifikasi Dokumen Persyaratan

## Ringkasan
- Fitur dokumen persyaratan wajib (5 dokumen) diimplementasikan untuk alur wali dan admin.
- Aktivasi santri dipusatkan di App\Services\AktivasiSantri dengan urutan lock santri -> fee -> dokumen.
- Bukti pembayaran tetap terpisah (tidak wajib untuk kelengkapan berkas) dan tetap dilindungi oleh route/validasi terpisah.
- Verifikasi fee tidak mengaktifkan langsung tanpa dokumen lengkap; aktivasi dilakukan melalui service.

## Backend
- Helper: app/Support/MimeTerpercaya.php
- Model: app/Models/Dokumen.php disesuaikan kompatibel dengan skema (mime_type/ukuran_bytes)
- Request: UploadDokumenRequest, UpdateDokumenStatusRequest, FilterDokumenRequest
- Service: app/Services/AktivasiSantri.php
- Controller: WaliDokumenController, AdminDokumenController, AdminPembayaranController (direfaktor)
- Resource: DokumenWaliResource, DokumenAdminResource
- Routes: ditambah route wali dan admin untuk dokumen

## Frontend
- npm run build berhasil

## Testing
- SQLite: php artisan test / pest — 307 passed, 307 tests, 1201 assertions
- Test baru: tests/Feature/DokumenPersyaratanTest.php
- Test diperbarui: tests/Feature/PembayaranPendaftaranTest.php

## Catatan
- Tidak ada migration baru
- Bukti pembayaran tidak termasuk kelengkapan wajib
- File disimpan di disk local (private)
