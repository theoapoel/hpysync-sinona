@extends('layouts.app')
@section('title', 'Rekap Order')

@push('styles')
<style>
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; margin-bottom:20px; }
.filter-bar .form-group { margin-bottom:0; }
.filter-bar label { font-size:12px; font-weight:600; color:var(--text2); display:block; margin-bottom:4px; }
.filter-bar .form-control { padding:8px 12px; font-size:13px; }

.summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:20px; }
.summary-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px; text-align:center; }
.summary-card .val { font-family:'Google Sans',sans-serif; font-size:22px; font-weight:700; line-height:1; }
.summary-card .lbl { font-size:11px; color:var(--text3); font-weight:600; margin-top:4px; text-transform:uppercase; letter-spacing:.5px; }

.tag-do  { background:#E8F0FE; color:#1967D2; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.tag-fg  { background:#E6F4EA; color:#137333; border-radius:4px; padding:1px 6px; font-size:11px; font-weight:600; }
.qty-total { font-family:'Google Sans',sans-serif; font-weight:700; color:var(--text); }
.empty-state { text-align:center; padding:60px 20px; color:var(--text3); }
.empty-state i { font-size:48px; margin-bottom:16px; display:block; }

@media print {
    .header, .sidebar, .filter-section, .no-print { display:none !important; }
    .main { margin:0 !important; padding:0 !important; }
    body { font-size:12px; }
    .print-title { display:block !important; }
    table { font-size:11px; }
    th, td { padding:6px 8px !important; }
}
.print-title { display:none; font-family:'Google Sans',sans-serif; margin-bottom:16px; }
</style>
@endpush

@section('content')
<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fas fa-layer-group text-blue" style="margin-right:8px"></i>Rekap Order</h1>
        <p class="page-subtitle">Rekapitulasi produk dari Delivery Order (confirmed, by tgl delivery) & Permintaan FG (submitted, by tgl dibutuhkan)</p>
    </div>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

{{-- Print header (hanya muncul saat print) --}}
<div class="print-title">
    <h2 style="font-size:18px;margin-bottom:4px">Rekap Order</h2>
    <p style="font-size:12px;color:#555">
        @if($dateFrom || $dateTo)
            Periode: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '…' }}
            – {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '…' }}
        @else
            Semua periode
        @endif
        &nbsp;|&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

{{-- Filter --}}
<div class="card filter-section no-print" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="{{ route('rekap-order.index') }}">
            <div class="filter-bar">
                <div class="form-group">
                    <label>Dari Tanggal <span class="text-muted" style="font-weight:400">(tgl delivery / tgl dibutuhkan)</span></label>
                    <input type="date" name="date_from" class="form-control"
                           value="{{ $dateFrom }}" style="width:160px">
                </div>
                <div class="form-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control"
                           value="{{ $dateTo }}" style="width:160px">
                </div>
                <div class="form-group">
                    <label>Sumber Order</label>
                    <select name="source" class="form-control form-select" style="width:180px">
                        <option value="" {{ !$source ? 'selected' : '' }}>Semua</option>
                        <option value="delivery" {{ $source === 'delivery' ? 'selected' : '' }}>Delivery Order saja</option>
                        <option value="fg_request" {{ $source === 'fg_request' ? 'selected' : '' }}>Permintaan FG saja</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="{{ route('rekap-order.index') }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="summary-grid">
    <div class="summary-card">
        <div class="val text-blue">{{ $summary['total_items'] }}</div>
        <div class="lbl">Jenis Produk</div>
    </div>
    @if(!$source || $source === 'delivery')
    <div class="summary-card">
        <div class="val" style="color:#1967D2">{{ number_format($summary['total_qty_do'], 0, ',', '.') }}</div>
        <div class="lbl">Total Qty — DO</div>
    </div>
    @endif
    @if(!$source || $source === 'fg_request')
    <div class="summary-card">
        <div class="val text-green">{{ number_format($summary['total_qty_sr'], 0, ',', '.') }}</div>
        <div class="lbl">Total Qty — Perm. FG</div>
    </div>
    @endif
    @if(!$source)
    <div class="summary-card">
        <div class="val text-red">{{ number_format($summary['total_qty'], 0, ',', '.') }}</div>
        <div class="lbl">Total Qty Keseluruhan</div>
    </div>
    @endif
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-table" style="margin-right:6px;color:var(--blue)"></i>
            Daftar Produk / Item
        </span>
        <span class="badge badge-blue">{{ $summary['total_items'] }} item</span>
    </div>
    <div class="card-body" style="padding:0;">
        @if(count($merged) === 0)
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p style="font-size:15px;font-weight:600;color:var(--text2)">Tidak ada data</p>
                <p style="font-size:13px;">Tidak ada order aktif pada periode ini. Coba ubah filter tanggal atau sumber.</p>
            </div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Nama Produk / Item</th>
                        <th>Kode Item</th>
                        <th>Satuan</th>
                        @if(!$source || $source === 'delivery')
                        <th style="text-align:right">
                            <span class="tag-do">DO</span>
                        </th>
                        @endif
                        @if(!$source || $source === 'fg_request')
                        <th style="text-align:right">
                            <span class="tag-fg">Perm. FG</span>
                        </th>
                        @endif
                        @if(!$source)
                        <th style="text-align:right">Total</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($merged as $i => $row)
                    <tr>
                        <td class="text-muted text-sm">{{ $i + 1 }}</td>
                        <td style="font-weight:500">{{ $row['item_name'] }}</td>
                        <td class="text-muted text-sm">{{ $row['item_code'] ?: '—' }}</td>
                        <td class="text-sm">{{ $row['uom'] ?: '—' }}</td>
                        @if(!$source || $source === 'delivery')
                        <td style="text-align:right">
                            @if($row['qty_do'] > 0)
                                <span style="font-weight:600;color:#1967D2">
                                    {{ number_format($row['qty_do'], 0, ',', '.') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endif
                        @if(!$source || $source === 'fg_request')
                        <td style="text-align:right">
                            @if($row['qty_sr'] > 0)
                                <span style="font-weight:600;color:#137333">
                                    {{ number_format($row['qty_sr'], 0, ',', '.') }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        @endif
                        @if(!$source)
                        <td style="text-align:right">
                            <span class="qty-total">{{ number_format($row['qty_total'], 0, ',', '.') }}</span>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:var(--surface2);font-weight:700;">
                        <td colspan="4" style="padding:12px 16px;font-size:13px;">TOTAL</td>
                        @if(!$source || $source === 'delivery')
                        <td style="text-align:right;padding:12px 16px;color:#1967D2">
                            {{ number_format($summary['total_qty_do'], 0, ',', '.') }}
                        </td>
                        @endif
                        @if(!$source || $source === 'fg_request')
                        <td style="text-align:right;padding:12px 16px;color:#137333">
                            {{ number_format($summary['total_qty_sr'], 0, ',', '.') }}
                        </td>
                        @endif
                        @if(!$source)
                        <td style="text-align:right;padding:12px 16px;color:var(--text)">
                            {{ number_format($summary['total_qty'], 0, ',', '.') }}
                        </td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
