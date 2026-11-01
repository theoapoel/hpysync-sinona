@extends('layouts.app')
@section('title', 'Buat Permintaan Finish Goods')

@push('styles')
<style>
.item-row { animation: fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }
.item-grid { display:grid; grid-template-columns:2fr 90px 90px 1fr 28px; gap:8px; align-items:center; margin-bottom:8px; }

.product-search-wrap { position:relative; }
.product-dropdown {
    display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; z-index:20;
    background:var(--surface); border:1px solid var(--border); border-radius:8px;
    max-height:260px; overflow-y:auto; box-shadow:0 8px 24px rgba(0,0,0,.12);
}
.product-dropdown.open { display:block; }
.product-dropdown-item { padding:8px 12px; cursor:pointer; font-size:13px; }
.product-dropdown-item:hover, .product-dropdown-item.active { background:var(--surface2); }
.product-dropdown-item .pname { font-weight:500; color:var(--text); }
.product-dropdown-item .pmeta { font-size:11px; color:var(--text3); margin-top:1px; }
.product-dropdown-empty { padding:10px 12px; font-size:13px; color:var(--text3); text-align:center; }
.item-product-search.invalid { border-color:var(--red); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-plus text-blue"></i> Permintaan Finish Goods</div>
        <div class="page-subtitle">Ajukan permintaan produksi produk jadi untuk kebutuhan stok</div>
    </div>
    <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<form id="srForm" method="POST" action="{{ route('stock-requests.store') }}">
@csrf
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

    <div>
        {{-- Info --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fas fa-info-circle text-blue"></i> Informasi Permintaan</div></div>
            <div class="card-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Dibutuhkan <span style="color:var(--red)">*</span></label>
                        <input type="date" name="needed_date" class="form-control" required
                            min="{{ now()->format('Y-m-d') }}" value="{{ old('needed_date', now()->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jam Dibutuhkan</label>
                        <input type="time" name="needed_time" class="form-control"
                            value="{{ old('needed_time') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="notes" class="form-control"
                            placeholder="Keterangan tambahan (opsional)" value="{{ old('notes') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-box text-blue"></i> Produk yang Diminta</div>
                <button type="button" onclick="addItem()" class="btn btn-outline btn-sm">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
            </div>
            <div class="card-body" style="padding:0">
                <div class="item-grid" style="padding:10px 16px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
                    <span>Produk / Finish Goods</span><span>Qty</span><span>Satuan</span><span>Catatan</span><span></span>
                </div>
                <div id="itemsContainer" style="padding:12px 16px"></div>
            </div>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div style="position:sticky;top:80px">
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fas fa-info-circle text-blue"></i> Ringkasan</div></div>
            <div class="card-body">
                <div id="summaryContent" style="font-size:13px;color:var(--text3)">
                    Tambahkan produk terlebih dahulu.
                </div>
            </div>
        </div>
        <div class="alert alert-info" style="margin-bottom:12px;font-size:13px">
            <i class="fas fa-info-circle"></i>
            Setelah disimpan, klik <strong>Ajukan Sekarang</strong> untuk mengirim ke dapur dan sinkronisasi ke ERP sebagai Material Request.
        </div>
        <button type="submit" class="btn btn-primary w-full btn-lg" style="border-radius:10px">
            <i class="fas fa-save"></i> Simpan sebagai Draft
        </button>
    </div>
</div>
</form>

<script id="productData" type="application/json">@json($products)</script>
@endsection

@push('scripts')
<script>
const products = JSON.parse(document.getElementById('productData').textContent);
let itemCount  = 0;

const uomOptions = ['Nos','Pcs','Box','Pack','Kg','Gram','Liter','ml','Lusin','Botol','Kaleng','Karung','Sak']
    .map(u => `<option value="${u}">${u}</option>`).join('');

function addItem(pid = '', qty = 1, uom = 'Nos', notes = '') {
    const idx  = itemCount++;
    const selectedProduct = pid ? products.find(p => p.id == pid) : null;

    const row = document.createElement('div');
    row.className = 'item-row item-grid';
    row.innerHTML = `
        <div class="product-search-wrap">
            <input type="text" class="form-control item-product-search" style="font-size:13px"
                placeholder="Ketik nama / SKU produk..." autocomplete="off"
                value="${selectedProduct ? selectedProduct.name : ''}"
                oninput="onProductSearchInput(this,${idx})"
                onfocus="onProductSearchInput(this,${idx})"
                onblur="setTimeout(() => closeProductDropdown(${idx}), 150)">
            <input type="hidden" name="items[${idx}][product_id]" class="item-product-id" value="${pid}">
            <input type="hidden" name="items[${idx}][item_name]" class="item-name" value="${selectedProduct ? selectedProduct.name : ''}">
            <div class="product-dropdown" id="productDropdown${idx}"></div>
        </div>
        <input type="number" name="items[${idx}][qty]" class="form-control item-qty" value="${qty}"
            min="0.01" step="0.01" style="text-align:right;font-size:13px" required oninput="updateSummary()">
        <select name="items[${idx}][uom]" class="form-control form-select item-uom" style="font-size:13px">
            ${uomOptions.replace(`>${uom}<`, ` selected>${uom}<`)}
        </select>
        <input type="text" name="items[${idx}][notes]" class="form-control" value="${notes}"
            placeholder="Catatan..." style="font-size:13px">
        <button type="button" onclick="this.closest('.item-row').remove(); updateSummary()"
            style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.getElementById('itemsContainer').appendChild(row);
    if (pid) updateSummary();
}

function onProductSearchInput(input, idx) {
    const row  = input.closest('.item-row');
    const dropdown = document.getElementById(`productDropdown${idx}`);
    const q = input.value.trim().toLowerCase();

    // Mengetik ulang membatalkan pilihan sebelumnya sampai user memilih lagi dari dropdown
    row.querySelector('.item-product-id').value = '';
    input.classList.remove('invalid');

    const filtered = (q
        ? products.filter(p => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
        : products
    ).slice(0, 30);

    dropdown.innerHTML = filtered.length
        ? filtered.map(p => `
            <div class="product-dropdown-item" onmousedown="selectProduct(event,${idx},${p.id})">
                <div class="pname">${p.name}</div>
                <div class="pmeta">${p.sku ? 'SKU: ' + p.sku + ' · ' : ''}${p.unit || 'Nos'}</div>
            </div>`).join('')
        : '<div class="product-dropdown-empty">Produk tidak ditemukan</div>';

    dropdown.classList.add('open');
}

function selectProduct(e, idx, pid) {
    e.preventDefault();
    const product = products.find(p => p.id == pid);
    if (!product) return;

    const row = document.querySelector(`#productDropdown${idx}`).closest('.item-row');
    row.querySelector('.item-product-search').value = product.name;
    row.querySelector('.item-product-search').classList.remove('invalid');
    row.querySelector('.item-product-id').value = product.id;
    row.querySelector('.item-name').value = product.name;

    const uomSel = row.querySelector('.item-uom');
    const targetUom = product.unit || 'Nos';
    for (let o of uomSel.options) {
        if (o.value === targetUom) { o.selected = true; break; }
    }

    closeProductDropdown(idx);
    updateSummary();
}

function closeProductDropdown(idx) {
    document.getElementById(`productDropdown${idx}`)?.classList.remove('open');
}

function updateSummary() {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length === 0) {
        document.getElementById('summaryContent').innerHTML =
            '<span style="color:var(--text3)">Tambahkan produk terlebih dahulu.</span>';
        return;
    }
    let html = '';
    rows.forEach(row => {
        const pid  = row.querySelector('.item-product-id')?.value;
        const name = row.querySelector('.item-name')?.value || '—';
        const qty  = row.querySelector('.item-qty')?.value || '0';
        const uom  = row.querySelector('.item-uom')?.value || '';
        if (pid) {
            html += `<div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border)">
                <span style="color:var(--text2)">${name}</span>
                <span class="font-medium">${parseFloat(qty).toLocaleString('id-ID')} ${uom}</span>
            </div>`;
        }
    });
    const filled = rows.length;
    document.getElementById('summaryContent').innerHTML =
        `<div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">${filled} Produk</div>` +
        (html || '<span style="color:var(--text3)">Pilih produk terlebih dahulu.</span>');
}

document.getElementById('srForm').addEventListener('submit', function (e) {
    let firstInvalid = null;
    document.querySelectorAll('.item-row').forEach(row => {
        const search = row.querySelector('.item-product-search');
        const pid    = row.querySelector('.item-product-id');
        if (!pid.value) {
            search.classList.add('invalid');
            if (!firstInvalid) firstInvalid = search;
        }
    });
    if (firstInvalid) {
        e.preventDefault();
        toast('Pilih produk yang valid dari daftar pencarian untuk semua baris.', 'error');
        firstInvalid.focus();
    }
});

addItem();
</script>
@endpush
