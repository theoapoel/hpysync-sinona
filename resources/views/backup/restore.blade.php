@extends('layouts.app')

@section('title', 'Restore Backup')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-upload" style="margin-right:8px;color:var(--blue);"></i>Restore Backup
        </h1>
        <p class="page-subtitle">Pulihkan data dari file backup LaraPos</p>
    </div>
    <a href="{{ route('factory-reset.index') }}" class="btn btn-ghost">← Factory Reset</a>
</div>

{{-- WARNING --}}
<div style="background:#FEF3E2;border:1px solid #F9AB00;border-radius:12px;padding:16px 20px;margin-bottom:24px;display:flex;gap:12px;align-items:flex-start;">
    <i class="fas fa-exclamation-triangle" style="color:#E37400;font-size:20px;margin-top:2px;flex-shrink:0;"></i>
    <div style="font-size:14px;color:#7C4A00;">
        <strong>Perhatian:</strong> Proses restore akan <strong>menghapus semua data saat ini</strong> (transaksi, produk, customer, transfer barang) dan menggantinya dengan data dari file backup. Akun user, konfigurasi ERP HPY, warehouse, dan roles <strong>tidak akan berubah</strong>.
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:900px;align-items:start;">

    {{-- Upload Card --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-file-upload" style="margin-right:8px;color:var(--blue);"></i>Upload File Backup</span>
        </div>
        <div class="card-body">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('backup.restore.post') }}" enctype="multipart/form-data" id="restoreForm" onsubmit="return confirmRestore()">
                @csrf

                {{-- Drop zone --}}
                <div id="dropZone" style="border:2px dashed var(--border);border-radius:10px;padding:32px;text-align:center;cursor:pointer;transition:all .2s;margin-bottom:16px;"
                    onclick="document.getElementById('backupFile').click()"
                    ondragover="onDragOver(event)"
                    ondragleave="onDragLeave(event)"
                    ondrop="onDrop(event)">
                    <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:var(--text3);margin-bottom:8px;display:block;"></i>
                    <div id="dropText" style="font-size:14px;color:var(--text2);">
                        Klik atau drag & drop file backup di sini<br>
                        <span style="font-size:12px;color:var(--text3);">Format: .json (LaraPos backup)</span>
                    </div>
                </div>
                <input type="file" id="backupFile" name="backup_file" accept=".json,application/json"
                    style="display:none;" onchange="onFileSelect(this)">

                {{-- Preview area (hidden until file selected) --}}
                <div id="filePreview" style="display:none;background:var(--bg);border-radius:8px;padding:14px;margin-bottom:16px;font-size:13px;">
                    <div style="font-weight:600;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-code" style="color:var(--blue);"></i>
                        <span id="previewFilename">—</span>
                    </div>
                    <div id="previewMeta" style="color:var(--text2);margin-bottom:10px;"></div>
                    <div style="border-top:1px solid var(--border);padding-top:10px;">
                        <div style="font-weight:600;margin-bottom:6px;color:var(--text2);">Data yang akan di-restore:</div>
                        <div id="previewCounts" style="display:grid;gap:4px;"></div>
                    </div>
                </div>

                <div id="parseError" style="display:none;" class="alert alert-danger"></div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700;">Ketik <code style="background:#FEF3E2;color:#B45309;padding:1px 5px;border-radius:3px;">RESTORE</code> untuk konfirmasi</label>
                    <input type="text" name="confirm_text" id="confirmInput" class="form-control"
                        placeholder="RESTORE"
                        style="letter-spacing:3px;font-weight:700;font-size:16px;text-align:center;"
                        oninput="checkRestoreReady()"
                        autocomplete="off">
                </div>

                <button type="submit" class="btn btn-primary w-full" id="restoreBtn" disabled style="opacity:.5;">
                    <i class="fas fa-undo"></i> Restore Data Sekarang
                </button>
            </form>
        </div>
    </div>

    {{-- Info Card --}}
    <div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-info-circle" style="margin-right:8px;color:var(--blue);"></i>Yang Akan Di-restore</span>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div style="font-size:13px;color:var(--text2);line-height:1.9;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-check" style="color:var(--green);width:14px;"></i> Semua transaksi & item transaksi</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-check" style="color:var(--green);width:14px;"></i> Semua produk & kategori</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-check" style="color:var(--green);width:14px;"></i> Semua data customer</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-check" style="color:var(--green);width:14px;"></i> Semua transfer barang</div>
                    <div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-check" style="color:var(--green);width:14px;"></i> Log sinkronisasi ERP HPY</div>
                </div>
                <hr class="divider">
                <div style="font-size:13px;color:var(--text3);line-height:1.9;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-minus" style="width:14px;"></i> Akun user (tidak berubah)</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;"><i class="fas fa-minus" style="width:14px;"></i> Konfigurasi ERP HPY (tidak berubah)</div>
                    <div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-minus" style="width:14px;"></i> Warehouse & roles (tidak berubah)</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-lightbulb" style="margin-right:8px;color:#E37400;"></i>Tips</span>
            </div>
            <div class="card-body" style="padding:16px 20px;">
                <div style="font-size:13px;color:var(--text2);line-height:1.8;">
                    <div style="margin-bottom:8px;"><strong>Kapan pakai Restore?</strong><br>Setelah factory reset, jika ingin mengembalikan data lama dari file backup.</div>
                    <div style="margin-bottom:8px;"><strong>Format file:</strong><br>File backup LaraPos berekstensi <code>.json</code> dengan nama seperti <code>larapos-backup-2026-05-22_120000.json</code></div>
                    <div><strong>Backup dulu sebelum restore!</strong><br>Jika ada data saat ini yang ingin disimpan, download backup terlebih dahulu sebelum restore.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.w-full { width: 100%; justify-content: center; }
#dropZone.dragover { border-color: var(--blue); background: var(--blue-light); }
</style>

<script>
let fileSelected = false;

function onDragOver(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.add('dragover');
}
function onDragLeave(e) {
    document.getElementById('dropZone').classList.remove('dragover');
}
function onDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').classList.remove('dragover');
    const file = e.dataTransfer.files[0];
    if (file) processFile(file);
}
function onFileSelect(input) {
    if (input.files[0]) processFile(input.files[0]);
}

function processFile(file) {
    // Update drop zone
    document.getElementById('dropText').innerHTML =
        `<strong>${file.name}</strong><br><span style="font-size:12px;color:var(--text3);">${(file.size/1024).toFixed(1)} KB</span>`;

    const errEl = document.getElementById('parseError');
    errEl.style.display = 'none';
    document.getElementById('filePreview').style.display = 'none';
    fileSelected = false;
    checkRestoreReady();

    // Read & parse client-side for preview
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const backup = JSON.parse(e.target.result);

            if (!backup.meta || !backup.data || backup.meta.app !== 'LaraPos') {
                throw new Error('Bukan file backup LaraPos yang valid.');
            }

            // Show preview
            document.getElementById('previewFilename').textContent = file.name;
            document.getElementById('previewMeta').innerHTML =
                `<i class="fas fa-calendar" style="margin-right:5px;"></i>Dibuat: <strong>${backup.meta.created_at ?? '—'}</strong>` +
                (backup.meta.created_by ? ` &nbsp;|&nbsp; <i class="fas fa-user" style="margin-right:5px;"></i>Oleh: <strong>${backup.meta.created_by}</strong>` : '');

            const labels = {
                categories:          'Kategori',
                products:            'Produk',
                customers:           'Customer',
                transactions:        'Transaksi',
                transaction_items:   'Item Transaksi',
                stock_transfers:     'Transfer Barang',
                stock_transfer_items:'Item Transfer',
                erp_sync_logs:       'Log Sync',
            };

            const counts = backup.meta.counts ?? {};
            let countsHtml = '';
            for (const [key, label] of Object.entries(labels)) {
                const n = counts[key] ?? (backup.data[key]?.length ?? 0);
                countsHtml += `<div style="display:flex;justify-content:space-between;">
                    <span>${label}</span>
                    <span class="badge badge-blue">${n}</span>
                </div>`;
            }
            document.getElementById('previewCounts').innerHTML = countsHtml;
            document.getElementById('filePreview').style.display = '';

            fileSelected = true;
            checkRestoreReady();

        } catch (err) {
            errEl.textContent   = 'File tidak valid: ' + err.message;
            errEl.style.display = '';
        }
    };
    reader.readAsText(file);
}

function checkRestoreReady() {
    const confirmVal = document.getElementById('confirmInput').value.trim().toUpperCase();
    const btn        = document.getElementById('restoreBtn');
    const ready      = fileSelected && confirmVal === 'RESTORE';
    btn.disabled      = !ready;
    btn.style.opacity = ready ? '1' : '.5';
}

function confirmRestore() {
    return confirm('Yakin ingin mengganti semua data saat ini dengan data dari file backup?');
}
</script>
@endsection
