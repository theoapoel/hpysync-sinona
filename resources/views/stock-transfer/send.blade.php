@extends('layouts.app')

@section('title', 'Kirim Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px;" class="text-blue">
                <path d="M12 19V5M5 12l7-7 7 7"/>
            </svg>
            Kirim Barang
        </h1>
        <p class="page-subtitle">Buat Material Transfer ke gudang in-transit di ERP HPY</p>
    </div>
    <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">← Kembali</a>
</div>

@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

@if(count($warehouses) === 0)
    <div class="alert alert-warning mb-3">
        <strong>Perhatian:</strong> Belum ada warehouse yang diaktifkan. Silakan pull dan aktifkan warehouse di <a href="{{ route('warehouses.index') }}">menu Warehouse</a>, lalu kembali ke halaman ini.
    </div>
@endif

<form method="POST" action="{{ route('stock-transfer.send.store') }}" id="sendForm">
    @csrf
    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

        {{-- LEFT: Main form --}}
        <div>
            {{-- Warehouse --}}
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">Informasi Pengiriman</span></div>
                <div class="card-body">
                    <div class="grid-2 gap-3">
                        <div class="form-group">
                            <label class="form-label">Gudang Asal <span style="color:var(--red)">*</span></label>
                            @if(count($warehouses))
                                <select name="from_warehouse" class="form-select" required>
                                    <option value="">-- Pilih Gudang Asal --</option>
                                    @foreach($warehouses as $wh)
                                        @if(!$wh['is_group'])
                                            <option value="{{ $wh['name'] }}" @selected(old('from_warehouse')===$wh['name'])>
                                                {{ $wh['warehouse_name'] }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            @else
                                <input type="text" name="from_warehouse" class="form-control"
                                    value="{{ old('from_warehouse') }}" placeholder="Nama gudang asal" required>
                            @endif
                            @error('from_warehouse')<p class="text-red" style="font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gudang Tujuan (In-Transit) <span style="color:var(--red)">*</span></label>
                            @if(count($warehouses))
                                <select name="to_warehouse" class="form-select" required>
                                    <option value="">-- Pilih Gudang Tujuan --</option>
                                    @foreach($warehouses as $wh)
                                        @if(!$wh['is_group'])
                                            <option value="{{ $wh['name'] }}" @selected(old('to_warehouse')===$wh['name'])>
                                                {{ $wh['warehouse_name'] }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            @else
                                <input type="text" name="to_warehouse" class="form-control"
                                    value="{{ old('to_warehouse') }}" placeholder="Nama gudang in-transit" required>
                            @endif
                            @error('to_warehouse')<p class="text-red" style="font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:12px;">
                        <label class="form-label">Keterangan</label>
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Catatan pengiriman (opsional)">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="card-title">Daftar Barang</span>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addRow()">+ Tambah Baris</button>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table id="itemTable" style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--bg);border-bottom:1px solid var(--border);">
                                    <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600;">Kode Item</th>
                                    <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600;">Nama Barang</th>
                                    <th style="padding:10px 12px;text-align:right;font-size:12px;color:var(--text2);font-weight:600;width:100px;">Qty</th>
                                    <th style="padding:10px 12px;text-align:left;font-size:12px;color:var(--text2);font-weight:600;width:90px;">Satuan</th>
                                    <th style="padding:10px 12px;width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemBody">
                                {{-- rows injected by JS --}}
                            </tbody>
                        </table>
                    </div>
                    <div id="emptyRow" style="text-align:center;padding:32px;color:var(--text2);font-size:14px;display:none;">
                        Belum ada barang. Klik "+ Tambah Baris" untuk menambahkan.
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Product search sidebar --}}
        <div>
            <div class="card" style="position:sticky;top:80px;">
                <div class="card-header"><span class="card-title">Cari Produk Lokal</span></div>
                <div class="card-body">
                    <input type="text" id="productSearch" class="form-control" placeholder="Nama / SKU / Barcode..."
                        style="margin-bottom:10px;">
                    <div id="productResults" style="max-height:400px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary" id="submitBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;">
                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
            </svg>
            Kirim ke ERP HPY
        </button>
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">Batal</a>
    </div>
</form>

<style>
.item-row td { padding:8px 12px;border-bottom:1px solid var(--border); }
.item-row input { background:transparent;border:none;outline:none;width:100%;font-size:14px;color:var(--text); }
.item-row input:focus { background:var(--bg);border-radius:4px;padding:2px 4px; }
.product-card { padding:10px;border:1px solid var(--border);border-radius:6px;margin-bottom:6px;cursor:pointer;transition:.15s; }
.product-card:hover { border-color:var(--blue);background:var(--bg); }
.product-card .name { font-size:13px;font-weight:600;color:var(--text); }
.product-card .meta { font-size:11px;color:var(--text2);margin-top:2px; }
</style>

<script>
const products = @json($productData);

let rowIdx = 0;

function addRow(item = null) {
    const tbody = document.getElementById('itemBody');
    const idx   = rowIdx++;
    const tr    = document.createElement('tr');
    tr.className = 'item-row';
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td><input name="items[${idx}][item_code]" value="${item?.item_code ?? ''}" placeholder="ITEM-001" required></td>
        <td><input name="items[${idx}][item_name]" value="${item?.name ?? ''}" placeholder="Nama barang" required></td>
        <td style="text-align:right;"><input name="items[${idx}][quantity]" type="number" step="0.001" min="0.001" value="1"
            style="text-align:right;" required></td>
        <td><input name="items[${idx}][unit]" value="${item?.unit ?? 'Nos'}" placeholder="Nos"></td>
        <td style="text-align:center;">
            <button type="button" onclick="removeRow(this)" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:18px;line-height:1;">×</button>
        </td>`;
    tbody.appendChild(tr);
    checkEmpty();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    checkEmpty();
}

function checkEmpty() {
    const tbody = document.getElementById('itemBody');
    document.getElementById('emptyRow').style.display =
        tbody.querySelectorAll('tr').length === 0 ? '' : 'none';
}

// Product search
let searchTimer;
document.getElementById('productSearch').addEventListener('input', function () {
    clearTimeout(searchTimer);
    const q = this.value.trim().toLowerCase();
    searchTimer = setTimeout(() => renderProducts(q), 200);
});

function renderProducts(q) {
    const box = document.getElementById('productResults');
    const filtered = q
        ? products.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.sku && p.sku.toLowerCase().includes(q)) ||
            (p.item_code && p.item_code.toLowerCase().includes(q))
          )
        : products.slice(0, 20);

    if (!filtered.length) {
        box.innerHTML = '<p style="color:var(--text2);font-size:13px;text-align:center;padding:16px;">Tidak ditemukan</p>';
        return;
    }
    box.innerHTML = filtered.slice(0, 30).map(p => `
        <div class="product-card" onclick='addRow(${JSON.stringify(p)})'>
            <div class="name">${p.name}</div>
            <div class="meta">SKU: ${p.sku ?? '—'} · ${p.unit ?? 'Nos'}</div>
        </div>`).join('');
}

// Form validation
document.getElementById('sendForm').addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('#itemBody tr');
    if (rows.length === 0) {
        e.preventDefault();
        toast('Tambahkan minimal 1 barang.', 'error');
        return;
    }
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML = '<span style="opacity:.7">Mengirim...</span>';
});

// Init
checkEmpty();
renderProducts('');
</script>
@endsection
