@extends('layouts.app')
@section('title', 'Stock Opname — ' . $stockOpname->opname_date->format('d/m/Y'))

@push('styles')
<style>
    .opname-table input[type=number] { width:90px; text-align:right; padding:4px 8px; border:1px solid var(--border); border-radius:6px; font-size:14px; background:var(--surface); color:var(--text); }
    .opname-table input[type=number]:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 3px rgba(66,133,244,.15); }
    .diff-plus  { color:var(--red);   font-weight:700; }
    .diff-minus { color:var(--green); font-weight:700; }
    .diff-zero  { color:var(--text3); }
    .save-indicator { font-size:12px; color:var(--text3); margin-left:8px; transition:color .3s; }
    .save-indicator.saving { color:var(--blue); }
    .save-indicator.saved  { color:var(--green); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px;"></i>
            Stock Opname — {{ $stockOpname->opname_date->format('d M Y') }}
        </h1>
        <p class="page-subtitle">
            {{ $stockOpname->warehouse?->warehouse_name ?: $stockOpname->warehouse?->name }}
            &nbsp;·&nbsp;
            Oleh: {{ $stockOpname->creator?->name }}
            &nbsp;·&nbsp;
            @if($stockOpname->status === 'draft')
                <span style="color:var(--yellow);font-weight:600;">Draft</span>
            @elseif($stockOpname->status === 'submitted')
                <span style="color:var(--green);font-weight:600;">Submitted</span>
            @else
                <span style="color:var(--red);font-weight:600;">Dibatalkan</span>
            @endif
        </p>
    </div>
    @if($stockOpname->status === 'draft')
    <div style="display:flex;gap:10px;">
        <form method="POST" action="{{ route('stock-opname.cancel', $stockOpname) }}"
            onsubmit="return confirm('Batalkan opname ini?')">
            @csrf
            <button type="submit" class="btn btn-ghost" style="color:var(--red);">
                <i class="fas fa-times"></i> Batalkan
            </button>
        </form>
        <form method="POST" action="{{ route('stock-opname.submit', $stockOpname) }}"
            onsubmit="return confirm('Submit opname dan kirim ke ERP HPY? Pastikan semua qty aktual sudah benar.')">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Submit & Sync ke ERP
            </button>
        </form>
    </div>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:20px;padding:14px 18px;background:#e6f4ea;border-radius:10px;color:#137333;font-size:14px;">
    <i class="fas fa-check-circle"></i> {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="alert alert-error" style="margin-bottom:20px;padding:14px 18px;background:#fce8e6;border-radius:10px;color:#c5221f;font-size:14px;">
    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
</div>
@endif

{{-- ERP Result --}}
@if($stockOpname->erp_entry_issue || $stockOpname->erp_entry_receipt)
<div class="card" style="margin-bottom:20px;padding:16px 20px;display:flex;gap:24px;flex-wrap:wrap;">
    @if($stockOpname->erp_entry_issue)
    <div>
        <div style="font-size:12px;color:var(--text2);margin-bottom:4px;">Material Issue (Aktual Kurang)</div>
        <code style="font-size:13px;color:var(--red);">{{ $stockOpname->erp_entry_issue }}</code>
    </div>
    @endif
    @if($stockOpname->erp_entry_receipt)
    <div>
        <div style="font-size:12px;color:var(--text2);margin-bottom:4px;">Material Receipt (Aktual Berlebih)</div>
        <code style="font-size:13px;color:var(--green);">{{ $stockOpname->erp_entry_receipt }}</code>
    </div>
    @endif
</div>
@endif

{{-- Summary --}}
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
        <div><div class="stat-value">{{ number_format($summary['total']) }}</div><div class="stat-label">Total Item</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-arrow-up"></i></div>
        <div><div class="stat-value">{{ number_format($summary['lebih']) }}</div><div class="stat-label">Berlebih → Receipt</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-arrow-down"></i></div>
        <div><div class="stat-value">{{ number_format($summary['kurang']) }}</div><div class="stat-label">Kurang → Issue</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:var(--surface2);"><i class="fas fa-equals" style="color:var(--text2);"></i></div>
        <div><div class="stat-value">{{ number_format($summary['sama']) }}</div><div class="stat-label">Sama</div></div>
    </div>
</div>

{{-- Tabel Item --}}
<div class="card">
    <div class="card-header" style="padding:14px 20px;display:flex;align-items:center;gap:12px;">
        <span style="font-weight:600;font-size:14px;">Daftar Item</span>
        @if($stockOpname->status === 'draft')
            <span class="save-indicator" id="saveIndicator">— perubahan disimpan otomatis</span>
        @endif
    </div>
    <div class="table-wrap">
        <table class="opname-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>SKU</th>
                    <th>Kategori</th>
                    <th style="text-align:right;">Stok Sistem</th>
                    <th style="text-align:right;">Aktual</th>
                    <th style="text-align:right;">Selisih</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody id="opnameBody">
                @forelse($items as $item)
                @php
                    $diff = $item->difference;
                    $diffClass = $diff > 0 ? 'diff-plus' : ($diff < 0 ? 'diff-minus' : 'diff-zero');
                    $diffLabel = $diff > 0 ? 'Berlebih → Issue' : ($diff < 0 ? 'Kurang → Receipt' : 'Sama');
                @endphp
                <tr data-item-id="{{ $item->id }}">
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            @if($item->product->image || $item->product->erp_image)
                                <img src="{{ $item->product->image ? asset($item->product->image) : $item->product->erp_image }}"
                                    style="width:28px;height:28px;border-radius:5px;object-fit:cover;border:1px solid var(--border);flex-shrink:0;">
                            @else
                                <div style="width:28px;height:28px;border-radius:5px;background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-box" style="font-size:11px;color:var(--text3);"></i>
                                </div>
                            @endif
                            <span style="font-weight:500;font-size:14px;">{{ $item->product->name }}</span>
                        </div>
                    </td>
                    <td><code style="font-size:11px;color:var(--text2);">{{ $item->product->sku }}</code></td>
                    <td style="font-size:13px;color:var(--text2);">{{ $item->product->category?->name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:600;">{{ number_format($item->system_qty) }}</td>
                    <td style="text-align:right;">
                        @if($stockOpname->status === 'draft')
                            <input type="number" min="0"
                                class="actual-input"
                                value="{{ $item->actual_qty }}"
                                data-id="{{ $item->id }}"
                                data-system="{{ $item->system_qty }}">
                        @else
                            <span style="font-weight:600;">{{ number_format($item->actual_qty) }}</span>
                        @endif
                    </td>
                    <td style="text-align:right;" class="diff-cell {{ $diffClass }}">
                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                    </td>
                    <td style="font-size:13px;" class="label-cell">
                        @if($diff > 0)
                            <span style="color:var(--green);"><i class="fas fa-arrow-up" style="font-size:10px;"></i> Berlebih → Receipt</span>
                        @elseif($diff < 0)
                            <span style="color:var(--red);"><i class="fas fa-arrow-down" style="font-size:10px;"></i> Kurang → Issue</span>
                        @else
                            <span style="color:var(--text3);">Sama</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px;color:var(--text2);">
                        Tidak ada item di opname ini
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($items->hasPages())
    <div style="padding:14px 20px;border-top:1px solid var(--border);">
        {{ $items->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
@if($stockOpname->status === 'draft')
<script>
const updateUrl  = '{{ route('stock-opname.update-items', $stockOpname) }}';
const indicator  = document.getElementById('saveIndicator');
let saveTimer    = null;
let pendingItems = {};

function markSaving() {
    indicator.textContent = 'Menyimpan...';
    indicator.className = 'save-indicator saving';
}

function markSaved() {
    indicator.textContent = 'Tersimpan ✓';
    indicator.className = 'save-indicator saved';
    setTimeout(() => {
        indicator.textContent = '— perubahan disimpan otomatis';
        indicator.className = 'save-indicator';
    }, 2000);
}

function updateDiffCell(row, actualQty, systemQty) {
    const diff      = actualQty - systemQty;
    const diffCell  = row.querySelector('.diff-cell');
    const labelCell = row.querySelector('.label-cell');

    diffCell.textContent = (diff > 0 ? '+' : '') + diff.toLocaleString('id-ID');
    diffCell.className   = 'diff-cell ' + (diff > 0 ? 'diff-plus' : diff < 0 ? 'diff-minus' : 'diff-zero');

    if (diff > 0) {
        labelCell.innerHTML = '<span style="color:var(--green);"><i class="fas fa-arrow-up" style="font-size:10px;"></i> Berlebih → Receipt</span>';
    } else if (diff < 0) {
        labelCell.innerHTML = '<span style="color:var(--red);"><i class="fas fa-arrow-down" style="font-size:10px;"></i> Kurang → Issue</span>';
    } else {
        labelCell.innerHTML = '<span style="color:var(--text3);">Sama</span>';
    }
}

document.querySelectorAll('.actual-input').forEach(input => {
    input.addEventListener('input', function () {
        const id        = this.dataset.id;
        const systemQty = parseInt(this.dataset.system);
        const actualQty = parseInt(this.value) || 0;

        pendingItems[id] = { id, actual_qty: actualQty };
        updateDiffCell(this.closest('tr'), actualQty, systemQty);

        clearTimeout(saveTimer);
        markSaving();
        saveTimer = setTimeout(flushSave, 800);
    });
});

async function flushSave() {
    const items = Object.values(pendingItems);
    if (!items.length) return;
    pendingItems = {};

    try {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        await fetch(updateUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ items }),
        });
        markSaved();
    } catch (e) {
        indicator.textContent = 'Gagal menyimpan!';
        indicator.className = 'save-indicator';
        indicator.style.color = 'var(--red)';
    }
}
</script>
@endif
@endpush
