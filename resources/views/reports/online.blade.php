@extends('layouts.app')
@section('title', 'Laporan Online HPY')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-cloud-download-alt text-blue"></i> Laporan Online HPY</div>
        <div class="page-subtitle">Tarik data transaksi historis langsung dari ERP HPY</div>
    </div>
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
        Data terlalu banyak — <strong>tabel di bawah</strong> hanya menampilkan 1.000 transaksi pertama.
        Angka ringkasan, grafik, dan rincian metode bayar tetap mencakup seluruh rentang tanggal.
        Perkecil rentang tanggal bila ingin tabelnya lengkap.
    </div>

    {{-- Summary cards --}}
    <div class="stat-grid" style="margin-bottom:20px">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
            <div>
                <div class="stat-value text-blue" id="statCount">0</div>
                <div class="stat-label">Total Transaksi</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div>
            <div>
                <div class="stat-value text-green money" id="statTotal">Rp 0</div>
                <div class="stat-label">Total Penjualan</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-calculator"></i></div>
            <div>
                <div class="stat-value money" style="color:#E37400" id="statAvg">Rp 0</div>
                <div class="stat-label">Rata-rata per Transaksi</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="stat-value text-blue" id="statDays">0</div>
                <div class="stat-label">Hari dengan Transaksi</div>
            </div>
        </div>
    </div>

    {{-- Chart --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar text-blue"></i> Penjualan per Hari</div>
            <span class="text-sm text-muted" id="chartDateRange"></span>
        </div>
        <div class="card-body">
            <canvas id="dailyChart" height="120"></canvas>
        </div>
    </div>

    {{-- Mode of Payment --}}
    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-wallet text-blue"></i> Metode Pembayaran</div>
        </div>
        <div class="card-body" style="display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.2fr);gap:24px;align-items:center">
            <div style="max-width:260px;margin:0 auto;width:100%">
                <canvas id="paymentChart"></canvas>
            </div>
            <div class="table-wrap">
                <table id="paymentTable">
                    <thead>
                        <tr>
                            <th>Metode</th>
                            <th style="text-align:right">Transaksi</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right">%</th>
                        </tr>
                    </thead>
                    <tbody id="paymentBody"></tbody>
                    <tfoot id="paymentFoot"></tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-list text-blue"></i> Daftar Transaksi</div>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="text" id="tableSearch" placeholder="Cari invoice / customer..."
                    class="form-control form-control-sm" style="width:220px;height:34px;border-radius:20px;font-size:13px"
                    oninput="filterTable(this.value)">
                <span class="text-sm text-muted" id="tableCount"></span>
            </div>
        </div>
        <div class="table-wrap">
            <table id="invoiceTable">
                <thead>
                    <tr>
                        <th>Invoice HPY</th>
                        <th>Invoice Lokal</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Customer</th>
                        <th>Grand Total</th>
                        <th>Metode Bayar</th>
                        <th>POS Profile</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal detail invoice --}}
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Detail Invoice</div>
            <button onclick="closeModal()" class="btn btn-ghost btn-sm" style="padding:4px 8px;border-radius:50%">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <div style="text-align:center;padding:40px">
                <div class="spinner" style="border-top-color:#4285F4;border-color:#E8F0FE;width:32px;height:32px;border-width:3px;margin:0 auto 12px"></div>
                <div class="text-muted">Memuat detail...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
let allInvoices  = [];
let dailyChart   = null;
let paymentChart = null;

const PAY_COLORS = ['#4285F4','#34A853','#FBBC04','#EA4335','#A142F4','#00ACC1','#FF7043','#9E9E9E'];

function fmt(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID', { minimumFractionDigits: 0 });
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
    const dateFrom  = document.getElementById('dateFrom').value;
    const dateTo    = document.getElementById('dateTo').value;
    const profile   = document.getElementById('posProfile').value.trim();

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
        const res  = await fetch('{{ route("online-report.fetch") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ date_from: dateFrom, date_to: dateTo, pos_profile: profile }),
        });

        const json = await res.json();

        if (!json.success) {
            throw new Error(json.error || 'Gagal mengambil data dari ERP HPY.');
        }

        allInvoices = json.invoices;
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
    const stats = json.stats;
    const daily = stats.daily_data;

    // Cards
    document.getElementById('statCount').textContent = stats.total_count.toLocaleString('id-ID');
    document.getElementById('statTotal').textContent = fmt(stats.total_sales);
    document.getElementById('statAvg').textContent   = fmt(stats.avg_per_tx);
    document.getElementById('statDays').textContent  = Object.keys(daily).length;

    // Truncated warning
    document.getElementById('truncatedWarn').style.display = json.truncated ? 'flex' : 'none';

    // Chart
    const labels = Object.keys(daily).map(d => {
        const dt = new Date(d + 'T00:00:00');
        return dt.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
    });
    const totals = Object.values(daily).map(v => v.total);
    const counts = Object.values(daily).map(v => v.count);

    const dateRange = document.getElementById('dateFrom').value + ' — ' + document.getElementById('dateTo').value;
    document.getElementById('chartDateRange').textContent = dateRange;

    if (dailyChart) dailyChart.destroy();
    dailyChart = new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Penjualan',
                data: totals,
                backgroundColor: '#4285F4',
                borderRadius: 5,
                borderSkipped: false,
                yAxisID: 'y',
            }, {
                label: 'Transaksi',
                data: counts,
                type: 'line',
                borderColor: '#34A853',
                backgroundColor: 'rgba(52,168,83,.12)',
                pointBackgroundColor: '#34A853',
                tension: 0.3,
                yAxisID: 'y2',
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label === 'Penjualan'
                            ? 'Penjualan: ' + fmt(ctx.raw)
                            : 'Transaksi: ' + ctx.raw,
                    }
                }
            },
            scales: {
                y:  { ticks: { callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k' }, grid: { color: '#F1F3F4' } },
                y2: { position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                x:  { grid: { display: false } },
            }
        }
    });

    // Payment breakdown
    renderPayment(stats.payment_data || []);

    // Table
    renderTable(allInvoices);
    document.getElementById('resultArea').style.display = 'block';
}

function renderPayment(rows) {
    const body = document.getElementById('paymentBody');
    const foot = document.getElementById('paymentFoot');
    body.innerHTML = '';
    foot.innerHTML = '';

    const grandTotal = rows.reduce((s, r) => s + Number(r.total), 0);
    const grandCount = rows.reduce((s, r) => s + Number(r.count), 0);

    rows.forEach((r, i) => {
        const pct = grandTotal > 0 ? (r.total / grandTotal * 100) : 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:${PAY_COLORS[i % PAY_COLORS.length]};margin-right:8px"></span>
                <span class="font-medium">${r.mode_of_payment}</span>
            </td>
            <td style="text-align:right">${Number(r.count).toLocaleString('id-ID')}</td>
            <td style="text-align:right" class="money">${fmt(r.total)}</td>
            <td style="text-align:right" class="text-muted">${pct.toFixed(1)}%</td>`;
        body.appendChild(tr);
    });

    if (rows.length) {
        foot.innerHTML = `
            <tr style="border-top:2px solid #DADCE0;font-weight:600">
                <td>Total</td>
                <td style="text-align:right">${grandCount.toLocaleString('id-ID')}</td>
                <td style="text-align:right" class="money">${fmt(grandTotal)}</td>
                <td style="text-align:right">100%</td>
            </tr>`;
    } else {
        body.innerHTML = '<tr><td colspan="4" class="text-muted" style="text-align:center;padding:24px">Tidak ada data pembayaran</td></tr>';
    }

    // Doughnut chart
    if (paymentChart) paymentChart.destroy();
    paymentChart = new Chart(document.getElementById('paymentChart'), {
        type: 'doughnut',
        data: {
            labels: rows.map(r => r.mode_of_payment),
            datasets: [{
                data: rows.map(r => r.total),
                backgroundColor: rows.map((_, i) => PAY_COLORS[i % PAY_COLORS.length]),
                borderWidth: 2,
                borderColor: '#fff',
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((s, v) => s + v, 0);
                            const pct = total > 0 ? (ctx.raw / total * 100).toFixed(1) : 0;
                            return `${ctx.label}: ${fmt(ctx.raw)} (${pct}%)`;
                        }
                    }
                }
            },
            cutout: '60%',
        }
    });
}

function renderTable(invoices) {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';

    invoices.forEach(inv => {
        const statusBadge = inv.status === 'Submitted'
            ? '<span class="badge badge-green">SUBMITTED</span>'
            : '<span class="badge badge-red">' + inv.status.toUpperCase() + '</span>';

        const customerDisplay = inv.customer_name || inv.customer || '<span class="text-muted">Walk-in</span>';

        const tr = document.createElement('tr');
        tr.setAttribute('data-search', (inv.name + ' ' + (inv.local_invoice || '') + ' ' + (inv.customer_name || '') + ' ' + (inv.customer || '') + ' ' + (inv.mode_of_payment || '')).toLowerCase());
        const localInv = inv.local_invoice
            ? `<span class="font-medium">${inv.local_invoice}</span>`
            : '<span class="text-muted">-</span>';
        tr.innerHTML = `
            <td><span class="font-medium text-blue" style="cursor:pointer" onclick="openDetail('${inv.name}')">${inv.name}</span></td>
            <td class="text-sm">${localInv}</td>
            <td>${inv.posting_date}</td>
            <td class="text-muted text-sm">${(inv.posting_time || '').substring(0,5)}</td>
            <td>${customerDisplay}</td>
            <td class="money">${fmt(inv.grand_total)}</td>
            <td class="text-sm">${inv.mode_of_payment || '<span class="text-muted">-</span>'}</td>
            <td class="text-sm text-muted">${inv.pos_profile || '-'}</td>
            <td>${statusBadge}</td>
            <td><button class="btn btn-ghost btn-sm" onclick="openDetail('${inv.name}')"><i class="fas fa-eye"></i></button></td>
        `;
        tbody.appendChild(tr);
    });

    updateTableCount(invoices.length, allInvoices.length);
}

function filterTable(q) {
    const rows     = document.querySelectorAll('#tableBody tr');
    const keyword  = q.toLowerCase();
    let visible    = 0;

    rows.forEach(row => {
        const match = !keyword || row.getAttribute('data-search').includes(keyword);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    updateTableCount(visible, allInvoices.length);
}

function updateTableCount(visible, total) {
    const el = document.getElementById('tableCount');
    el.textContent = visible === total
        ? total + ' transaksi'
        : visible + ' dari ' + total + ' transaksi';
}

async function openDetail(name) {
    document.getElementById('modalTitle').textContent  = name;
    document.getElementById('modalBody').innerHTML = `
        <div style="text-align:center;padding:40px">
            <div style="width:32px;height:32px;border:3px solid #E8F0FE;border-top-color:#4285F4;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 12px"></div>
            <div class="text-muted">Memuat detail...</div>
        </div>`;
    document.getElementById('detailModal').classList.add('show');

    try {
        const res  = await fetch(`{{ url('online-report/detail') }}/` + encodeURIComponent(name));
        const json = await res.json();

        if (!json.success) throw new Error(json.error);

        const d      = json.data;
        const items  = d.items  || [];
        const taxes  = d.taxes  || [];
        const pays   = d.payments || [];

        const itemRows = items.map(it => `
            <tr>
                <td>${it.item_code}</td>
                <td>${it.item_name}</td>
                <td style="text-align:right">${it.qty} ${it.uom || ''}</td>
                <td style="text-align:right" class="money">${fmt(it.rate)}</td>
                <td style="text-align:right" class="money">${fmt(it.amount)}</td>
            </tr>`).join('');

        const taxRows = taxes.map(t => `
            <tr>
                <td colspan="4" style="color:#5F6368">${t.description}</td>
                <td style="text-align:right" class="money">${fmt(t.tax_amount)}</td>
            </tr>`).join('');

        const payRows = pays.map(p => `
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F1F3F4">
                <span>${p.mode_of_payment}</span>
                <span class="money font-medium">${fmt(p.amount)}</span>
            </div>`).join('');

        document.getElementById('modalBody').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;font-size:14px">
                <div>
                    <div class="text-muted text-xs" style="margin-bottom:2px">Tanggal & Waktu</div>
                    <div class="font-medium">${d.posting_date} ${(d.posting_time||'').substring(0,5)}</div>
                </div>
                <div>
                    <div class="text-muted text-xs" style="margin-bottom:2px">Customer</div>
                    <div class="font-medium">${d.customer_name || d.customer || 'Walk-in'}</div>
                </div>
                <div>
                    <div class="text-muted text-xs" style="margin-bottom:2px">POS Profile</div>
                    <div class="font-medium">${d.pos_profile || '-'}</div>
                </div>
                <div>
                    <div class="text-muted text-xs" style="margin-bottom:2px">Status</div>
                    <div>${d.status === 'Submitted' ? '<span class="badge badge-green">SUBMITTED</span>' : '<span class="badge badge-red">'+(d.status||'-')+'</span>'}</div>
                </div>
            </div>

            <div style="margin-bottom:16px">
                <div class="font-medium" style="margin-bottom:8px;font-size:13px;color:#5F6368;text-transform:uppercase;letter-spacing:.5px">Item</div>
                <div class="table-wrap">
                    <table style="font-size:13px">
                        <thead><tr>
                            <th>Kode</th><th>Nama Item</th>
                            <th style="text-align:right">Qty</th>
                            <th style="text-align:right">Rate</th>
                            <th style="text-align:right">Jumlah</th>
                        </tr></thead>
                        <tbody>${itemRows}${taxRows}</tbody>
                    </table>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div>
                    <div class="font-medium" style="margin-bottom:8px;font-size:13px;color:#5F6368;text-transform:uppercase;letter-spacing:.5px">Pembayaran</div>
                    ${payRows || '<div class="text-muted text-sm">Tidak ada data pembayaran</div>'}
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:16px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
                        <span class="text-muted">Net Total</span>
                        <span class="money">${fmt(d.net_total)}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
                        <span class="text-muted">Pajak & Biaya</span>
                        <span class="money">${fmt(d.total_taxes_and_charges || 0)}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid #DADCE0">
                        <span class="font-medium">Grand Total</span>
                        <span class="money font-medium text-blue" style="font-size:16px">${fmt(d.grand_total)}</span>
                    </div>
                </div>
            </div>
        `;

    } catch (e) {
        document.getElementById('modalBody').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> ${e.message}
            </div>`;
    }
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('show');
}

document.getElementById('detailModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
@endpush
