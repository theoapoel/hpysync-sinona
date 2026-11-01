@extends('layouts.app')

@section('title', 'Factory Reset')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title" style="color:var(--red);">
            <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Factory Reset
        </h1>
        <p class="page-subtitle">Hapus seluruh data lokal dan kembalikan sistem ke kondisi awal</p>
    </div>
    <a href="{{ route('backup.restore') }}" class="btn btn-outline">
        <i class="fas fa-upload"></i> Restore Backup
    </a>
</div>

{{-- WARNING BANNER --}}
<div style="background:#FEF2F2;border:2px solid var(--red);border-radius:12px;padding:20px 24px;margin-bottom:24px;">
    <div style="display:flex;gap:14px;align-items:flex-start;">
        <i class="fas fa-skull-crossbones" style="font-size:28px;color:var(--red);margin-top:2px;flex-shrink:0;"></i>
        <div>
            <div style="font-size:16px;font-weight:700;color:var(--red);margin-bottom:8px;">PERINGATAN — Tindakan Ini Tidak Dapat Dibatalkan!</div>
            <div style="font-size:14px;color:#7F1D1D;line-height:1.7;">
                Factory Reset akan <strong>menghapus permanen</strong> seluruh data berikut dari database lokal:
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;margin-top:10px;font-size:13px;color:#7F1D1D;">
                <div><i class="fas fa-times" style="width:14px;"></i> Semua transaksi & item transaksi</div>
                <div><i class="fas fa-times" style="width:14px;"></i> Semua produk & kategori</div>
                <div><i class="fas fa-times" style="width:14px;"></i> Semua data customer</div>
                <div><i class="fas fa-times" style="width:14px;"></i> Semua transfer barang</div>
                <div><i class="fas fa-times" style="width:14px;"></i> Semua cash register / shift</div>
                <div><i class="fas fa-times" style="width:14px;"></i> Semua log sinkronisasi</div>
            </div>
            <div style="margin-top:10px;font-size:13px;color:#166534;background:#F0FDF4;padding:8px 12px;border-radius:6px;display:inline-block;">
                <i class="fas fa-check" style="margin-right:4px;"></i> <strong>Yang tetap dipertahankan:</strong> Akun user, konfigurasi ERP HPY, warehouse & roles
            </div>
        </div>
    </div>
</div>

{{-- STEP 0: Buat Backup --}}
<div class="card" id="step0Card" style="margin-bottom:20px;max-width:900px;">
    <div class="card-header">
        <span class="card-title">
            <span id="step0Badge" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:var(--blue);color:#fff;border-radius:50%;font-size:12px;font-weight:700;margin-right:8px;">0</span>
            Buat Backup Data (Wajib)
        </span>
        <span id="step0Status" class="badge {{ $backupCreatedAt ? 'badge-green' : 'badge-gray' }}">
            {{ $backupCreatedAt ? '✓ Backup sudah dibuat' : 'Belum dibackup' }}
        </span>
    </div>

    {{-- State: belum backup --}}
    <div class="card-body" id="step0Body" style="{{ $backupCreatedAt ? 'display:none' : '' }}">
        <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">
            Sebelum melakukan factory reset, <strong>wajib download backup</strong> terlebih dahulu. File backup berisi semua data yang akan dihapus sehingga bisa di-restore kapan saja.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;font-size:13px;color:var(--text2);">
            <div style="background:var(--bg);border-radius:8px;padding:12px;">
                <div style="font-weight:600;margin-bottom:6px;color:var(--text);">File backup berisi:</div>
                <div><i class="fas fa-circle" style="font-size:6px;margin-right:6px;color:var(--blue);"></i> Transaksi & item</div>
                <div><i class="fas fa-circle" style="font-size:6px;margin-right:6px;color:var(--blue);"></i> Produk & kategori</div>
                <div><i class="fas fa-circle" style="font-size:6px;margin-right:6px;color:var(--blue);"></i> Customer</div>
                <div><i class="fas fa-circle" style="font-size:6px;margin-right:6px;color:var(--blue);"></i> Transfer barang & log sync</div>
            </div>
            <div style="background:var(--bg);border-radius:8px;padding:12px;">
                <div style="font-weight:600;margin-bottom:6px;color:var(--text);">Format file:</div>
                <div><i class="fas fa-file-code" style="margin-right:6px;color:var(--blue);"></i> JSON (.json)</div>
                <div style="margin-top:6px;"><i class="fas fa-upload" style="margin-right:6px;color:var(--green);"></i> Bisa di-restore via halaman <a href="{{ route('backup.restore') }}">Restore Backup</a></div>
            </div>
        </div>
        <div id="backupError" style="display:none;font-size:13px;color:var(--red);background:#FEF2F2;padding:10px 14px;border-radius:6px;margin-bottom:12px;"></div>
        <button class="btn btn-primary w-full" id="downloadBtn" onclick="downloadBackup()">
            <i class="fas fa-download"></i> Download Backup Sekarang
        </button>
    </div>

    {{-- State: sudah backup --}}
    <div class="card-body" id="step0Success" style="{{ $backupCreatedAt ? '' : 'display:none' }}">
        <div style="display:flex;align-items:center;gap:16px;">
            <div style="width:48px;height:48px;background:#E6F4EA;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-check" style="color:var(--green);font-size:20px;"></i>
            </div>
            <div>
                <div style="font-weight:700;font-size:15px;color:var(--green);">Backup Berhasil Didownload</div>
                <div style="font-size:13px;color:var(--text2);margin-top:2px;" id="backupFilename">
                    File backup sudah tersimpan di komputer Anda. Simpan baik-baik!
                </div>
            </div>
            <button class="btn btn-outline btn-sm" style="margin-left:auto;flex-shrink:0;" onclick="reDownloadBackup()">
                <i class="fas fa-redo"></i> Download Ulang
            </button>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

    {{-- STEP 1: Verifikasi ERP HPY --}}
    <div class="card" id="step1Card" style="{{ $backupCreatedAt ? '' : 'opacity:.45;pointer-events:none;' }}">
        <div class="card-header">
            <span class="card-title">
                <span id="step1Badge" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:{{ $backupCreatedAt ? 'var(--blue)' : 'var(--text3)' }};color:#fff;border-radius:50%;font-size:12px;font-weight:700;margin-right:8px;">1</span>
                Verifikasi Akun ERP HPY
            </span>
            <span id="step1Status" class="badge badge-gray">Belum diverifikasi</span>
        </div>
        <div class="card-body" id="step1Body">
            <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">
                Login menggunakan akun ERP HPY dengan role <strong>System Manager</strong> untuk melanjutkan.
            </p>
            <div class="form-group">
                <label class="form-label">Username / Email ERP HPY</label>
                <input type="text" id="erpUsername" class="form-control" placeholder="admin@example.com" autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label">Password ERP HPY</label>
                <div style="position:relative;">
                    <input type="password" id="erpPassword" class="form-control" placeholder="••••••••"
                        autocomplete="current-password" style="padding-right:40px;"
                        onkeydown="if(event.key==='Enter') verifyErp()">
                    <button type="button" onclick="togglePwd()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text3);">
                        <i class="fas fa-eye" id="pwdEyeIcon"></i>
                    </button>
                </div>
            </div>
            <div id="verifyError" style="display:none;font-size:13px;color:var(--red);background:#FEF2F2;padding:10px 14px;border-radius:6px;margin-bottom:12px;"></div>
            <button class="btn btn-primary w-full" id="verifyBtn" onclick="verifyErp()">
                <i class="fas fa-shield-alt"></i> Verifikasi Akun ERP HPY
            </button>
        </div>
        <div class="card-body" id="step1Success" style="display:none;">
            <div style="text-align:center;padding:16px 0;">
                <div style="width:56px;height:56px;background:#E6F4EA;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-check" style="color:var(--green);font-size:24px;"></i>
                </div>
                <div style="font-weight:700;font-size:15px;color:var(--green);">Verifikasi Berhasil</div>
                <div id="verifiedAs" style="font-size:13px;color:var(--text2);margin-top:4px;"></div>
                <div style="font-size:12px;color:var(--text3);margin-top:6px;">
                    <i class="fas fa-clock"></i> Sesi verifikasi berlaku 10 menit
                </div>
            </div>
        </div>
    </div>

    {{-- STEP 2: Konfirmasi Reset --}}
    <div class="card" id="step2Card" style="opacity:.45;pointer-events:none;">
        <div class="card-header">
            <span class="card-title">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:var(--text3);color:#fff;border-radius:50%;font-size:12px;font-weight:700;margin-right:8px;" id="step2NumBadge">2</span>
                Konfirmasi Reset
            </span>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:var(--text2);margin-bottom:16px;">
                Untuk mengkonfirmasi, ketik <code style="background:#FEF2F2;color:var(--red);padding:2px 6px;border-radius:4px;font-weight:700;">RESET</code> pada kolom di bawah.
            </p>
            <div style="background:var(--bg);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;">
                <div style="font-weight:600;color:var(--text2);margin-bottom:8px;">Data yang akan dihapus:</div>
                <div style="display:grid;gap:4px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Transaksi</span>
                        <span class="badge badge-red" id="countTransactions">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Produk</span>
                        <span class="badge badge-red" id="countProducts">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Customer</span>
                        <span class="badge badge-red" id="countCustomers">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Transfer Barang</span>
                        <span class="badge badge-red" id="countTransfers">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Delivery Order</span>
                        <span class="badge badge-red" id="countDeliveryOrders">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Permintaan FG</span>
                        <span class="badge badge-red" id="countStockRequests">—</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text2);">Log Sync</span>
                        <span class="badge badge-red" id="countLogs">—</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" style="color:var(--red);font-weight:700;">Ketik RESET untuk konfirmasi</label>
                <input type="text" id="confirmText" class="form-control"
                    placeholder="RESET"
                    style="letter-spacing:3px;font-weight:700;font-size:16px;text-align:center;border-color:var(--red);"
                    oninput="checkConfirmText()">
            </div>
            <div id="executeError" style="display:none;font-size:13px;color:var(--red);background:#FEF2F2;padding:10px 14px;border-radius:6px;margin-bottom:12px;"></div>
            <button class="btn btn-danger w-full" id="executeBtn" disabled onclick="executeReset()"
                style="opacity:.5;transition:.2s;">
                <i class="fas fa-trash-alt"></i> Hapus Semua Data Sekarang
            </button>
        </div>
    </div>
</div>

{{-- Modal hasil --}}
<div class="modal-overlay" id="resultModal">
    <div class="modal" style="max-width:400px;text-align:center;">
        <div class="modal-body" style="padding:36px 24px;">
            <div style="width:64px;height:64px;background:#E6F4EA;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                <i class="fas fa-check" style="color:var(--green);font-size:28px;"></i>
            </div>
            <div style="font-family:'Google Sans',sans-serif;font-size:20px;font-weight:700;margin-bottom:8px;">Factory Reset Selesai</div>
            <div style="font-size:14px;color:var(--text2);margin-bottom:24px;">Semua data berhasil dihapus. Sistem siap digunakan kembali.</div>
            <a href="{{ route('dashboard') }}" class="btn btn-primary w-full">Ke Dashboard</a>
        </div>
    </div>
</div>

<style>
.w-full { width: 100%; justify-content: center; }
</style>

<script>
let verifiedToken  = null;
let backupDone     = {{ $backupCreatedAt ? 'true' : 'false' }};

// ─── STEP 0: Backup ─────────────────────────────────────────────
async function downloadBackup() {
    const btn    = document.getElementById('downloadBtn');
    const errEl  = document.getElementById('backupError');

    btn.innerHTML = '<span class="spinner"></span> Menyiapkan backup...';
    btn.disabled  = true;
    errEl.style.display = 'none';

    try {
        const res = await fetch('{{ route("backup.download") }}', { credentials: 'same-origin' });

        if (!res.ok) {
            throw new Error('Server error ' + res.status);
        }

        // Get filename from Content-Disposition header
        const disposition = res.headers.get('Content-Disposition') || '';
        const match       = disposition.match(/filename="(.+?)"/);
        const filename    = match ? match[1] : 'larapos-backup.json';

        // Trigger download from blob
        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        // Show success state
        backupDone = true;
        document.getElementById('step0Body').style.display    = 'none';
        document.getElementById('step0Success').style.display = '';
        document.getElementById('step0Status').className      = 'badge badge-green';
        document.getElementById('step0Status').textContent    = '✓ Backup sudah dibuat';
        document.getElementById('step0Badge').style.background = 'var(--green)';
        document.getElementById('backupFilename').textContent  = `File "${filename}" berhasil didownload. Simpan baik-baik!`;

        // Unlock step 1
        const step1 = document.getElementById('step1Card');
        step1.style.opacity       = '1';
        step1.style.pointerEvents = '';
        document.getElementById('step1Badge').style.background = 'var(--blue)';

    } catch (e) {
        errEl.textContent   = 'Gagal membuat backup: ' + e.message;
        errEl.style.display = '';
        btn.innerHTML = '<i class="fas fa-download"></i> Download Backup Sekarang';
        btn.disabled  = false;
    }
}

function reDownloadBackup() {
    document.getElementById('step0Body').style.display    = '';
    document.getElementById('step0Success').style.display = 'none';
    const btn = document.getElementById('downloadBtn');
    btn.innerHTML = '<i class="fas fa-download"></i> Download Backup Sekarang';
    btn.disabled  = false;
}

// ─── STEP 1: Verifikasi ERP HPY ─────────────────────────────────
function togglePwd() {
    const input = document.getElementById('erpPassword');
    const icon  = document.getElementById('pwdEyeIcon');
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}

async function verifyErp() {
    if (!backupDone) {
        toast('Download backup terlebih dahulu.', 'warning');
        return;
    }

    const username = document.getElementById('erpUsername').value.trim();
    const password = document.getElementById('erpPassword').value;
    const btn      = document.getElementById('verifyBtn');
    const errEl    = document.getElementById('verifyError');

    if (!username || !password) {
        showError(errEl, 'Username dan password wajib diisi.');
        return;
    }

    btn.innerHTML = '<span class="spinner"></span> Memverifikasi...';
    btn.disabled  = true;
    errEl.style.display = 'none';

    try {
        const res = await api.post('{{ route("factory-reset.verify") }}', {
            erp_username: username,
            erp_password: password,
        });

        if (!res.success) {
            showError(errEl, res.error || 'Verifikasi gagal.');
            btn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi Akun ERP HPY';
            btn.disabled  = false;
            return;
        }

        verifiedToken = res.token;

        document.getElementById('step1Body').style.display    = 'none';
        document.getElementById('step1Success').style.display = '';
        document.getElementById('verifiedAs').textContent     = `Terverifikasi sebagai: ${res.fullname}`;
        document.getElementById('step1Status').className      = 'badge badge-green';
        document.getElementById('step1Status').textContent    = '✓ Terverifikasi';
        document.getElementById('step1Badge').style.background = 'var(--green)';

        // Unlock step 2
        const step2 = document.getElementById('step2Card');
        step2.style.opacity       = '1';
        step2.style.pointerEvents = '';
        document.getElementById('step2NumBadge').style.background = 'var(--blue)';

        loadCounts();
        startCountdown();

    } catch (e) {
        showError(errEl, 'Koneksi gagal: ' + e.message);
        btn.innerHTML = '<i class="fas fa-shield-alt"></i> Verifikasi Akun ERP HPY';
        btn.disabled  = false;
    }
}

// ─── STEP 2: Execute ─────────────────────────────────────────────
async function loadCounts() {
    try {
        const res = await api.get('{{ route("factory-reset.counts") }}');
        if (res.success) {
            document.getElementById('countTransactions').textContent    = res.transactions;
            document.getElementById('countProducts').textContent        = res.products;
            document.getElementById('countCustomers').textContent       = res.customers;
            document.getElementById('countTransfers').textContent       = res.transfers;
            document.getElementById('countDeliveryOrders').textContent  = res.delivery_orders;
            document.getElementById('countStockRequests').textContent   = res.stock_requests;
            document.getElementById('countLogs').textContent            = res.logs;
        }
    } catch(_) {}
}

function checkConfirmText() {
    const val = document.getElementById('confirmText').value.trim().toUpperCase();
    const btn = document.getElementById('executeBtn');
    const ok  = val === 'RESET' && verifiedToken;
    btn.disabled      = !ok;
    btn.style.opacity = ok ? '1' : '.5';
}

async function executeReset() {
    const btn   = document.getElementById('executeBtn');
    const errEl = document.getElementById('executeError');

    if (!verifiedToken) {
        showError(errEl, 'Token tidak valid. Lakukan verifikasi ulang.');
        return;
    }

    if (!confirm('Anda yakin ingin menghapus SEMUA DATA? Tindakan ini tidak dapat dibatalkan!')) return;

    btn.innerHTML = '<span class="spinner"></span> Menghapus semua data...';
    btn.disabled  = true;
    errEl.style.display = 'none';

    try {
        const res = await api.post('{{ route("factory-reset.execute") }}', {
            token:        verifiedToken,
            confirm_text: 'RESET',
        });

        if (!res.success) {
            showError(errEl, res.error || 'Reset gagal.');
            btn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Semua Data Sekarang';
            btn.disabled  = false;
            return;
        }

        document.getElementById('resultModal').classList.add('show');

    } catch (e) {
        showError(errEl, 'Error: ' + e.message);
        btn.innerHTML = '<i class="fas fa-trash-alt"></i> Hapus Semua Data Sekarang';
        btn.disabled  = false;
    }
}

function showError(el, msg) {
    el.textContent   = msg;
    el.style.display = '';
}

let countdown;
function startCountdown() {
    let remaining = 600;
    const timeEl  = document.querySelector('#step1Success .fa-clock').parentElement;

    countdown = setInterval(() => {
        remaining--;
        const m = String(Math.floor(remaining / 60)).padStart(2,'0');
        const s = String(remaining % 60).padStart(2,'0');
        timeEl.innerHTML = `<i class="fas fa-clock"></i> Sesi berakhir dalam ${m}:${s}`;

        if (remaining <= 0) {
            clearInterval(countdown);
            verifiedToken = null;
            timeEl.innerHTML = '<i class="fas fa-exclamation-triangle" style="color:var(--red)"></i> Sesi kadaluarsa — verifikasi ulang';
            document.getElementById('step2Card').style.opacity       = '.45';
            document.getElementById('step2Card').style.pointerEvents = 'none';
        }
    }, 1000);
}
</script>
@endsection
