# Playbook Mitra POS HPY

Panduan penggunaan aplikasi Point-of-Sale (POS) Mitra HPY untuk operasional harian.
Dokumen ini menjelaskan **cara memakai** program, bukan cara mengembangkannya
(untuk teknis lihat [CLAUDE.md](../CLAUDE.md)).

> Catatan istilah: semua integrasi server pusat disebut **ERP HPY**.

---

## 1. Login & Peran (Role)

Aplikasi punya dua cara masuk:

| Mode | Kapan dipakai | Yang dibutuhkan |
|------|---------------|-----------------|
| **Online** | Kondisi normal, ada internet | Email + Password (diverifikasi ke ERP HPY) |
| **Offline (PIN)** | Server ERP tidak bisa dihubungi | Email + PIN 6 digit lokal |

Setelah login, halaman awal tergantung peran:

| Peran | Landing setelah login | Keterangan |
|-------|----------------------|------------|
| **Admin** | Dashboard | Akses penuh ke semua menu |
| **Manager** | Dashboard | Akses operasional luas |
| **Cashier (Kasir)** | Kasir (POS) | Langsung ke layar transaksi |
| **Dapur** | Kitchen Monitor | Layar pantau pesanan dapur |

Menu yang tampil untuk tiap peran diatur di **Hak Akses** (lihat bagian 15).

---

## 2. Kasir (POS) — Transaksi Penjualan

Menu **Kasir** adalah layar utama penjualan. Tersedia 3 tampilan (diatur admin di
Pengaturan Toko → `pos_layout`): **Klasik**, **Quick**, dan **Express**.

### Alur transaksi
1. Pilih **produk** dari daftar/kategori atau **scan/cari** barang.
2. Item masuk ke keranjang; atur **qty** bila perlu.
3. (Opsional) Pilih **customer** — kalau kosong dipakai *Walk-in Customer*.
4. (Opsional) Masukkan **kupon** → sistem memvalidasi diskonnya.
5. (Opsional) Isi **nomor meja** untuk pesanan dine-in.
6. Klik **Bayar** → pilih **metode pembayaran**, masukkan **jumlah dibayar**;
   sistem menghitung **kembalian** otomatis.
7. Transaksi tersimpan dengan nomor invoice `INV-YYYYMMDD-XXXX`.
8. **Struk** bisa dicetak; pesanan dapur bisa dicetak lewat **Print Kitchen**.

### Yang terjadi di belakang layar
- Stok produk berkurang otomatis (hanya untuk produk dengan `track_stock` aktif).
- Transaksi ditandai **belum tersinkron** (`pending`) untuk nanti dikirim ke ERP HPY.

### Hold / Recall (tahan pesanan)
- **Hold**: simpan sementara keranjang (mis. pelanggan belum siap bayar).
- **Recall**: panggil kembali pesanan yang di-hold, lengkap dengan customer-nya.

---

## 3. Transaksi

Menu **Transaksi** menampilkan riwayat penjualan.
- Lihat detail tiap invoice (item, pembayaran, kembalian, status sync).
- **Batalkan transaksi** — hanya **Admin/Manager**. Pembatalan mengembalikan stok
  dan menghapus transaksi secara *soft-delete*.

---

## 4. Produk

Kelola master barang: nama, SKU, harga, kategori, dan opsi lacak stok.
Data produk umumnya **ditarik dari ERP HPY** (lihat menu Sync → Pull Produk),
memakai harga *Item Price* dengan `valid_from` terbaru.

---

## 5. Customer

Kelola data pelanggan (kode, nama, telepon, poin loyalti).
Customer dapat dipilih saat transaksi dan bisa didorong (*push*) ke ERP HPY.

---

## 6. Stok Barang

Pantau stok per gudang.
- **Sync Bin** / **Sync Warehouse**: tarik saldo stok terbaru dari ERP HPY.
- Mendukung banyak gudang; satu gudang ditandai *default* untuk POS.

---

## 7. Stock Opname

Hitung ulang stok fisik dan cocokkan dengan sistem.
Alur dokumen: **draft → submitted**, lalu selisih disesuaikan.
- Buat opname, isi qty hasil hitung fisik, **Submit**.
- Bisa **Cancel** bila masih draft.

---

## 8. Repack (Konversi Item)

Mengubah stok satu/beberapa item menjadi item lain. Formnya memakai **dua tabel**:

| Tabel | Isi | Efek stok |
|-------|-----|-----------|
| **Item Dibuang (Issue)** | Semua item yang dikonsumsi/keluar | **Keluar** dari gudang yang dipilih |
| **Jadi Item (Receipt)** | Semua item hasil | **Masuk** ke gudang default |

Alur dokumen: **draft → submitted → sync ke ERP HPY (Repack)**.

### Cara pakai
1. Di tabel **Issue**, tambahkan tiap bahan yang dibuang: item, qty, dan
   **gudang asal** (wajib dipilih — tidak bisa disimpan bila gudang kosong).
2. Di tabel **Receipt**, tambahkan tiap item hasil: item + qty.
3. **Simpan sebagai Draft**, lalu **Submit ke ERP** → semua item Issue keluar
   stok, semua item Receipt masuk stok (Stock Entry tipe *Repack*).

### Contoh pola yang didukung
- **1 → banyak** dari 1 bahan: Issue `1 Bolu` → Receipt `8 Potong`.
- **Pecah beberapa jenis**: Issue `1 Bolu` → Receipt `2 Slice A` + `2 Slice B`.
- **Dengan bahan tambahan/topping**: cukup tambahkan topping sebagai baris di
  tabel Issue (mis. Issue `1 Bolu` + `2 Topping` → Receipt `2 Slice`). Topping
  bisa keluar dari gudang berbeda.

> Catatan: di balik layar fitur ini masih bernama `slice` (route/model),
> data baris tersimpan di tabel `slice_lines` (`line_type` = issue/receipt),
> tetapi seluruh label untuk pengguna adalah **Repack**.

---

## 9. Transfer Barang (Stock Transfer)

Perpindahan stok antar gudang.
- **Outgoing** (`STO-*`): kirim barang keluar.
- **Incoming** (`STI-*`): terima barang; item dimuat dari entri sumber ERP.
- Status: `draft → submitted`, dengan `local_status` (draft/sent/received).
- **Surat Jalan** bisa dicetak (halaman terpisah, buka di tab baru).
- **Laporan Transfer**: rekap seluruh transfer beserta rinciannya.

---

## 10. Delivery Order (DO) & Delivery Notes

### Delivery Order
Pesanan pengiriman ke pelanggan. Nomor `DO-YYYYMMDD-XXXX`.
- Alur: **draft → confirmed** (batal = soft-delete).
- Status dapur: `pending → preparing → ready`.
- Bisa **catat pembayaran**, **atur jadwal produksi**, cetak **slip Gudang/QC**,
  serta **Proforma Invoice / Invoice**.
- **Sync Sales Order** ke ERP HPY.

**Penerbitan Sales Invoice (otomatis).** Begitu order **punya pembayaran**,
sistem menerbitkan **Sales Invoice** di ERP HPY (bukan berhenti di Sales Order):
lewat **confirm** bila pembayaran sudah ada, atau lewat **penambahan pembayaran**
bila order dikonfirmasi tanpa bayar. Order **tanpa pembayaran sengaja tidak
ditagihkan** agar piutang tidak menumpuk untuk order yang belum tentu jalan.
- Invoice dibuat dengan `update_stock = 0`; **stok tetap dipotong oleh Delivery
  Note** saat pengiriman — supaya satu pengiriman tidak memotong stok dua kali.
- Item invoice membawa referensi Sales Order sehingga order yang sama **tidak
  bisa ditagih dua kali**, dan Payment Entry menutup piutang (bukan uang muka).
- **Pembatalan order** ikut membatalkan Payment Entry lalu Sales Invoice (urutan
  wajib). Bila ERP gagal dihubungi, **order lokal TIDAK jadi dibatalkan** agar
  kedua sisi tidak berbeda diam-diam. Pembatalan yang putus di tengah bisa
  dilanjutkan (langkah yang sudah batal dilewati).

### Delivery Notes
Bekerja pada **pengiriman (shipment)**, bukan order.
- Tandai **Delivered**.
- **Sync Delivery Note** ke ERP HPY — sistem mencoba **submit** DN, bukan hanya
  membuat draft.
- Bila submit gagal (**paling sering karena stok di ERP kurang**), DN **tetap
  tersimpan sebagai draft** di HPY, nomornya dicatat, dan status
  (`Submitted`/`Draft`) diinfokan ke user. **Sync ulang bersifat idempoten**:
  menyelesaikan submit DN yang sudah ada, bukan membuat DN baru.
- Shipment lama yang tersimpan tanpa SKU tetap dikirim dengan `item_code` yang
  benar — produk dicari lewat namanya, jadi tidak lagi ditolak "Could not find
  Item Code".

---

## 11. Permintaan FG (Stock Request)

Permintaan barang jadi (Finished Goods) ke dapur/pusat. Nomor `FG-YYYYMMDD-XXXX`.
- Alur: **draft → submitted** (bisa dibatalkan).
- Status dapur: `requested → preparing → done`.
- **Sync** ke ERP HPY sebagai *Material Request*.
- **Tanggal butuh yang sudah lewat dimajukan otomatis.** Material Request dikirim
  dengan tanggal transaksi = hari ini; bila `needed_date`-nya sudah lewat (mis.
  dibuat pekan lalu, baru disync hari ini) *Reqd by Date* dimajukan ke hari ini
  supaya ERP tidak menolak ("Reqd by Date cannot be before Transaction Date").
  Jadwal yang masih di depan dipakai apa adanya; tanggal butuh asli dicatat di
  remarks agar informasinya tidak hilang.

---

## 12. Pulling Order

Layar gabungan untuk mengelola **pembayaran** dan **jadwal produksi** lintas
**Delivery Order** dan **Permintaan FG** dalam satu tempat.
- Atur jadwal DO / SR, konfirmasi ke dapur, catat pembayaran DO.
- Pembayaran DO yang dicatat di sini **menerbitkan Sales Invoice dulu (idempoten)
  lalu Payment Entry** — sama seperti mencatat bayar dari layar Delivery Order,
  sehingga pembayaran menutup piutang, bukan tercatat sebagai uang muka.

---

## 13. Rekap Order

Laporan agregat item dari:
- **Delivery Order** yang sudah *confirmed* (difilter `delivery_date`), dan
- **Stock Request** yang sudah *submitted* (difilter `needed_date`),

digabung berdasarkan kode item — untuk melihat total kebutuhan produksi per tanggal.

---

## 14. Kitchen Monitor (Dapur)

Layar pantau untuk staf dapur, menampilkan **Delivery Order** dan **Permintaan FG**
sekaligus, dengan **refresh otomatis**.
- Ubah status masak: `pending/requested → preparing → ready/done`.
- Ada tampilan **kalender** produksi.

---

## 15. Integrasi ERP HPY (Sync)

Menu **Sync HPY** untuk menyinkronkan data dengan server pusat.
- **Sync All / Sync per transaksi / Retry Failed** — kirim invoice ke ERP.
- **Pull**: Produk, Metode Pembayaran, User, Harga Delivery, Kupon.
- Badge angka di menu = jumlah transaksi yang belum tersinkron.
- **Laporan Online**: data historis langsung dari ERP HPY.
- **Laporan Pembayaran (MOP)**: matriks tanggal × metode pembayaran.
- **Laporan DO**: penjualan **Delivery Order** dari data lokal (ringkasan
  total / dibayar / outstanding + tabel per order). Penjualan DO tidak muncul di
  Laporan Online/Pembayaran karena keduanya membaca POS Invoice, sedangkan DO
  terbit sebagai Sales Invoice biasa. Listing tidak memanggil ERP agar cepat;
  tombol **Cek HPY** per order menarik langsung dari ERP HPY (Sales Invoice:
  status/outstanding/per_billed, Delivery Note per shipment, Payment Entry per
  pembayaran). Diatur via hak akses **`do_report`**.

Status sinkron tiap data: `pending / synced / failed`, tercatat di log audit.
Sync berjalan **saat itu juga** (tidak ada antrean background).

### Pemotongan stok POS Invoice

Invoice POS dikirim dengan **`update_stock = 1` eksplisit**. Ini wajib: centang
"Update Stock" di POS Profile **tidak** diwarisi oleh dokumen yang dibuat lewat
REST — ERP mengisinya dari JavaScript form POS, yang tidak pernah jalan untuk
sync ini. Tanpa dikirim eksplisit, invoice terbit dengan nilai 0 walau POS
Profile-nya tercentang, dan **stok di ERP tidak pernah berkurang** padahal stok
lokal sudah dipotong saat checkout — selisihnya menumpuk tiap transaksi.

Flag ini **tidak** menyebabkan stok terpotong dua kali saat POS Invoice
dikonsolidasi. POS Invoice sendiri tidak pernah membuat Stock Ledger Entry
(diverifikasi ke ERP HPY: invoice ber-`update_stock = 1` yang sudah submit tidak
punya SLE sama sekali); nilai itu hanya ikut terbawa ke **Sales Invoice
konsolidasi**, dan invoice konsolidasi itulah satu-satunya yang memotong stok.

Beda dengan alur **Delivery Order**, yang memakai `update_stock = 0` karena di
sana stok dipotong Delivery Note saat barang benar-benar dikirim.

---

## 16. Menu Sistem (Admin)

Menu berikut sebelumnya khusus Admin; kini masing-masing punya toggle di **Hak Akses**
sehingga bisa didelegasikan ke peran lain. **Admin selalu punya akses penuh.**

| Menu | Fungsi |
|------|--------|
| **Kupon** | Daftar kupon (ditarik dari ERP HPY) |
| **Manajemen User** | Tambah/ubah user, aktif/nonaktif, atur peran & PIN |
| **Manajemen Role** | Kelola peran beserta warnanya |
| **Hak Akses** | Matriks izin menu per peran (lihat di bawah) |
| **Warehouse** | Pemetaan gudang ke ERP; set *default* / *transit* |
| **Pengaturan Toko** | Nama toko, logo, layout POS, pajak/service charge, kredensial ERP |
| **Restore Backup** | Unduh & pulihkan data (manual, format JSON) |
| **Update Sistem** | Cek & jalankan pembaruan aplikasi |
| **Factory Reset** | Reset data pabrik (hati-hati, destruktif) |

### Backup Otomatis ke Google Drive

Selain backup manual (JSON) di menu **Restore Backup**, sistem menjalankan
**backup database otomatis ke Google Drive** tiap hari (default **jam 02:00**).

- Yang di-backup: **seluruh database** (dump `.sql` di dalam zip) + folder upload.
- Retensi: backup harian disimpan **7 hari** penuh lalu menipis (mingguan/bulanan).
- **Notifikasi email hanya dikirim bila backup gagal** (tidak spam saat sukses).
- Backup manual kapan saja: `php artisan backup:run --only-db`.

**Prasyarat (dikonfigurasi sekali oleh teknisi):**
1. Kredensial Google Drive (service account) diisi di `.env`
   (`GOOGLE_DRIVE_SERVICE_ACCOUNT`, `GOOGLE_DRIVE_FOLDER_ID`, `BACKUP_DISK=google`).
   File JSON service account disimpan **di luar folder project** agar tidak masuk git.
2. **Windows Task Scheduler** menjalankan `php artisan schedule:run` tiap 1 menit
   (Program `C:\xampp\php\php.exe`, Argument `artisan schedule:run`, Start in
   `C:\xampp\htdocs\resto-pos`), dicentang *"Run whether user is logged on or not"*.

> Teknis lengkap ada di `config/backup.php`, `config/filesystems.php` (disk
> `google`), dan jadwal di `routes/console.php`.

### Cara mengatur Hak Akses
1. Buka **Hak Akses**.
2. Nyalakan/matikan **toggle** tiap menu untuk tiap peran.
3. Klik **Simpan**.

> Modul admin sensitif (Kupon, User, Role, Hak Akses, Warehouse, Pengaturan,
> Backup, Update, Factory Reset) **default mati** untuk non-admin — harus
> dinyalakan manual bila ingin didelegasikan.

---

## 17. Alur Harian yang Umum

**Kasir (buka toko → tutup toko):**
1. Login (online; jika server down pakai PIN).
2. Layani penjualan lewat menu **Kasir**.
3. Cetak struk / print kitchen sesuai kebutuhan.
4. Akhir shift: buka **Sync HPY** → **Sync All** agar transaksi terkirim.
5. Cek **Laporan Pembayaran** untuk rekap per metode.

**Dapur:**
1. Login → **Kitchen Monitor**.
2. Update status masak tiap pesanan sampai `ready/done`.

**Manager/Admin:**
1. Pantau **Dashboard** & **Transaksi**.
2. Kelola stok, transfer, dan produksi (DO / Permintaan FG / Pulling Order).
3. Pastikan sinkronisasi ERP lancar (tidak ada `failed`).

---

## Lampiran — Kredensial Demo (setelah seeding)

| Peran | Email | Password | PIN |
|-------|-------|----------|-----|
| Admin | admin@larapos.com | password | 123456 |
| Manager | manager@larapos.com | password | 111222 |
| Cashier | kasir@larapos.com | password | 654321 |
