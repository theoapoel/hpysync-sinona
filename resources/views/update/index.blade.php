@extends('layouts.app')
@section('title', 'Update Sistem')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-download text-blue"></i> Update Sistem</div>
        <div class="page-subtitle">Perbarui aplikasi ke versi terbaru dari HPY Solution</div>
    </div>
</div>

{{-- Versi lokal --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-desktop text-blue"></i> Versi Saat Ini</div>
            <span class="badge badge-blue">LOKAL</span>
        </div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                <div style="font-family:'Google Sans',sans-serif;font-size:28px;font-weight:700;color:var(--blue);letter-spacing:1px">
                    {{ $local['sha'] ?: '?' }}
                </div>
                @if($local['branch'])
                    <span class="badge badge-gray">{{ $local['branch'] }}</span>
                @endif
            </div>
            @if($local['date'])
            <div class="text-sm text-muted" style="margin-bottom:4px">
                <i class="fas fa-clock" style="width:14px"></i>
                {{ \Carbon\Carbon::parse($local['date'])->setTimezone('Asia/Jakarta')->isoFormat('D MMM Y, HH:mm') }} WIB
            </div>
            @endif
            @if($local['message'])
            <div class="text-sm" style="color:var(--text2)">
                <i class="fas fa-code-commit" style="width:14px"></i>
                {{ Str::limit($local['message'], 80) }}
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fab fa-github"></i> Versi Terbaru (GitHub)</div>
            <span id="latestBadge" class="badge badge-gray">BELUM DICEK</span>
        </div>
        <div class="card-body" id="latestBody">
            <div class="text-muted text-sm">Masukkan GitHub Token lalu klik <strong>Cek Update</strong>.</div>
        </div>
    </div>
</div>

{{-- Input token + cek --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <div class="card-title"><i class="fab fa-github"></i> GitHub Token</div>
    </div>
    <div class="card-body">
        <div class="alert alert-info" style="margin-bottom:16px">
            <i class="fas fa-info-circle"></i>
            Gunakan <strong>Fine-grained personal access token</strong> dari GitHub.
            Buat di: GitHub → Settings → Developer settings → Personal access tokens → <strong>Fine-grained tokens</strong>
            → pilih repository <strong>hpy-pos</strong> → permission <strong>Contents: Read-only</strong>.
        </div>
        <div style="display:flex;gap:12px;align-items:flex-end">
            <div class="form-group" style="flex:1;margin-bottom:0">
                <label class="form-label">GitHub Token</label>
                <input type="password" id="githubToken" class="form-control"
                    placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
                    autocomplete="off" style="font-family:monospace;letter-spacing:1px">
            </div>
            <button id="btnCheck" onclick="checkUpdate()" class="btn btn-primary"
                style="height:42px;border-radius:8px;white-space:nowrap">
                <i class="fas fa-search"></i> Cek Update
            </button>
        </div>
    </div>
</div>

{{-- Status hasil cek --}}
<div id="statusBar" style="display:none;margin-bottom:20px"></div>

{{-- Form update (muncul setelah cek jika ada update) --}}
<div id="updateForm" class="card" style="display:none;margin-bottom:24px">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-download text-blue"></i> Jalankan Update</div>
    </div>
    <div class="card-body">
        <div class="alert alert-warning" style="margin-bottom:16px">
            <i class="fas fa-exclamation-triangle"></i>
            Proses update akan me-restart aplikasi sebentar. Pastikan tidak ada transaksi yang sedang berjalan.
        </div>
        <button id="btnUpdate" onclick="runUpdate()" class="btn btn-success"
            style="height:42px;border-radius:8px;white-space:nowrap">
            <i class="fas fa-download"></i> Mulai Update Sekarang
        </button>
    </div>
</div>

{{-- Log output --}}
<div id="logCard" class="card" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-terminal"></i> Log Update</div>
        <span id="logStatus" class="badge"></span>
    </div>
    <div class="card-body" style="padding:0">
        <pre id="logOutput" style="font-family:'Courier New',monospace;font-size:12px;background:#1E1E1E;color:#D4D4D4;margin:0;padding:16px;border-radius:0 0 12px 12px;max-height:400px;overflow-y:auto;line-height:1.6;white-space:pre-wrap;word-break:break-all"></pre>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF     = document.querySelector('meta[name="csrf-token"]').content;
const localSha = '{{ $local["sha"] }}';

async function checkUpdate() {
    const token = document.getElementById('githubToken').value.trim();
    if (!token) { alert('Masukkan GitHub Token terlebih dahulu.'); return; }

    const btn = document.getElementById('btnCheck');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Mengecek...';

    document.getElementById('latestBadge').textContent = '...';
    document.getElementById('latestBody').innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;color:var(--text3);font-size:14px">
            <div style="width:16px;height:16px;border:2px solid #E8F0FE;border-top-color:#4285F4;border-radius:50%;animation:spin .8s linear infinite;flex-shrink:0"></div>
            Menghubungi GitHub...
        </div>`;
    document.getElementById('statusBar').style.display  = 'none';
    document.getElementById('updateForm').style.display = 'none';

    try {
        const res  = await fetch('{{ route("update.check") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ github_token: token }),
        });
        const json = await res.json();

        if (!json.success) throw new Error(json.error);

        const sha = json.sha;
        let dateStr = '';
        if (json.date) {
            const d = new Date(json.date);
            dateStr = d.toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) + ' WIB';
        }

        document.getElementById('latestBadge').textContent = 'GITHUB';
        document.getElementById('latestBadge').className   = 'badge badge-green';
        document.getElementById('latestBody').innerHTML    = `
            <div style="font-family:'Google Sans',sans-serif;font-size:28px;font-weight:700;color:var(--green);letter-spacing:1px;margin-bottom:12px">${sha}</div>
            ${dateStr ? `<div class="text-sm text-muted" style="margin-bottom:4px"><i class="fas fa-clock" style="width:14px"></i> ${dateStr}</div>` : ''}
            ${json.message ? `<div class="text-sm" style="color:var(--text2)"><i class="fas fa-code-commit" style="width:14px"></i> ${json.message.split('\\n')[0].substring(0,80)}</div>` : ''}`;

        // Bandingkan SHA
        const bar = document.getElementById('statusBar');
        bar.style.display = 'block';

        if (sha === localSha) {
            bar.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Sistem sudah terbaru.</strong> Tidak ada pembaruan yang tersedia saat ini.
                </div>`;
        } else {
            bar.innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Pembaruan tersedia!</strong>
                    Versi lokal <code>${localSha}</code> berbeda dengan versi terbaru <code>${sha}</code>.
                    Masukkan <strong>Key Update</strong> di bawah untuk melanjutkan.
                </div>`;
            document.getElementById('updateForm').style.display = 'block';
        }

    } catch (e) {
        document.getElementById('latestBadge').textContent = 'ERROR';
        document.getElementById('latestBadge').className   = 'badge badge-red';
        document.getElementById('latestBody').innerHTML    = `
            <div class="alert alert-danger" style="margin:0">
                <i class="fas fa-exclamation-circle"></i> ${e.message}
            </div>`;
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-search"></i> Cek Update';
}

async function runUpdate() {
    const token = document.getElementById('githubToken').value.trim();
    if (!token) { alert('GitHub Token tidak boleh kosong. Isi token lalu Cek Update terlebih dahulu.'); return; }
    if (!confirm('Sistem akan diperbarui. Pastikan tidak ada transaksi berjalan. Lanjutkan?')) return;

    const btn = document.getElementById('btnUpdate');
    btn.disabled  = true;
    btn.innerHTML = '<span class="spinner"></span> Memperbarui...';

    document.getElementById('logCard').style.display  = 'block';
    document.getElementById('logOutput').textContent  = 'Memulai proses update...\n';
    document.getElementById('logStatus').textContent  = 'BERJALAN';
    document.getElementById('logStatus').className    = 'badge badge-yellow';

    try {
        const res  = await fetch('{{ route("update.run") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ github_token: token }),
        });
        const json = await res.json();

        const logEl = document.getElementById('logOutput');
        logEl.textContent = (json.log || []).join('\n');
        logEl.scrollTop   = logEl.scrollHeight;

        if (json.success) {
            document.getElementById('logStatus').textContent = '✓ SELESAI';
            document.getElementById('logStatus').className   = 'badge badge-green';
            document.getElementById('statusBar').innerHTML   = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <strong>Update berhasil!</strong> Sistem sudah diperbarui ke versi terbaru.
                    <a href="{{ route('dashboard') }}" class="btn btn-success btn-sm" style="margin-left:12px">
                        <i class="fas fa-home"></i> Kembali ke Dashboard
                    </a>
                </div>`;
            document.getElementById('updateForm').style.display = 'none';
        } else {
            document.getElementById('logStatus').textContent = '✗ GAGAL';
            document.getElementById('logStatus').className   = 'badge badge-red';
            if (json.error) logEl.textContent += '\n\n❌ ' + json.error;
        }
    } catch (e) {
        document.getElementById('logStatus').textContent = '✗ ERROR';
        document.getElementById('logStatus').className   = 'badge badge-red';
        document.getElementById('logOutput').textContent += '\n\n❌ ' + e.message;
    }

    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-download"></i> Mulai Update Sekarang';
}
</script>
@endpush
