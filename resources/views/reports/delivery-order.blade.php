@extends('layouts.app')
@section('title', 'Laporan DO')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-file-invoice-dollar text-blue"></i> Laporan Delivery Order</div>
        <div class="page-subtitle">Penjualan Delivery Order dari data lokal &mdash; verifikasi Sales Invoice &amp; Delivery Note ke ERP HPY</div>
    </div>
</div>

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('do-report.index') }}"
              style="display:grid;grid-template-columns:repeat(4,1fr) auto;gap:12px;align-items:flex-end">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Dari Tgl Kirim</label>
                <input type="date" name="date_from" class="form-control" value="{{ $from->format('Y-m-d') }}"
                       @disabled($dateLocked)>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Sampai Tgl Kirim</label>
                <input type="date" name="date_to" class="form-control" value="{{ $to->format('Y-m-d') }}"
                       @disabled($dateLocked)>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Status Order</label>
                <select name="status" class="form-control">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','confirmed'=>'Confirmed','delivering'=>'Delivering','completed'=>'Completed'] as $k=>$v)
                        <option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Pembayaran</label>
                <select name="payment" class="form-control">
                    <option value="">Semua</option>
                    @foreach(['unpaid'=>'Belum Lunas','partial'=>'DP','paid'=>'Lunas'] as $k=>$v)
                        <option value="{{ $k }}" @selected($payment===$k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="height:42px;border-radius:8px">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            @if($dateLocked)
            <p style="grid-column:1/-1;margin:0;font-size:12px;color:var(--text3)">
                <i class="fas fa-lock"></i> Laporan dikunci ke tanggal hari ini ({{ today()->format('d/m/Y') }}).
            </p>
            @endif
        </form>
    </div>
</div>

{{-- Ringkasan --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">
    <div class="card"><div class="card-body">
        <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Total Penjualan</div>
        <div style="font-size:22px;font-weight:800;color:var(--text1)">Rp {{ number_format($totalSales,0,',','.') }}</div>
        <div class="text-muted text-xs">{{ $count }} order</div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Sudah Dibayar</div>
        <div style="font-size:22px;font-weight:800;color:var(--green)">Rp {{ number_format($totalPaid,0,',','.') }}</div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Outstanding (Piutang)</div>
        <div style="font-size:22px;font-weight:800;color:{{ $totalOutstanding>0 ? 'var(--red)' : 'var(--green)' }}">Rp {{ number_format($totalOutstanding,0,',','.') }}</div>
    </div></div>
    <div class="card"><div class="card-body">
        <div class="text-muted text-xs" style="text-transform:uppercase;letter-spacing:.5px">Invoice HPY Terbit</div>
        <div style="font-size:22px;font-weight:800;color:var(--blue)">{{ $withInvoice }} / {{ $count }}</div>
        <div class="text-muted text-xs">DO dengan Sales Invoice</div>
    </div></div>
</div>

{{-- Tabel --}}
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>No. DO</th>
                    <th>Tgl Kirim</th>
                    <th>Customer</th>
                    <th class="text-right">Total</th>
                    <th class="text-right">Dibayar</th>
                    <th class="text-right">Outstanding</th>
                    <th>Status</th>
                    <th>Bayar</th>
                    <th>Invoice HPY</th>
                    <th style="white-space:nowrap">Verifikasi HPY</th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $o)
                @php
                    $paid = $o->totalPaid();
                    $out  = $o->outstanding();
                    $statusColor = ['draft'=>'badge-gray','confirmed'=>'badge-blue','delivering'=>'badge-yellow','completed'=>'badge-green'][$o->status] ?? 'badge-gray';
                    $payColor = ['unpaid'=>'badge-red','partial'=>'badge-yellow','paid'=>'badge-green'][$o->payment_status] ?? 'badge-gray';
                    $payLabel = ['unpaid'=>'Belum Lunas','partial'=>'DP','paid'=>'Lunas'][$o->payment_status] ?? '-';
                @endphp
                <tr>
                    <td><a href="{{ route('delivery-orders.show', $o) }}" class="text-blue font-medium">{{ $o->order_no }}</a></td>
                    <td>{{ $o->delivery_date?->isoFormat('D MMM Y') ?? '-' }}</td>
                    <td>{{ $o->customer?->name ?? '-' }}</td>
                    <td class="text-right money">Rp {{ number_format($o->total,0,',','.') }}</td>
                    <td class="text-right money">Rp {{ number_format($paid,0,',','.') }}</td>
                    <td class="text-right money" style="color:{{ $out>0 ? 'var(--red)' : 'var(--green)' }};font-weight:700">Rp {{ number_format($out,0,',','.') }}</td>
                    <td><span class="badge {{ $statusColor }}">{{ strtoupper($o->status) }}</span></td>
                    <td><span class="badge {{ $payColor }}">{{ $payLabel }}</span></td>
                    <td>
                        @if($o->erp_sales_invoice)
                            <span class="text-xs" style="font-family:monospace">{{ $o->erp_sales_invoice }}</span>
                        @else
                            <span class="text-muted text-xs">belum terbit</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="checkErp({{ $o->id }})">
                            <i class="fas fa-cloud-download-alt"></i> Cek HPY
                        </button>
                    </td>
                </tr>
                <tr id="erp-row-{{ $o->id }}" style="display:none;background:var(--bg2,#f8fafc)">
                    <td colspan="10" style="padding:0">
                        <div id="erp-panel-{{ $o->id }}" style="padding:16px 20px"></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3)">Tidak ada Delivery Order pada rentang ini</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
const CHECK_URL = "{{ url('do-report') }}";

function money(n) {
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}

function docBadge(label) {
    const map = { 'Submitted': '#16a34a', 'Draft': '#6b7280', 'Dibatalkan': '#dc2626' };
    const c = map[label] || '#6b7280';
    return `<span style="background:${c};color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700">${label}</span>`;
}

async function checkErp(id) {
    const row   = document.getElementById('erp-row-' + id);
    const panel = document.getElementById('erp-panel-' + id);

    // Toggle: kalau sudah terbuka & berisi, tutup saja.
    if (row.style.display !== 'none' && panel.dataset.loaded === '1') {
        row.style.display = 'none';
        return;
    }

    row.style.display = '';
    panel.dataset.loaded = '';
    panel.innerHTML = '<div style="color:#5F6368"><i class="fas fa-spinner fa-spin"></i> Mengambil data dari ERP HPY...</div>';

    try {
        const res  = await fetch(`${CHECK_URL}/${id}/check-erp`, { headers: { 'Accept': 'application/json' } });
        const data = await res.json();

        if (!data.success) {
            panel.innerHTML = `<div style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> ${data.error}</div>`;
            return;
        }

        let html = '';

        // Sales Invoice
        html += '<div style="font-weight:700;margin-bottom:6px"><i class="fas fa-file-invoice"></i> Sales Invoice</div>';
        if (data.sales_invoice) {
            const si = data.sales_invoice;
            html += `<div style="margin-bottom:14px;font-size:13px;line-height:1.9">
                <span style="font-family:monospace;font-weight:600">${si.name}</span> ${docBadge(si.docstatus_label)}
                &nbsp;·&nbsp; Status: <b>${si.status ?? '-'}</b>
                &nbsp;·&nbsp; Total: ${money(si.grand_total)}
                &nbsp;·&nbsp; Outstanding: <b style="color:${si.outstanding > 0 ? '#dc2626' : '#16a34a'}">${money(si.outstanding)}</b>
                &nbsp;·&nbsp; per_billed: ${si.per_billed}%
                ${si.posting_date ? '&nbsp;·&nbsp; ' + si.posting_date : ''}
            </div>`;
        } else {
            html += '<div style="color:#6b7280;margin-bottom:14px;font-size:13px">Belum ada Sales Invoice untuk order ini di HPY.</div>';
        }

        // Delivery Note
        html += '<div style="font-weight:700;margin-bottom:6px"><i class="fas fa-truck"></i> Delivery Note</div>';
        if (data.delivery_notes && data.delivery_notes.length) {
            html += '<div style="font-size:13px;line-height:1.9">';
            data.delivery_notes.forEach(dn => {
                html += `<div>
                    <span style="font-family:monospace;font-weight:600">${dn.name}</span> ${docBadge(dn.docstatus_label)}
                    &nbsp;·&nbsp; Status: <b>${dn.status ?? '-'}</b>
                    &nbsp;·&nbsp; Total: ${money(dn.grand_total)}
                    &nbsp;·&nbsp; per_billed: ${dn.per_billed}%
                    ${dn.posting_date ? '&nbsp;·&nbsp; ' + dn.posting_date : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<div style="color:#6b7280;font-size:13px">Belum ada Delivery Note &mdash; barang belum diterbitkan pengirimannya.</div>';
        }

        // Payment Entry
        html += '<div style="font-weight:700;margin:14px 0 6px"><i class="fas fa-money-check-alt"></i> Payment Entry</div>';
        if (data.payment_entries && data.payment_entries.length) {
            html += '<div style="font-size:13px;line-height:1.9">';
            data.payment_entries.forEach(pe => {
                html += `<div>
                    <span style="font-family:monospace;font-weight:600">${pe.name}</span> ${docBadge(pe.docstatus_label)}
                    &nbsp;·&nbsp; ${pe.method ?? '-'}
                    &nbsp;·&nbsp; Jumlah: ${money(pe.paid_amount != null ? pe.paid_amount : pe.local_amount)}
                    ${pe.posting_date ? '&nbsp;·&nbsp; ' + pe.posting_date : ''}
                </div>`;
            });
            html += '</div>';
        } else {
            html += '<div style="color:#6b7280;font-size:13px">Belum ada Payment Entry &mdash; pembayaran belum tersinkron ke HPY.</div>';
        }

        panel.innerHTML = html;
        panel.dataset.loaded = '1';
    } catch (e) {
        panel.innerHTML = `<div style="color:#dc2626"><i class="fas fa-exclamation-triangle"></i> Gagal menghubungi server: ${e.message}</div>`;
    }
}
</script>
@endsection
