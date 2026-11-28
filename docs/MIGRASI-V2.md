# Catatan Migrasi Data dari v2 (DITUNDA)

Status: ditunda sampai deploy v3 stabil. Dokumen ini menyimpan keputusan dan temuan agar bisa dilanjutkan. Tidak ada data pelanggan di sini.

## Prinsip

- v2 tetap berjalan; v3 dipasang terpisah. Snapshot baca-saja dari v2 dipakai sebagai sumber.
- Pemindahan lewat perintah artisan terpisah, satu transaksi, mode `--dry-run`, dan laporan rekonsiliasi (jumlah baris dan total rupiah v2 vs v3).
- Kredensial router tidak dipindah: diketik ulang lewat UI v3 saat hari pindah. Jangan menyentuh APP_KEY v2.
- Selama v2 masih mengendalikan router, jangan isi kredensial router di v3 dan jangan pasang cron v3.

## Temuan dan keputusan

- Zona waktu: v2 dan v3 menyimpan UTC, tidak perlu konversi. Tanggal bisnis v3 memakai Asia/Jakarta.
- Tagihan v2 dihitung di belakang, sama seperti v3: periode berakhir sehari sebelum tanggal terbit. `period_start` = tanggal yang sama di bulan sebelumnya (dipotong di akhir bulan).
- `invoice_day` pelanggan diisi dari tanggal terbit asli di v2, bukan dari `due_day`.
- `invoices.active_period_key` harus `id_pelanggan:period_start` (periode reguler, juga untuk tagihan prorata). Jika salah, scheduler bisa menerbitkan tagihan ganda.
- Tagihan prorata: detail masuk `snapshot`, kolom periode tetap periode reguler.
- Tagihan bernilai Rp0 tidak dipindahkan (v3 tidak menerbitkan invoice Rp0).
- Nomor invoice v2 (INV-YYYYMM-NNNNNN) dipertahankan; tidak bentrok dengan format v3 (INV- plus 8 digit).
- `due_on` mengikuti masa tenggang global v3 (`due_grace_days`); jatuh tempo per pelanggan v2 tidak dipertahankan persis.
- `started_at` wajib di v3, sedangkan sebagian besar pelanggan v2 tidak punya tanggal aktivasi: isi dari tanggal dibuat atau awal periode tagihan pertama.
- Pajak dan masa tenggang di pengaturan billing v3 harus disamakan dengan v2 sebelum migrasi.

## Pembayaran dan saldo

- Metode bank_transfer menjadi `transfer`, cash menjadi `cash`.
- Pembayaran dengan saldo kredit tidak menjadi receipt: dibuat sepasang entri saldo (`in` bernote saldo awal migrasi, dan `use` ke invoice) tanpa `receipt_id`, supaya tidak dihitung sebagai pemasukan tunai.
- Saldo kredit yang tersisa dipindah sebagai satu entri `in` per pelanggan.
- Pembayaran ganda atau sebagian dipindah sebagai beberapa receipt dengan alokasi sebagian (status partial didukung).
- `request_key` receipt: UUID deterministik (v5 dari id pembayaran v2) agar dry-run bisa diulang; `recorded_by_name` = 'Migrasi v2'.
- Setiap invoice hasil pindahan diverifikasi dengan `BillingLedger::refreshInvoice` (terbayar tidak boleh melebihi total).
- Selisih kecil akibat salah input titip saldo dipindah apa adanya; koreksi manual sesudah migrasi lewat menu Titip Saldo supaya ada jejak.

## Pemetaan tabel (dugaan, verifikasi dengan .schema v2)

| v2 | v3 |
|---|---|
| areas, area_user, area_router_access | wilayah, wilayah_user, network_router_wilayah |
| routers | network_routers |
| internet_packages | internet_packages, internet_package_router |
| customers | customers |
| invoices | invoices |
| payments | billing_receipts, billing_allocations |
| saldo kredit | billing_balance_entries |
| expenses | expenses |
| users | users |

## Rekonsiliasi

- Jumlah pelanggan, tagihan (dikurangi yang Rp0), pembayaran, pengeluaran, paket, router, wilayah.
- Total tagihan lunas, total terbayar (tunai, transfer, saldo), sisa tagihan belum lunas, saldo kredit.
- Tidak ada invoice dengan `paid_amount` lebih besar dari `total`, dan tidak ada `active_period_key` ganda.

## Yang harus dibaca sebelum mulai

- Skema v2 (`.schema`) dan skema akhir v3 (kolom customers: code, harga dan pajak, invoice_day, first_billing_mode, soft delete).
- Pengguna: peran v2 vs v3, username wajib, kecocokan hash kata sandi.
- Paket: kolom bandwidth dan rate limit v3, profil MikroTik per router.

## Hari pindah

1. Hentikan perubahan di v2 (jendela pemeliharaan) dan ambil snapshot baru.
2. Jalankan perintah migrasi (`--dry-run`, lalu sungguhan) dan cocokkan rekonsiliasi.
3. Ketik kredensial router di v3, uji koneksi, lalu hentikan cron v2.
4. Pasang cron v3.
5. Simpan v2 sebagai arsip baca-saja.
