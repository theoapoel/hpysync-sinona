@extends('layouts.app')

@section('title', 'Stok Barang')

@push('styles')
<style>
    .page-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; font-size: 13px; font-weight: 500; color: var(--text2); text-decoration: none; transition: all .2s; }
    .page-btn:hover:not(.disabled) { background: var(--surface2); color: var(--text); }
    .page-btn.active { background: var(--blue); color: #fff; }
    .page-btn.disabled { color: var(--border); cursor: default; }
    .warehouse-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
    .warehouse-tab { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; border: 1px solid var(--border); color: var(--text2); transition: all .2s; }
    .warehouse-tab:hover { border-color: var(--blue); color: var(--blue); }
    .warehouse-tab.active { background: var(--blue); color: #fff; border-color: var(--blue); }
    .filter-tabs-group { margin-bottom: 12px; }
    .filter-tabs-label { font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }

    /* ── Sync Modal ── */
    #syncModal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,.5); align-items:center; justify-content:center; }
    #syncModalBox { background:var(--surface); border-radius:16px; width:520px; max-width:calc(100vw - 32px); box-shadow:0 8px 40px rgba(0,0,0,.3); overflow:hidden; }
    #syncModalHeader { padding:20px 24px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px; }
    #syncModalHeader h3 { margin:0; font-size:16px; font-weight:600; color:var(--text); flex:1; }
    #syncProgressBar { height:4px; background:var(--surface2); }
    #syncProgressFill { height:4px; background:var(--blue); width:0%; transition:width .4s ease; }
    #syncLog { padding:16px 24px; max-height:320px; overflow-y:auto; display:flex; flex-direction:column; gap:8px; }
    .sync-log-row { display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border-radius:10px; background:var(--surface2); font-size:13px; }
    .sync-log-row.running { border-left:3px solid var(--blue); }
    .sync-log-row.done    { border-left:3px solid var(--green); }
    .sync-log-row.error   { border-left:3px solid var(--red); }
    .sync-log-icon { margin-top:1px; width:16px; text-align:center; flex-shrink:0; }
    .sync-log-body { flex:1; }
    .sync-log-title { font-weight:600; color:var(--text); }
    .sync-log-detail { color:var(--text2); margin-top:2px; line-height:1.5; }
    #syncModalFooter { padding:14px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
    #syncStatus { padding:0 24px 12px; font-size:13px; color:var(--text2); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-boxes" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px;"></i>
            Stok Barang
        </h1>
        <p class="page-subtitle">
            Pantau stok per gudang ·
            @if($selectedWarehouse)
                <strong>{{ $selectedWarehouse->warehouse_name ?: $selectedWarehouse->name }}</strong>
            @else
                <span style="color:var(--red);">Belum ada warehouse aktif</span>
            @endif
        </p>
    </div>
    <div>
        <button id="btnSyncBin" class="btn btn-outline" onclick="syncFromBin()">
            <i class="fas fa-sync-alt" id="syncBinIcon"></i>
            Sync Stok dari ERP HPY
        </button>
    </div>
</div>

{{-- Warehouse Tabs --}}
@if($warehouses->count() > 1)
<div class="filter-tabs-group">
    <div class="filter-tabs-label">Gudang</div>
    <div class="warehouse-tabs">
        @foreach($warehouses as $wh)
            @php
                $tabUrl = request()->fullUrlWithQuery(['warehouse_id' => $wh->id, 'page' => null]);
            @endphp
            <a href="{{ $tabUrl }}" class="warehouse-tab {{ $wh->id == $selectedWarehouseId ? 'active' : '' }}">
                <i class="fas fa-warehouse" style="margin-right:4px;font-size:11px;"></i>
                {{ $wh->warehouse_name ?: $wh->name }}
                @if($wh->is_default) <span style="font-size:10px;opacity:.8;">(default)</span> @endif
            </a>
        @endforeach
    </div>
</div>
@endif

{{-- Kategori Tabs (sumber: field kategori di ERP) --}}
@if($itemCategories->count() > 0)
<div class="filter-tabs-group" style="margin-bottom:20px;">
    <div class="filter-tabs-label">Kategori</div>
    <div class="warehouse-tabs">
        <a href="{{ request()->fullUrlWithQuery(['item_category_id' => null, 'page' => null]) }}"
            class="warehouse-tab {{ !request('item_category_id') ? 'active' : '' }}">
            <i class="fas fa-layer-group" style="margin-right:4px;font-size:11px;"></i> Semua
        </a>
        @foreach($itemCategories as $cat)
            <a href="{{ request()->fullUrlWithQuery(['item_category_id' => $cat->id, 'page' => null]) }}"
                class="warehouse-tab {{ request('item_category_id') == $cat->id ? 'active' : '' }}">
                {{ $cat->name }}
            </a>
        @endforeach
    </div>
</div>
@endif

{{-- Summary Cards --}}
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
        <div>
            <div class="stat-value">{{ number_format($totalProducts) }}</div>
            <div class="stat-label">Total Produk</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value">{{ number_format($totalSafe) }}</div>
            <div class="stat-label">Stok Aman</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-value">{{ number_format($totalLow) }}</div>
            <div class="stat-label">Stok Rendah</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-value">{{ number_format($totalEmpty) }}</div>
            <div class="stat-label">Stok Habis</div>
        </div>
    </div>
</div>

<div class="card">
    {{-- Filter --}}
    <div class="card-header" style="padding:16px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">
            <input type="hidden" name="item_category_id" value="{{ request('item_category_id') }}">
            <input type="text" name="search" value="{{ request('search') }}"
                class="form-control" placeholder="Cari nama / SKU..." style="width:240px;">
            <select name="status" class="form-control form-select" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="safe"  @selected(request('status')==='safe')>Aman</option>
                <option value="low"   @selected(request('status')==='low')>Stok Rendah</option>
                <option value="empty" @selected(request('status')==='empty')>Habis</option>
            </select>
            <button type="submit" class="btn btn-outline">Filter</button>
            @if(request()->hasAny(['search','item_category_id','status']))
                <a href="{{ route('stock.index', ['warehouse_id' => $selectedWarehouseId]) }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>

    {{-- Tabel --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>SKU</th>
                    <th>Item Group</th>
                    <th>Kategori</th>
                    <th style="text-align:right;">Stok</th>
                    <th style="text-align:right;">Min Stok</th>
                    <th>Satuan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stocks as $stock)
                @php
                    $product = $stock->product;
                    $isEmpty = $stock->quantity <= 0;
                    $isLow   = !$isEmpty && $stock->quantity <= $product->min_stock;
                @endphp
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if($product->image || $product->erp_image)
                                <img src="{{ $product->image ? asset($product->image) : $product->erp_image }}"
                                    style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid var(--border);">
                            @else
                                <div style="width:32px;height:32px;border-radius:6px;background:var(--surface2);display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-box" style="font-size:12px;color:var(--text3);"></i>
                                </div>
                            @endif
                            <span style="font-weight:500;">{{ $product->name }}</span>
                        </div>
                    </td>
                    <td><code style="font-size:12px;color:var(--text2);">{{ $product->sku }}</code></td>
                    <td>
                        @if($product->category)
                            <span style="font-size:13px;">{{ $product->category->name }}</span>
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($product->itemCategory)
                            <span class="badge badge-gray" style="font-size:12px;">{{ $product->itemCategory->name }}</span>
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <span style="font-family:'Google Sans',sans-serif;font-size:16px;font-weight:700;
                            color:{{ $isEmpty ? 'var(--red)' : ($isLow ? '#E37400' : 'var(--text)') }};">
                            {{ number_format($stock->quantity, 0, ',', '.') }}
                        </span>
                    </td>
                    <td style="text-align:right;color:var(--text2);font-size:13px;">
                        {{ number_format($product->min_stock, 0, ',', '.') }}
                    </td>
                    <td style="color:var(--text2);font-size:13px;">{{ $product->unit }}</td>
                    <td>
                        @if($isEmpty)
                            <span class="badge badge-red">Habis</span>
                        @elseif($isLow)
                            <span class="badge badge-yellow">Rendah</span>
                        @else
                            <span class="badge badge-green">Aman</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:48px;color:var(--text2);">
                        @if(!$selectedWarehouse)
                            Belum ada warehouse yang dikonfigurasi
                        @else
                            Tidak ada produk yang sesuai filter di gudang ini
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($stocks->hasPages())
    <div class="pagination-wrap">
        <span style="color:var(--text2);font-size:13px;">
            Menampilkan {{ $stocks->firstItem() }}–{{ $stocks->lastItem() }} dari {{ $stocks->total() }}
        </span>
        <div class="pagination">
            @if($stocks->onFirstPage())
                <span class="page-btn disabled">‹</span>
            @else
                <a href="{{ $stocks->previousPageUrl() }}" class="page-btn">‹</a>
            @endif
            @foreach($stocks->getUrlRange(max(1,$stocks->currentPage()-2), min($stocks->lastPage(),$stocks->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}" class="page-btn {{ $page == $stocks->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endforeach
            @if($stocks->hasMorePages())
                <a href="{{ $stocks->nextPageUrl() }}" class="page-btn">›</a>
            @else
                <span class="page-btn disabled">›</span>
            @endif
        </div>
    </div>
    @endif
</div>
{{-- Sync Progress Modal --}}
<div id="syncModal">
    <div id="syncModalBox">
        <div id="syncModalHeader">
            <i class="fas fa-sync-alt fa-spin" id="syncModalIcon" style="color:var(--blue);font-size:18px;"></i>
            <h3>Sinkronisasi Stok dari ERP HPY</h3>
        </div>
        <div id="syncProgressBar"><div id="syncProgressFill"></div></div>
        <div id="syncStatus">Mempersiapkan...</div>
        <div id="syncLog"></div>
        <div id="syncModalFooter">
            <button id="btnSyncClose" class="btn btn-outline" style="display:none;" onclick="closeSyncModal()">Tutup</button>
            <button id="btnSyncReload" class="btn btn-primary" style="display:none;" onclick="location.reload()">Muat Ulang Halaman</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const WAREHOUSES = @json($warehouses->map(fn($w) => ['id' => $w->id, 'label' => $w->warehouse_name ?: $w->name, 'is_default' => $w->is_default])->values());

const syncRouteBase = '{{ url('stock/sync-warehouse') }}';

function openSyncModal() {
    // Pindahkan ke <body> agar tidak terkurung di dalam <main>
    const m = document.getElementById('syncModal');
    if (m.parentNode !== document.body) document.body.appendChild(m);
    m.style.display = 'flex';
    document.getElementById('syncLog').innerHTML = '';
    document.getElementById('syncStatus').textContent = 'Mempersiapkan...';
    document.getElementById('syncProgressFill').style.width = '0%';
    document.getElementById('syncModalIcon').className = 'fas fa-sync-alt fa-spin';
    document.getElementById('syncModalIcon').style.color = 'var(--blue)';
    document.getElementById('btnSyncClose').style.display  = 'none';
    document.getElementById('btnSyncReload').style.display = 'none';
}

function closeSyncModal() {
    document.getElementById('syncModal').style.display = 'none';
}

function addLogRow(id, state, icon, title, detail) {
    const existing = document.getElementById('log-' + id);
    const html = `
        <div class="sync-log-row ${state}" id="log-${id}">
            <div class="sync-log-icon">${icon}</div>
            <div class="sync-log-body">
                <div class="sync-log-title">${title}</div>
                ${detail ? `<div class="sync-log-detail">${detail}</div>` : ''}
            </div>
        </div>`;
    if (existing) {
        existing.outerHTML = html;
    } else {
        document.getElementById('syncLog').insertAdjacentHTML('beforeend', html);
    }
    // auto scroll
    const log = document.getElementById('syncLog');
    log.scrollTop = log.scrollHeight;
}

function setProgress(current, total) {
    const pct = total > 0 ? Math.round((current / total) * 100) : 0;
    document.getElementById('syncProgressFill').style.width = pct + '%';
}

function setStatus(text) {
    document.getElementById('syncStatus').textContent = text;
}

async function syncFromBin() {
    openSyncModal();

    const btn  = document.getElementById('btnSyncBin');
    const icon = document.getElementById('syncBinIcon');
    if (btn)  btn.disabled = true;
    if (icon) icon.classList.add('fa-spin');

    const total = WAREHOUSES.length;
    let grandTotal = 0;
    let hasError = false;

    setStatus(`Memulai sync — ${total} gudang ditemukan...`);

    for (let i = 0; i < WAREHOUSES.length; i++) {
        const wh = WAREHOUSES[i];
        const rowId = 'wh-' + wh.id;

        addLogRow(rowId, 'running',
            '<i class="fas fa-circle-notch fa-spin" style="color:var(--blue)"></i>',
            wh.label + (wh.is_default ? ' <span style="color:var(--blue);font-size:11px;">(default)</span>' : ''),
            'Mengambil data dari ERP HPY...'
        );

        setStatus(`Memproses ${i + 1} dari ${total}: ${wh.label}`);
        setProgress(i, total);

        try {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const resp  = await fetch(`${syncRouteBase}/${wh.id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            });
            const res = await resp.json();

            if (res.success) {
                const removed = res.removed > 0 ? ` &nbsp;·&nbsp; Dihapus (tidak ada di ERP): <strong style="color:var(--red)">${res.removed}</strong>` : '';
                const detail = `Bin dari ERP: <strong>${res.bin_count}</strong> &nbsp;·&nbsp; Diperbarui: <strong>${res.updated}</strong> &nbsp;·&nbsp; Tidak cocok: <strong>${res.skipped}</strong>${removed} &nbsp;·&nbsp; Tersimpan di DB: <strong>${res.db_rows}</strong>`;
                addLogRow(rowId, 'done',
                    '<i class="fas fa-check-circle" style="color:var(--green)"></i>',
                    wh.label + (wh.is_default ? ' <span style="color:var(--blue);font-size:11px;">(default)</span>' : ''),
                    detail
                );
                grandTotal += res.updated;
            } else {
                addLogRow(rowId, 'error',
                    '<i class="fas fa-times-circle" style="color:var(--red)"></i>',
                    wh.label,
                    'Gagal: ' + res.error
                );
                hasError = true;
            }
        } catch (e) {
            addLogRow(rowId, 'error',
                '<i class="fas fa-times-circle" style="color:var(--red)"></i>',
                wh.label,
                'Error koneksi: ' + e.message
            );
            hasError = true;
        }
    }

    setProgress(total, total);
    document.getElementById('syncModalIcon').classList.remove('fa-spin');

    if (hasError) {
        setStatus(`Selesai dengan error — ${grandTotal} produk diperbarui`);
        document.getElementById('syncModalIcon').className = 'fas fa-exclamation-triangle';
        document.getElementById('syncModalIcon').style.color = 'var(--yellow)';
    } else {
        setStatus(`Sync selesai — ${grandTotal} produk diperbarui di ${total} gudang`);
        document.getElementById('syncModalIcon').className = 'fas fa-check-circle';
        document.getElementById('syncModalIcon').style.color = 'var(--green)';
    }

    document.getElementById('btnSyncClose').style.display  = 'inline-flex';
    document.getElementById('btnSyncReload').style.display = 'inline-flex';

    btn.disabled = false;
    icon.classList.remove('fa-spin');
}
</script>
@endpush
