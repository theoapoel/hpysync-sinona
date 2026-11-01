@extends('layouts.app')
@section('title', 'Buat Repack')

@push('styles')
<style>
.item-row { animation: fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

.issue-grid   { display:grid; grid-template-columns:1.8fr 90px 1.3fr 1fr 28px; gap:8px; align-items:center; margin-bottom:8px; }
.receipt-grid { display:grid; grid-template-columns:1.8fr 90px 1fr 28px; gap:8px; align-items:center; margin-bottom:8px; }
.grid-head { font-size:11px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; margin-bottom:0; }

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
.product-search.invalid, select.invalid { border-color:var(--red); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-scissors text-blue"></i> Buat Repack</div>
        <div class="page-subtitle">Tabel 1: item yang dibuang (issue) · Tabel 2: item hasil (receipt) — diproses sebagai Repack di ERP HPY</div>
    </div>
    <a href="{{ route('slices.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<form id="sliceForm" method="POST" action="{{ route('slices.store') }}">
@csrf
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

    <div>
        {{-- TABEL 1 — ISSUE --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-up-from-bracket text-red"></i> Item Dibuang (Issue)</div>
                <button type="button" onclick="addIssue()" class="btn btn-outline btn-sm">
                    <i class="fas fa-plus"></i> Tambah Baris
                </button>
            </div>
            <div class="card-body" style="padding:0">
                <div class="issue-grid grid-head" style="padding:10px 16px;background:var(--surface2)">
                    <span>Item</span><span>Qty</span><span>Gudang Asal</span><span>Catatan</span><span></span>
                </div>
                <div id="issuesContainer" style="padding:12px 16px"></div>
            </div>
        </div>

        {{-- TABEL 2 — RECEIPT --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-down-to-bracket text-green"></i> Jadi Item (Receipt)</div>
                <button type="button" onclick="addReceipt()" class="btn btn-outline btn-sm">
                    <i class="fas fa-plus"></i> Tambah Baris
                </button>
            </div>
            <div class="card-body" style="padding:0">
                <div class="receipt-grid grid-head" style="padding:10px 16px;background:var(--surface2)">
                    <span>Item</span><span>Qty</span><span>Catatan</span><span></span>
                </div>
                <div id="receiptsContainer" style="padding:12px 16px"></div>
            </div>
        </div>
    </div>

    {{-- Ringkasan --}}
    <div style="position:sticky;top:80px">
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fas fa-info-circle text-blue"></i> Ringkasan</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control"
                        placeholder="Keterangan (opsional)" value="{{ old('notes') }}">
                </div>
                <div id="summaryContent" style="font-size:13px;color:var(--text3)">
                    Lengkapi item issue &amp; receipt.
                </div>
            </div>
        </div>
        <div class="alert alert-info" style="margin-bottom:12px;font-size:13px">
            <i class="fas fa-info-circle"></i>
            Semua item di tabel <strong>Issue</strong> keluar stok; semua item di tabel <strong>Receipt</strong> masuk stok. Setelah disimpan, klik <strong>Submit ke ERP</strong>.
        </div>
        <button type="submit" class="btn btn-primary w-full btn-lg" style="border-radius:10px">
            <i class="fas fa-save"></i> Simpan sebagai Draft
        </button>
    </div>
</div>
</form>

<script id="productData" type="application/json">@json($products)</script>
<script id="warehouseData" type="application/json">@json($warehouses->map(fn($w) => ['name' => $w->name, 'label' => $w->display_name]))</script>
@endsection

@push('scripts')
<script>
const products   = JSON.parse(document.getElementById('productData').textContent);
const warehouses = JSON.parse(document.getElementById('warehouseData').textContent);
const defaultWh  = @json($defaultWarehouse);
let rowCount = 0;

function warehouseOptions() {
    const placeholder = `<option value="" disabled selected>— Pilih gudang —</option>`;
    return placeholder + warehouses.map(w => `<option value="${w.name}">${w.label || w.name}</option>`).join('');
}

// section = 'issue' | 'receipt'
function productField(id, section, nameAttr) {
    return `
        <div class="product-search-wrap">
            <input type="text" class="form-control ${section}-search product-search" style="font-size:13px"
                placeholder="Ketik nama / SKU..." autocomplete="off"
                oninput="onSearch(this,'${id}','${section}')"
                onfocus="onSearch(this,'${id}','${section}')"
                onblur="setTimeout(() => closeDropdown('${id}','${section}'), 150)">
            <input type="hidden" name="${nameAttr}" class="${section}-id" value="">
            <div class="product-dropdown" id="dropdown_${section}_${id}"></div>
        </div>`;
}

function addIssue() {
    const i = rowCount++;
    const row = document.createElement('div');
    row.className = 'item-row issue-grid';
    row.innerHTML = `
        ${productField('i'+i, 'issue', `issues[${i}][product_id]`)}
        <input type="number" name="issues[${i}][qty]" class="form-control issue-qty" value="1"
            min="0.01" step="0.01" style="text-align:right;font-size:13px" required oninput="updateSummary()">
        <select name="issues[${i}][warehouse]" class="form-control issue-warehouse" style="font-size:12px" required
            onchange="this.classList.remove('invalid'); updateSummary()">
            ${warehouseOptions()}
        </select>
        <input type="text" name="issues[${i}][notes]" class="form-control" placeholder="Catatan..." style="font-size:13px">
        <button type="button" onclick="this.closest('.item-row').remove(); updateSummary()"
            style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('issuesContainer').appendChild(row);
}

function addReceipt() {
    const i = rowCount++;
    const row = document.createElement('div');
    row.className = 'item-row receipt-grid';
    row.innerHTML = `
        ${productField('r'+i, 'receipt', `receipts[${i}][product_id]`)}
        <input type="number" name="receipts[${i}][qty]" class="form-control receipt-qty" value="1"
            min="0.01" step="0.01" style="text-align:right;font-size:13px" required oninput="updateSummary()">
        <input type="text" name="receipts[${i}][notes]" class="form-control" placeholder="Catatan..." style="font-size:13px">
        <button type="button" onclick="this.closest('.item-row').remove(); updateSummary()"
            style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('receiptsContainer').appendChild(row);
}

function onSearch(input, id, section) {
    const dropdown = document.getElementById(`dropdown_${section}_${id}`);
    const q = input.value.trim().toLowerCase();

    input.parentElement.querySelector(`.${section}-id`).value = '';
    input.classList.remove('invalid');

    const filtered = (q
        ? products.filter(p => p.name.toLowerCase().includes(q) || (p.sku && p.sku.toLowerCase().includes(q)))
        : products
    ).slice(0, 30);

    dropdown.innerHTML = filtered.length
        ? filtered.map(p => `
            <div class="product-dropdown-item" onmousedown="selectProduct(event,'${id}','${section}',${p.id})">
                <div class="pname">${p.name}</div>
                <div class="pmeta">${p.sku ? 'SKU: ' + p.sku + ' · ' : ''}${p.unit || 'Nos'}</div>
            </div>`).join('')
        : '<div class="product-dropdown-item" style="color:var(--text3);text-align:center">Produk tidak ditemukan</div>';

    dropdown.classList.add('open');
}

function selectProduct(e, id, section, pid) {
    e.preventDefault();
    const product = products.find(p => p.id == pid);
    if (!product) return;

    const wrap   = document.getElementById(`dropdown_${section}_${id}`).parentElement;
    const search = wrap.querySelector(`.${section}-search`);
    search.value = product.name;
    search.classList.remove('invalid');
    wrap.querySelector(`.${section}-id`).value = product.id;

    closeDropdown(id, section);
    updateSummary();
}

function closeDropdown(id, section) {
    document.getElementById(`dropdown_${section}_${id}`)?.classList.remove('open');
}

function collect(section) {
    const rows = document.querySelectorAll(`.${section}-grid.item-row`);
    const out = [];
    rows.forEach(row => {
        const id = row.querySelector(`.${section}-id`)?.value;
        if (!id) return;
        out.push({
            name: row.querySelector(`.${section}-search`)?.value || '—',
            qty: parseFloat(row.querySelector(`.${section}-qty`)?.value || '0').toLocaleString('id-ID'),
        });
    });
    return out;
}

function updateSummary() {
    const issues = collect('issue');
    const receipts = collect('receipt');
    if (!issues.length && !receipts.length) {
        document.getElementById('summaryContent').innerHTML =
            '<span style="color:var(--text3)">Lengkapi item issue &amp; receipt.</span>';
        return;
    }
    const list = (title, color, arr) => `
        <div style="font-size:11px;font-weight:700;color:${color};text-transform:uppercase;letter-spacing:.5px;margin:6px 0 4px">${title}</div>` +
        (arr.length ? arr.map(r => `<div style="font-size:13px;padding:3px 0">${r.qty} ${r.name}</div>`).join('')
                    : '<div style="font-size:12px;color:var(--text3)">—</div>');
    document.getElementById('summaryContent').innerHTML =
        list('Dibuang (Issue)', 'var(--red)', issues) +
        `<div style="text-align:center;color:var(--text3);margin:6px 0"><i class="fas fa-arrow-down"></i></div>` +
        list('Jadi (Receipt)', 'var(--green)', receipts);
}

document.getElementById('sliceForm').addEventListener('submit', function (e) {
    let firstInvalid = null;

    ['issue', 'receipt'].forEach(section => {
        const rows = document.querySelectorAll(`.${section}-grid.item-row`);
        rows.forEach(row => {
            const search = row.querySelector(`.${section}-search`);
            const id     = row.querySelector(`.${section}-id`);
            if (!id.value) { search.classList.add('invalid'); if (!firstInvalid) firstInvalid = search; }

            // Gudang wajib dipilih untuk setiap baris issue (yang itemnya sudah diisi).
            if (section === 'issue' && id.value) {
                const wh = row.querySelector('.issue-warehouse');
                if (!wh.value) { wh.classList.add('invalid'); if (!firstInvalid) firstInvalid = wh; }
            }
        });
    });

    const issueValid   = [...document.querySelectorAll('.issue-grid.item-row .issue-id')].some(el => el.value);
    const receiptValid = [...document.querySelectorAll('.receipt-grid.item-row .receipt-id')].some(el => el.value);

    if (!issueValid || !receiptValid) {
        e.preventDefault();
        toast('Minimal 1 item issue dan 1 item receipt harus valid.', 'error');
        if (firstInvalid) firstInvalid.focus();
        return;
    }
    if (firstInvalid) {
        e.preventDefault();
        toast('Lengkapi item yang valid dan pilih gudang untuk setiap baris issue.', 'error');
        firstInvalid.focus();
    }
});

addIssue();
addReceipt();
</script>
@endpush
