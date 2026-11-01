@extends('layouts.app')
@section('title', 'Laporan Transfer Barang')

@push('styles')
<style>
/* Filter */
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
.filter-bar .form-group { margin-bottom:0; }
.filter-bar label { font-size:12px; font-weight:600; color:var(--text2); display:block; margin-bottom:4px; }
.filter-bar .form-control { padding:8px 12px; font-size:13px; }

/* Summary */
.summary-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin-bottom:20px; }
.summary-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:16px; text-align:center; }
.summary-card .val { font-family:'Google Sans',sans-serif; font-size:22px; font-weight:700; line-height:1; }
.summary-card .lbl { font-size:11px; color:var(--text3); font-weight:600; margin-top:4px; text-transform:uppercase; letter-spacing:.5px; }

/* Transfer group */
.transfer-group { border:1px solid var(--border); border-radius:var(--radius); margin-bottom:12px; overflow:hidden; }
.transfer-head {
    display:flex; align-items:center; gap:12px; flex-wrap:wrap;
    padding:12px 16px; background:var(--surface2);
    cursor:pointer; user-select:none;
    border-bottom:1px solid var(--border);
}
.transfer-head:hover { background:#EBEBED; }
.transfer-head .toggle-icon { color:var(--text3); font-size:13px; transition:transform .2s; }
.transfer-head.open .toggle-icon { transform:rotate(90deg); }
.transfer-no { font-family:'Google Sans',sans-serif; font-weight:700; font-size:14px; }
.wh-route { font-size:13px; color:var(--text2); }
.wh-route .wh-name { font-weight:600; color:var(--text); }
.transfer-body { display:none; }
.transfer-body.open { display:block; }

/* Items table inside group */
.items-table { width:100%; border-collapse:collapse; font-size:13px; }
.items-table th { padding:8px 16px; font-size:11px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid var(--border); background:var(--surface); }
.items-table td { padding:9px 16px; border-bottom:1px solid var(--border); }
.items-table tbody tr:last-child td { border-bottom:none; }
.items-table tbody tr:hover { background:#F8F9FA; }

/* diff badges */
.diff-ok   { color:var(--green); font-weight:600; }
.diff-warn { color:#E37400; font-weight:600; }
.diff-miss { color:var(--red); font-weight:600; }

/* Status badges */
.status-submitted { background:#E6F4EA; color:#137333; }
.status-draft     { background:var(--surface2); color:var(--text2); }
.status-cancelled { background:#FCE8E6; color:#B31412; }
.type-out { background:#E8F0FE; color:#1967D2; }
.type-in  { background:#E6F4EA; color:#137333; }
.erp-synced  { background:#E6F4EA; color:#137333; }
.erp-pending { background:#FEF3E2; color:#B06000; }
.erp-failed  { background:#FCE8E6; color:#B31412; }

.empty-state { text-align:center; padding:60px 20px; color:var(--text3); }
.empty-state i { font-size:48px; margin-bottom:16px; display:block; }

/* Print */
@media print {
    .header, .sidebar, .filter-section, .no-print { display:none !important; }
    .main { margin:0 !important; padding:0 !important; }
    .transfer-body { display:block !important; }
    .transfer-head { cursor:default; }
    .toggle-icon { display:none; }
    body { font-size:11px; }
    .transfer-group { break-inside:avoid; margin-bottom:8px; }
    th, td { padding:5px 10px !important; }
}
.print-title { display:none; font-family:'Google Sans',sans-serif; margin-bottom:16px; }
@media print { .print-title { display:block; } }
</style>
@endpush

@section('content')
<div class="page-header no-print">
    <div>
        <h1 class="page-title">
            <i class="fas fa-file-alt text-blue" style="margin-right:8px"></i>Laporan Transfer Barang
        </h1>
        <p class="page-subtitle">Detail item per dokumen transfer — Kirim & Terima</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost btn-sm no-print">
            <i class="fas fa-list"></i> Daftar Transfer
        </a>
        <button onclick="expandAll()" class="btn btn-ghost btn-sm no-print">
            <i class="fas fa-expand-alt"></i> Buka Semua
        </button>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

{{-- Print header --}}
<div class="print-title">
    <h2 style="font-size:18px;margin-bottom:4px">Laporan Transfer Barang</h2>
    <p style="font-size:12px;color:#555">
        @if($dateFrom || $dateTo)
            Periode: {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '…' }}
            – {{ $dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : '…' }}
        @else
            Semua periode
        @endif
        @if($type) &nbsp;|&nbsp; Tipe: {{ $type === 'outgoing' ? 'Kirim' : 'Terima' }} @endif
        @if($warehouse) &nbsp;|&nbsp; Gudang: {{ $warehouse }} @endif
        &nbsp;|&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }}
    </p>
</div>

{{-- Filter --}}
<div class="card filter-section no-print" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" action="{{ route('stock-transfer.report') }}">
            <div class="filter-bar">
                <div class="form-group">
                    <label>Tanggal Dari</label>
                    <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" style="width:150px">
                </div>
                <div class="form-group">
                    <label>Tanggal Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" style="width:150px">
                </div>
                <div class="form-group">
                    <label>Tipe</label>
                    <select name="type" class="form-control form-select" style="width:150px">
                        <option value="">Semua Tipe</option>
                        <option value="outgoing" {{ $type === 'outgoing' ? 'selected' : '' }}>Kirim (Outgoing)</option>
                        <option value="incoming" {{ $type === 'incoming' ? 'selected' : '' }}>Terima (Incoming)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control form-select" style="width:150px">
                        <option value="">Semua Status</option>
                        <option value="submitted"  {{ $status === 'submitted'  ? 'selected' : '' }}>Submitted</option>
                        <option value="draft"      {{ $status === 'draft'      ? 'selected' : '' }}>Draft</option>
                        <option value="cancelled"  {{ $status === 'cancelled'  ? 'selected' : '' }}>Dibatalkan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gudang</label>
                    <input type="text" name="warehouse" class="form-control" placeholder="Cari nama gudang…"
                           value="{{ $warehouse }}" style="width:180px">
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="{{ route('stock-transfer.report') }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Summary --}}
<div class="summary-grid">
    <div class="summary-card">
        <div class="val text-blue">{{ $summary['total'] }}</div>
        <div class="lbl">Total Transfer</div>
    </div>
    <div class="summary-card">
        <div class="val" style="color:#1967D2">{{ $summary['outgoing'] }}</div>
        <div class="lbl">Kirim (Outgoing)</div>
    </div>
    <div class="summary-card">
        <div class="val text-green">{{ $summary['incoming'] }}</div>
        <div class="lbl">Terima (Incoming)</div>
    </div>
    <div class="summary-card">
        <div class="val" style="color:#E37400">{{ $summary['total_items'] }}</div>
        <div class="lbl">Jenis Item</div>
    </div>
    <div class="summary-card">
        <div class="val text-blue">{{ number_format($summary['total_qty_sent'], 2, ',', '.') }}</div>
        <div class="lbl">Total Qty Kirim</div>
    </div>
    @if($summary['total_qty_received'] > 0)
    <div class="summary-card">
        <div class="val text-green">{{ number_format($summary['total_qty_received'], 2, ',', '.') }}</div>
        <div class="lbl">Total Qty Diterima</div>
    </div>
    @endif
</div>

{{-- Transfer Groups --}}
@if($transfers->isEmpty())
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p style="font-size:15px;font-weight:600;color:var(--text2)">Tidak ada data transfer</p>
            <p style="font-size:13px;">Coba ubah filter tanggal, tipe, atau gudang.</p>
        </div>
    </div>
@else
    @foreach($transfers as $transfer)
    @php
        $isOut = $transfer->type === 'outgoing';
        $statusClass = match($transfer->status) {
            'submitted'  => 'status-submitted',
            'cancelled'  => 'status-cancelled',
            default      => 'status-draft',
        };
        $statusLabel = match($transfer->status) {
            'submitted'  => 'Submitted',
            'cancelled'  => 'Dibatalkan',
            default      => 'Draft',
        };
        $erpClass = match($transfer->erp_sync_status) {
            'synced'  => 'erp-synced',
            'failed'  => 'erp-failed',
            default   => 'erp-pending',
        };
        $erpLabel = match($transfer->erp_sync_status) {
            'synced'  => 'Synced',
            'failed'  => 'Failed',
            default   => 'Pending',
        };
    @endphp
    <div class="transfer-group">
        <div class="transfer-head" onclick="toggleGroup(this)">
            <i class="fas fa-chevron-right toggle-icon"></i>
            <span class="transfer-no">{{ $transfer->transfer_no }}</span>
            <span class="badge {{ $isOut ? 'type-out' : 'type-in' }}">
                <i class="fas {{ $isOut ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                {{ $isOut ? 'Kirim' : 'Terima' }}
            </span>
            <span class="wh-route">
                <span class="wh-name">{{ $transfer->from_warehouse }}</span>
                <i class="fas fa-long-arrow-alt-right" style="margin:0 6px;color:var(--text3)"></i>
                <span class="wh-name">{{ $transfer->to_warehouse }}</span>
            </span>
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
            <span class="badge {{ $erpClass }}" style="font-size:11px">ERP: {{ $erpLabel }}</span>
            <span class="text-muted text-sm" style="margin-left:auto;display:flex;align-items:center;gap:10px;">
                <span>
                    {{ $transfer->items->count() }} item
                    &nbsp;·&nbsp;
                    {{ local_dt($transfer->created_at) }}
                    @if($transfer->user)
                        &nbsp;·&nbsp; {{ $transfer->user->name }}
                    @endif
                </span>
                <a href="{{ route('stock-transfer.surat-jalan', $transfer) }}"
                   target="_blank" onclick="event.stopPropagation()"
                   class="btn btn-ghost btn-sm no-print"
                   style="padding:4px 10px;font-size:12px;">
                    <i class="fas fa-print"></i>
                    {{ $transfer->type === 'incoming' ? 'Bukti Terima' : 'Surat Jalan' }}
                </a>
                <a href="{{ route('stock-transfer.show', $transfer) }}"
                   onclick="event.stopPropagation()"
                   class="btn btn-ghost btn-sm no-print"
                   style="padding:4px 10px;font-size:12px;">
                    <i class="fas fa-eye"></i>
                </a>
            </span>
        </div>

        <div class="transfer-body">
            @if($transfer->erp_sync_error)
            <div style="padding:8px 16px;background:#FCE8E6;font-size:12px;color:#B31412;border-bottom:1px solid var(--border)">
                <i class="fas fa-exclamation-circle"></i> ERP Error: {{ $transfer->erp_sync_error }}
            </div>
            @endif

            @if($transfer->erp_stock_entry || $transfer->erp_source_entry)
            <div style="padding:8px 16px;background:var(--blue-light);font-size:12px;color:var(--blue-dark);border-bottom:1px solid var(--border)">
                @if($transfer->erp_stock_entry)
                    <i class="fas fa-link"></i> Stock Entry: <strong>{{ $transfer->erp_stock_entry }}</strong>
                @endif
                @if($transfer->erp_source_entry)
                    &nbsp;|&nbsp; Ref: <strong>{{ $transfer->erp_source_entry }}</strong>
                @endif
            </div>
            @endif

            @if($transfer->notes)
            <div style="padding:8px 16px;font-size:12px;color:var(--text2);border-bottom:1px solid var(--border);background:#FAFAFA">
                <i class="fas fa-sticky-note" style="margin-right:4px"></i>{{ $transfer->notes }}
            </div>
            @endif

            <div style="overflow-x:auto;">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kode Item</th>
                            <th>Nama Item</th>
                            <th>SKU Lokal</th>
                            <th style="text-align:right">Qty Kirim</th>
                            @if(!$isOut)
                            <th style="text-align:right">Qty Diterima</th>
                            <th style="text-align:right">Selisih</th>
                            @endif
                            <th>Satuan</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfer->items as $idx => $item)
                        @php
                            $diff = null;
                            $diffClass = '';
                            if (!$isOut && $item->actual_quantity !== null) {
                                $diff = $item->actual_quantity - $item->quantity;
                                $diffClass = $diff == 0 ? 'diff-ok' : ($diff > 0 ? 'diff-warn' : 'diff-miss');
                            }
                        @endphp
                        <tr>
                            <td class="text-muted text-sm">{{ $idx + 1 }}</td>
                            <td><span style="font-family:monospace;font-size:12px">{{ $item->item_code }}</span></td>
                            <td style="font-weight:500">{{ $item->item_name }}</td>
                            <td class="text-muted text-sm">{{ $item->sku ?: '—' }}</td>
                            <td style="text-align:right;font-weight:600">
                                {{ number_format($item->quantity, 2, ',', '.') }}
                            </td>
                            @if(!$isOut)
                            <td style="text-align:right;font-weight:600;color:var(--green)">
                                {{ $item->actual_quantity !== null
                                    ? number_format($item->actual_quantity, 2, ',', '.')
                                    : '—' }}
                            </td>
                            <td style="text-align:right" class="{{ $diffClass }}">
                                @if($diff !== null)
                                    {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            @endif
                            <td class="text-sm">{{ $item->unit }}</td>
                            <td class="text-muted text-sm">{{ $item->notes ?: '—' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" style="text-align:center;padding:16px;color:var(--text3)">
                                Tidak ada item
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="background:var(--surface2);font-weight:700;">
                            <td colspan="{{ $isOut ? 4 : 4 }}" style="padding:8px 16px;font-size:12px;">
                                Total — {{ $transfer->items->count() }} jenis item
                            </td>
                            <td style="text-align:right;padding:8px 16px;color:var(--blue)">
                                {{ number_format($transfer->items->sum('quantity'), 2, ',', '.') }}
                            </td>
                            @if(!$isOut)
                            <td style="text-align:right;padding:8px 16px;color:var(--green)">
                                {{ number_format($transfer->items->sum('actual_quantity'), 2, ',', '.') }}
                            </td>
                            <td colspan="3"></td>
                            @else
                            <td colspan="2"></td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    @endforeach
@endif

@endsection

@push('scripts')
<script>
function toggleGroup(head) {
    head.classList.toggle('open');
    head.nextElementSibling.classList.toggle('open');
}

function expandAll() {
    document.querySelectorAll('.transfer-head').forEach(h => h.classList.add('open'));
    document.querySelectorAll('.transfer-body').forEach(b => b.classList.add('open'));
}
</script>
@endpush
