@extends('layouts.app')
@section('title', 'Laporan Mode of Payment')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-wallet text-blue"></i> Laporan Mode of Payment</div>
        <div class="page-subtitle">Rekap pembayaran per hari &times; metode, langsung dari ERP HPY</div>
    </div>
    @if(!empty($scopedToUser) && $scopedToUser)
        <div class="badge" style="align-self:center;background:var(--primary-light,#e0e7ff);color:var(--primary,#4f46e5);padding:6px 12px;border-radius:20px;font-weight:700;font-size:12px">
            <i class="fas fa-user"></i> Hanya transaksi Anda ({{ $scopedUserName }})
        </div>
    @endif
</div>

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr auto auto;gap:12px;align-items:flex-end">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" id="dateFrom" class="form-control"
                    value="{{ $dateLocked ? today()->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d') }}"
                    @disabled($dateLocked)>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" id="dateTo" class="form-control"
                    value="{{ now()->format('Y-m-d') }}" @disabled($dateLocked)>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">POS Profile</label>
                <input type="text" id="posProfile" class="form-control"
                    placeholder="Kosong = semua profile"
                    value="{{ $posProfile }}" style="min-width:200px">
            </div>
            <button id="btnFetch" onclick="fetchReport()" class="btn btn-primary" style="height:42px;border-radius:8px">
                <i class="fas fa-search"></i> Tampilkan
            </button>
        </div>

        @if($dateLocked)
        <p style="margin:12px 0 0;font-size:12px;color:var(--text3)">
            <i class="fas fa-lock"></i> Laporan dikunci ke tanggal hari ini ({{ today()->format('d/m/Y') }}).
        </p>
        @else
        <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap">
            <button onclick="setRange('today')" class="btn btn-ghost btn-sm">Hari Ini</button>
            <button onclick="setRange('yesterday')" class="btn btn-ghost btn-sm">Kemarin</button>
            <button onclick="setRange('week')" class="btn btn-ghost btn-sm">7 Hari Terakhir</button>
            <button onclick="setRange('month')" class="btn btn-ghost btn-sm">Bulan Ini</button>
            <button onclick="setRange('last_month')" class="btn btn-ghost btn-sm">Bulan Lalu</button>
        </div>
        @endif
    </div>
</div>

{{-- Loading state --}}
<div id="loadingState" style="display:none;text-align:center;padding:60px 0">
    <div style="width:48px;height:48px;border:4px solid #E8F0FE;border-top-color:#4285F4;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px"></div>
    <div style="color:#5F6368;font-size:15px">Mengambil data dari ERP HPY...</div>
    <div style="color:#80868B;font-size:13px;margin-top:4px">Mungkin membutuhkan beberapa saat</div>
</div>

{{-- Error state --}}
<div id="errorState" style="display:none">
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span id="errorMsg">Terjadi kesalahan</span>
    </div>
</div>

{{-- Hasil --}}
<div id="resultArea" style="display:none">
    {{-- Truncated warning --}}
    <div id="truncatedWarn" class="alert alert-warning" style="display:none">
        <i class="fas fa-exclamation-triangle"></i>
        Data terlalu banyak — hanya 1.000 transaksi pertama yang ditampilkan.
        Perkecil rentang tanggal untuk melihat data lengkap.
    </div>

    {{-- Empty state --}}
    <div id="emptyState" class="card" style="display:none">
        <div class="card-body" style="text-align:center;padding:48px 0;color:#80868B">
            <i class="fas fa-inbox" style="font-size:32px;margin-bottom:12px;display:block"></i>
            Tidak ada data pembayaran pada rentang tanggal ini.
        </div>
    </div>

    <div id="matrixWrap" style="display:none">
        {{-- Metode chips ringkasan --}}
        <div id="modeSummary" class="stat-grid" style="margin-bottom:20px"></div>

        {{-- Matriks --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-table text-blue"></i> Rekap Harian per Metode</div>
                <div style="display:flex;gap:8px;align-items:center">
                    <span class="text-sm text-muted" id="matrixRange"></span>
                    <button class="btn btn-ghost btn-sm" onclick="exportCsv()"><i class="fas fa-file-csv"></i> Export CSV</button>
                </div>
            </div>
            <div class="table-wrap">
                <table id="matrixTable">
                    <thead id="matrixHead"></thead>
                    <tbody id="matrixBody"></tbody>
                    <tfoot id="matrixFoot"></tfoot>
                </table>
            </div>
        </div>

        {{-- Rekap per Kasir --}}
        <div class="card" style="margin-top:20px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-user-tie text-blue"></i> Rekap per Kasir</div>
            </div>
            <div class="table-wrap">
                <table id="cashierTable">
                    <thead id="cashierHead"></thead>
                    <tbody id="cashierBody"></tbody>
                    <tfoot id="cashierFoot"></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const PAY_COLORS = ['#4285F4','#34A853','#FBBC04','#EA4335','#A142F4','#00ACC1','#FF7043','#9E9E9E'];
let lastData = null;

function fmt(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 0 });
}

function fmtDate(d) {
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

function setRange(range) {
    const today = new Date();
    const pad   = n => String(n).padStart(2, '0');
    const iso   = d => d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate());

    let from, to;
    if (range === 'today') {
        from = to = iso(today);
    } else if (range === 'yesterday') {
        const y = new Date(today); y.setDate(y.getDate()-1);
        from = to = iso(y);
    } else if (range === 'week') {
        const w = new Date(today); w.setDate(w.getDate()-6);
        from = iso(w); to = iso(today);
    } else if (range === 'month') {
        from = iso(new Date(today.getFullYear(), today.getMonth(), 1));
        to   = iso(today);
    } else if (range === 'last_month') {
        const lm = new Date(today.getFullYear(), today.getMonth()-1, 1);
        const le = new Date(today.getFullYear(), today.getMonth(), 0);
        from = iso(lm); to = iso(le);
    }
    document.getElementById('dateFrom').value = from;
    document.getElementById('dateTo').value   = to;
}

async function fetchReport() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo   = document.getElementById('dateTo').value;
    const profile  = document.getElementById('posProfile').value.trim();

    if (!dateFrom || !dateTo) {
        alert('Pilih rentang tanggal terlebih dahulu.');
        return;
    }

    document.getElementById('loadingState').style.display = 'block';
    document.getElementById('resultArea').style.display   = 'none';
    document.getElementById('errorState').style.display   = 'none';

    const btn = document.getElementById('btnFetch');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Mengambil...';

    try {
        const res = await fetch('{{ route("mop-report.fetch") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ date_from: dateFrom, date_to: dateTo, pos_profile: profile }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Gagal mengambil data dari ERP HPY.');

        lastData = json;
        renderResult(json);
    } catch (e) {
        document.getElementById('errorMsg').textContent = e.message;
        document.getElementById('errorState').style.display = 'block';
    } finally {
        document.getElementById('loadingState').style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search"></i> Tampilkan';
    }
}

function renderResult(json) {
    document.getElementById('resultArea').style.display   = 'block';
    document.getElementById('truncatedWarn').style.display = json.truncated ? 'flex' : 'none';
    document.getElementById('matrixRange').textContent =
        document.getElementById('dateFrom').value + ' — ' + document.getElementById('dateTo').value;

    const modes = json.modes || [];
    const rows  = json.rows  || [];

    if (!rows.length || !modes.length) {
        document.getElementById('emptyState').style.display = 'block';
        document.getElementById('matrixWrap').style.display = 'none';
        return;
    }
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('matrixWrap').style.display = 'block';

    renderSummary(json);
    renderMatrix(json);
    renderCashier(json);
}

function renderSummary(json) {
    const wrap = document.getElementById('modeSummary');
    wrap.innerHTML = '';
    const grand = json.totals.grand_total || 0;

    json.modes.forEach((mode, i) => {
        const m   = json.totals.per_mode[mode] || { count: 0, total: 0 };
        const pct = grand > 0 ? (m.total / grand * 100) : 0;
        const color = PAY_COLORS[i % PAY_COLORS.length];
        const card = document.createElement('div');
        card.className = 'stat-card';
        card.innerHTML = `
            <div class="stat-icon" style="background:${color}1A;color:${color}"><i class="fas fa-money-check-alt"></i></div>
            <div>
                <div class="stat-value money" style="color:${color}">${fmt(m.total)}</div>
                <div class="stat-label">${mode} · ${Number(m.count).toLocaleString('id-ID')} pmt · ${pct.toFixed(1)}%</div>
            </div>`;
        wrap.appendChild(card);
    });
}

function renderMatrix(json) {
    const modes = json.modes;
    const rows  = json.rows;
    const per   = json.totals.per_mode;

    // Header
    let head = '<tr><th style="position:sticky;left:0;background:inherit">Tanggal</th><th style="text-align:right">Trx</th>';
    modes.forEach((m, i) => {
        head += `<th style="text-align:right">
            <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:${PAY_COLORS[i % PAY_COLORS.length]};margin-right:5px"></span>${m}</th>`;
    });
    head += '<th style="text-align:right">Total Hari</th></tr>';
    document.getElementById('matrixHead').innerHTML = head;

    // Body
    let body = '';
    rows.forEach(r => {
        body += `<tr><td style="position:sticky;left:0;background:#fff"><span class="font-medium">${fmtDate(r.date)}</span></td>`;
        body += `<td style="text-align:right" class="text-muted">${Number(r.tx_count).toLocaleString('id-ID')}</td>`;
        modes.forEach(m => {
            const c = r.cells[m] || { total: 0, count: 0 };
            body += `<td style="text-align:right" class="money">${c.total ? fmt(c.total) : '<span class="text-muted">-</span>'}</td>`;
        });
        body += `<td style="text-align:right" class="money font-medium">${fmt(r.total)}</td></tr>`;
    });
    document.getElementById('matrixBody').innerHTML = body;

    // Footer
    let foot = '<tr style="border-top:2px solid #DADCE0;font-weight:600">';
    foot += '<td style="position:sticky;left:0;background:#F8F9FA">Total</td>';
    foot += `<td style="text-align:right">${Number(json.totals.grand_count).toLocaleString('id-ID')}</td>`;
    modes.forEach(m => {
        foot += `<td style="text-align:right" class="money">${fmt((per[m] || {}).total)}</td>`;
    });
    foot += `<td style="text-align:right" class="money text-blue">${fmt(json.totals.grand_total)}</td></tr>`;
    document.getElementById('matrixFoot').innerHTML = foot;
}

function renderCashier(json) {
    const modes = json.modes;
    const rows  = json.cashier_rows || [];
    const per   = json.totals.per_mode;

    // Header
    let head = '<tr><th style="position:sticky;left:0;background:inherit">Kasir</th><th style="text-align:right">Trx</th>';
    modes.forEach((m, i) => {
        head += `<th style="text-align:right">
            <span style="display:inline-block;width:9px;height:9px;border-radius:2px;background:${PAY_COLORS[i % PAY_COLORS.length]};margin-right:5px"></span>${m}</th>`;
    });
    head += '<th style="text-align:right">Total</th></tr>';
    document.getElementById('cashierHead').innerHTML = head;

    // Body
    let body = '';
    rows.forEach(r => {
        body += `<tr><td style="position:sticky;left:0;background:#fff"><span class="font-medium">${r.cashier}</span></td>`;
        body += `<td style="text-align:right" class="text-muted">${Number(r.count).toLocaleString('id-ID')}</td>`;
        modes.forEach(m => {
            const c = r.cells[m] || { total: 0, count: 0 };
            body += `<td style="text-align:right" class="money">${c.total ? fmt(c.total) : '<span class="text-muted">-</span>'}</td>`;
        });
        body += `<td style="text-align:right" class="money font-medium">${fmt(r.total)}</td></tr>`;
    });
    document.getElementById('cashierBody').innerHTML = body ||
        `<tr><td colspan="${modes.length + 3}" style="text-align:center;padding:16px" class="text-muted">Tidak ada data kasir</td></tr>`;

    // Footer
    let foot = '<tr style="border-top:2px solid #DADCE0;font-weight:600">';
    foot += '<td style="position:sticky;left:0;background:#F8F9FA">Total</td>';
    foot += `<td style="text-align:right">${Number(json.totals.grand_count).toLocaleString('id-ID')}</td>`;
    modes.forEach(m => {
        foot += `<td style="text-align:right" class="money">${fmt((per[m] || {}).total)}</td>`;
    });
    foot += `<td style="text-align:right" class="money text-blue">${fmt(json.totals.grand_total)}</td></tr>`;
    document.getElementById('cashierFoot').innerHTML = foot;
}

function exportCsv() {
    if (!lastData) return;
    const modes = lastData.modes;
    const sep   = ';';
    const esc   = v => `"${String(v).replace(/"/g, '""')}"`;

    const lines = [];
    lines.push(['Tanggal', 'Transaksi', ...modes, 'Total Hari'].map(esc).join(sep));
    lastData.rows.forEach(r => {
        const cells = modes.map(m => (r.cells[m] || {}).total || 0);
        lines.push([r.date, r.tx_count, ...cells, r.total].map(esc).join(sep));
    });
    const per = lastData.totals.per_mode;
    const totalRow = ['Total', lastData.totals.grand_count,
        ...modes.map(m => (per[m] || {}).total || 0), lastData.totals.grand_total];
    lines.push(totalRow.map(esc).join(sep));

    // Rekap per Kasir
    const cashierRows = lastData.cashier_rows || [];
    if (cashierRows.length) {
        lines.push('');
        lines.push(['Kasir', 'Transaksi', ...modes, 'Total'].map(esc).join(sep));
        cashierRows.forEach(r => {
            const cells = modes.map(m => (r.cells[m] || {}).total || 0);
            lines.push([r.cashier, r.count, ...cells, r.total].map(esc).join(sep));
        });
    }

    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `mode-of-payment_${document.getElementById('dateFrom').value}_${document.getElementById('dateTo').value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>
@endpush
