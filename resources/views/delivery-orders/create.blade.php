@extends('layouts.app')
@section('title', 'Buat Delivery Order')

@push('styles')
<style>
.item-row, .ship-row { animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
.ship-card { border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:12px; background:var(--surface); }
.ship-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
.ship-item-row { display:grid; grid-template-columns:1fr 80px 110px 100px 28px; gap:8px; align-items:center; margin-bottom:8px; }
/* Item search (textbox like POS) */
.do-search-dropdown { position:absolute; left:0; right:0; top:100%; margin-top:4px; background:var(--surface); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); max-height:320px; overflow-y:auto; z-index:50; display:none; }
.do-search-dropdown.show { display:block; }
.do-dd-item { display:grid; grid-template-columns:1fr auto; grid-template-areas:"name price" "meta price"; gap:0 12px; padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border); }
.do-dd-item:last-child { border-bottom:none; }
.do-dd-item:hover, .do-dd-item.active { background:var(--surface2); }
.do-dd-name { grid-area:name; font-size:13px; font-weight:600; }
.do-dd-meta { grid-area:meta; font-size:11px; color:var(--text3); }
.do-dd-price { grid-area:price; align-self:center; font-size:13px; font-weight:700; color:var(--blue); white-space:nowrap; }
.do-dd-empty { padding:12px; text-align:center; color:var(--text3); font-size:13px; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-plus text-blue"></i> Buat Delivery Order</div>
    </div>
    <a href="{{ route('delivery-orders.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

@if ($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px">
    <i class="fas fa-exclamation-circle"></i> Order belum bisa disimpan:
    <ul style="margin:6px 0 0 18px">
        @foreach ($errors->all() as $message)
        <li>{{ $message }}</li>
        @endforeach
    </ul>
</div>
@endif

<form id="doForm" method="POST" action="{{ route('delivery-orders.store') }}">
@csrf
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start">

    {{-- Kiri: Detail Order --}}
    <div>
        {{-- Info Customer --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fas fa-user text-blue"></i> Informasi Order</div></div>
            <div class="card-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Customer <span style="color:var(--red)">*</span></label>
                        <div style="position:relative" id="customerSearchWrap">
                            <i class="fas fa-user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px"></i>
                            <input type="text" id="customerSearch" class="form-control" autocomplete="off"
                                placeholder="Ketik nama / no. HP customer..." style="padding-left:34px;padding-right:32px"
                                oninput="onCustomerSearch(this.value)" onfocus="onCustomerSearch(this.value)"
                                onkeydown="onCustomerKeydown(event)">
                            <i id="customerCheck" class="fas fa-check-circle" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--green);font-size:13px"></i>
                            <input type="hidden" name="customer_id" id="customerId" value="{{ old('customer_id') }}">
                            <div id="customerDropdown" class="do-search-dropdown"></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Pembuatan <span style="color:var(--red)">*</span></label>
                        <input type="date" name="order_date" class="form-control" required
                            onfocus="try{this.showPicker()}catch(e){}" onclick="try{this.showPicker()}catch(e){}"
                            value="{{ old('order_date', now()->format('Y-m-d')) }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Penagihan (Billing Address)</label>
                    <textarea name="billing_address" id="billingAddress" class="form-control" rows="2"
                        placeholder="Alamat penagihan customer">{{ old('billing_address') }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Order</label>
                    <input type="text" name="notes" class="form-control" placeholder="Catatan tambahan (opsional)" value="{{ old('notes') }}">
                </div>
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;background:var(--surface2);border-radius:8px;margin-bottom:0">
                    <input type="hidden" name="bill_as_receivable" value="0">
                    <input type="checkbox" name="bill_as_receivable" id="billAsReceivable" value="1" {{ old('bill_as_receivable') ? 'checked' : '' }}
                        onchange="toggleArPricing()"
                        style="width:16px;height:16px;margin-top:2px;accent-color:var(--blue);cursor:pointer;flex-shrink:0">
                    <span>
                        <span style="display:block;font-size:13px;font-weight:600;color:var(--text2)">
                            <i class="fas fa-file-invoice-dollar text-blue" style="margin-right:4px"></i>Tagih sebagai piutang (AR)
                        </span>
                        <span style="display:block;font-size:12px;color:var(--text3);margin-top:2px">
                            Pembayaran boleh dilewati. Invoice HPYERP langsung terbit saat order dikonfirmasi dan tercatat sebagai piutang customer.
                            Harga item boleh diubah.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Items --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-box text-blue"></i> Item Order</div>
            </div>
            <div class="card-body" style="padding:0">
                {{-- Pencarian item (textbox, seperti di kasir) --}}
                <div style="padding:12px 16px;border-bottom:1px solid var(--border)">
                    <div style="position:relative" id="itemSearchWrap">
                        <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px"></i>
                        <input type="text" id="itemSearch" class="form-control" autocomplete="off"
                            placeholder="Cari produk untuk ditambahkan..." style="padding-left:34px"
                            oninput="onItemSearch(this.value)" onfocus="onItemSearch(this.value)">
                        <div id="itemSearchDropdown" class="do-search-dropdown"></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 80px 110px 100px 28px;gap:8px;padding:10px 16px;background:var(--surface2);font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
                    <span>Produk</span><span>Qty</span><span>Harga</span><span>Subtotal</span><span></span>
                </div>
                <div id="itemsContainer" style="padding:12px 16px"></div>
                <div style="padding:12px 16px;border-top:1px solid var(--border);text-align:right">
                    <span class="text-muted text-sm">Total Order: </span>
                    <span id="orderTotal" class="money font-bold text-blue" style="font-size:16px">Rp 0</span>
                </div>
            </div>
        </div>

        {{-- Shipments / Tujuan Pengiriman --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-map-marker-alt text-blue"></i> Tujuan Pengiriman</div>
                <button type="button" onclick="addShipment()" class="btn btn-outline btn-sm">
                    <i class="fas fa-plus"></i> Tambah Tujuan
                </button>
            </div>
            <div class="card-body">
                @error('shipments')
                <div class="alert alert-danger" style="margin-bottom:12px">
                    <i class="fas fa-exclamation-circle"></i> {{ $message }}
                </div>
                @enderror
                <div id="shipmentsContainer"></div>
            </div>
        </div>
    </div>

    {{-- Kanan: Ringkasan --}}
    <div style="position:sticky;top:80px">
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fas fa-info-circle text-blue"></i> Ringkasan</div></div>
            <div class="card-body">
                <div id="summaryContent" style="color:var(--text3);font-size:13px">
                    Tambahkan item dan tujuan pengiriman terlebih dahulu.
                </div>
                <div id="allocationStatus" style="margin-top:12px"></div>
            </div>
        </div>
        <button type="submit" id="btnSubmit" class="btn btn-primary w-full btn-lg" style="border-radius:10px">
            <i class="fas fa-save"></i> Simpan Delivery Order
        </button>
    </div>
</div>
</form>

{{-- Hidden: product data for JS --}}
<script id="productData" type="application/json">@json($products)</script>
<script id="customerData" type="application/json">@json($customers)</script>

@endsection

@push('scripts')
<script>
const products  = JSON.parse(document.getElementById('productData').textContent);
const customers = JSON.parse(document.getElementById('customerData').textContent);
const today     = new Date().toISOString().split('T')[0];
const hourOptions   = Array.from({length:24}, (_,h) => `<option value="${String(h).padStart(2,'0')}">${String(h).padStart(2,'0')}</option>`).join('');
const minuteOptions = ['00','05','10','15','20','25','30','35','40','45','50','55'].map(m => `<option value="${m}">${m}</option>`).join('');
let itemCount   = 0;
let shipCount   = 0;

function fmt(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

// ── Customers (kotak ketik + saran, seperti pencarian item) ──
let selectedCustomer = null;
let customerMatches  = [];
let customerActive   = -1;

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function renderCustomerDropdown() {
    const dd = document.getElementById('customerDropdown');
    if (!customerMatches.length) {
        dd.innerHTML = '<div class="do-dd-empty">Customer tidak ditemukan</div>';
    } else {
        dd.innerHTML = customerMatches.map((c, i) =>
            `<div class="do-dd-item${i === customerActive ? ' active' : ''}" onmousedown="selectCustomer(${c.id})">
                <div class="do-dd-name">${escapeHtml(c.name)}</div>
                <div class="do-dd-meta">${escapeHtml([c.phone, c.address].filter(Boolean).join(' · '))}</div>
            </div>`
        ).join('');
    }
    dd.classList.add('show');
}

function onCustomerSearch(term) {
    const raw = (term || '').trim();
    // Teks diubah setelah memilih → pilihan lama tidak berlaku lagi
    if (selectedCustomer && raw !== selectedCustomer.name) setSelectedCustomer(null, false);
    const q = raw.toLowerCase();
    const dd = document.getElementById('customerDropdown');
    if (!q) { dd.classList.remove('show'); dd.innerHTML = ''; customerMatches = []; return; }
    customerMatches = customers.filter(c =>
        (c.name && c.name.toLowerCase().includes(q)) ||
        (c.phone && String(c.phone).toLowerCase().includes(q))
    ).slice(0, 30);
    customerActive = customerMatches.length ? 0 : -1;
    renderCustomerDropdown();
}

function onCustomerKeydown(e) {
    const dd = document.getElementById('customerDropdown');
    if (!dd.classList.contains('show') || !customerMatches.length) return;
    if (e.key === 'ArrowDown')      { e.preventDefault(); customerActive = (customerActive + 1) % customerMatches.length; renderCustomerDropdown(); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); customerActive = (customerActive - 1 + customerMatches.length) % customerMatches.length; renderCustomerDropdown(); }
    else if (e.key === 'Enter')     { e.preventDefault(); if (customerActive >= 0) selectCustomer(customerMatches[customerActive].id); }
    else if (e.key === 'Escape')    { dd.classList.remove('show'); }
}

function setSelectedCustomer(c, fillAddress = true) {
    selectedCustomer = c;
    document.getElementById('customerId').value = c ? c.id : '';
    document.getElementById('customerCheck').style.display = c ? '' : 'none';
    if (c) {
        document.getElementById('customerSearch').value = c.name;
        if (fillAddress) document.getElementById('billingAddress').value = c.address || '';
    }
    applyBuyerToShip0();
}

function selectCustomer(id) {
    const c = customers.find(x => x.id === id);
    if (!c) return;
    setSelectedCustomer(c);
    document.getElementById('customerDropdown').classList.remove('show');
}

// ── Tujuan 1: samakan nama & alamat dengan pembeli ───────────
function currentBuyer() {
    const name = selectedCustomer ? selectedCustomer.name : '';
    const addr = document.getElementById('billingAddress').value || '';
    return { name, addr };
}

function applyBuyerToShip0() {
    const cb = document.getElementById('sameAsBuyer');
    if (!cb || !cb.checked) return;
    const { name, addr } = currentBuyer();
    const nameEl = document.querySelector('[name="shipments[0][recipient_name]"]');
    const addrEl = document.querySelector('[name="shipments[0][shipping_address]"]');
    if (nameEl) nameEl.value = name;
    if (addrEl) addrEl.value = addr;
    updateSummary();
}

function toggleSameAsBuyer(cb) {
    const nameEl = document.querySelector('[name="shipments[0][recipient_name]"]');
    const addrEl = document.querySelector('[name="shipments[0][shipping_address]"]');
    [nameEl, addrEl].forEach(el => {
        if (!el) return;
        el.readOnly = cb.checked;
        el.style.background = cb.checked ? 'var(--surface2)' : '';
    });
    if (cb.checked) applyBuyerToShip0();
}

// ── Item Search (textbox, seperti di kasir) ──────────────────
function onItemSearch(term) {
    term = (term || '').trim().toLowerCase();
    const dd = document.getElementById('itemSearchDropdown');
    if (!term) { dd.classList.remove('show'); dd.innerHTML = ''; return; }
    const matches = products.filter(p =>
        (p.name && p.name.toLowerCase().includes(term)) ||
        (p.sku && String(p.sku).toLowerCase().includes(term))
    ).slice(0, 30);
    if (!matches.length) {
        dd.innerHTML = '<div class="do-dd-empty">Produk tidak ditemukan</div>';
    } else {
        dd.innerHTML = matches.map(p =>
            `<div class="do-dd-item" onmousedown="addItemFromSearch(${p.id})">
                <div class="do-dd-name">${p.name}</div>
                <div class="do-dd-meta">${p.sku || ''}</div>
                <div class="do-dd-price">${fmt(p.price || 0)}</div>
            </div>`
        ).join('');
    }
    dd.classList.add('show');
}

function addItemFromSearch(pid) {
    const p = products.find(x => x.id === pid);
    if (!p) return;
    // Gabungkan ke baris yang sudah ada jika produk sama, seperti perilaku kasir
    const existing = [...document.querySelectorAll('.item-row')]
        .find(r => r.querySelector('.item-pid')?.value === String(pid));
    if (existing) {
        const qtyEl = existing.querySelector('.item-qty');
        qtyEl.value = parseFloat(qtyEl.value || 0) + 1;
        recalcAll();
    } else {
        addItem(p.name, p.sku || '', p.price || 0, 1, p.id);
    }
    const input = document.getElementById('itemSearch');
    input.value = '';
    document.getElementById('itemSearchDropdown').classList.remove('show');
    input.focus();
}

// Tutup dropdown saat klik di luar area pencarian
document.addEventListener('click', e => {
    if (!e.target.closest('#itemSearchWrap')) {
        document.getElementById('itemSearchDropdown')?.classList.remove('show');
    }
    if (!e.target.closest('#customerSearchWrap')) {
        document.getElementById('customerDropdown')?.classList.remove('show');
    }
});

// ── Harga: hanya boleh diubah bila "Tagih sebagai piutang (AR)" dicentang ──
function isArChecked() {
    return !!document.getElementById('billAsReceivable')?.checked;
}

function applyPriceLock(input) {
    const editable = isArChecked();
    input.readOnly = !editable;
    input.tabIndex = editable ? 0 : -1;
    input.style.background = editable ? '' : 'var(--surface2)';
    input.title = editable ? '' : 'Centang "Tagih sebagai piutang (AR)" untuk mengubah harga';
}

function toggleArPricing() {
    const editable = isArChecked();
    document.querySelectorAll('.item-row .item-price').forEach(input => {
        // Kembalikan ke harga produk saat AR dimatikan lagi.
        if (!editable && input.dataset.defaultPrice !== undefined) input.value = input.dataset.defaultPrice;
        applyPriceLock(input);
    });
    recalcAll();
}

// Samakan harga item per tujuan dengan harga Item Order (dicocokkan per nama produk).
function syncShipPrices() {
    const priceByName = {};
    getOrderItems().forEach(it => { priceByName[it.name] = it.price; });
    document.querySelectorAll('.ship-item-row-data').forEach(row => {
        const sel = row.querySelector('select');
        [...sel.options].forEach(opt => { if (opt.value && priceByName[opt.value] !== undefined) opt.dataset.price = priceByName[opt.value]; });
        if (sel.value && priceByName[sel.value] !== undefined) {
            row.querySelector('.ship-item-price').value = priceByName[sel.value];
            recalcShipItem(row.querySelector('input[type=number]'));
        }
    });
}

// ── Order Items ──────────────────────────────────────────────
function addItem(name = '', sku = '', price = 0, qty = 1, pid = '') {
    const idx = itemCount++;
    const product = products.find(x => String(x.id) === String(pid));
    const defaultPrice = product ? (product.price || 0) : price;

    const row = document.createElement('div');
    row.className = 'item-row';
    row.style = 'display:grid;grid-template-columns:1fr 80px 110px 100px 28px;gap:8px;margin-bottom:8px;align-items:center';
    row.innerHTML = `
        <div class="item-product-name" title="${name}" style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${name || '—'}</div>
        <input type="number" name="items[${idx}][qty]" class="form-control item-qty" value="${qty}"
            min="0.01" step="0.01" style="text-align:right;font-size:13px" oninput="recalcItem(${idx})">
        <input type="number" name="items[${idx}][price]" class="form-control item-price" value="${price}" data-default-price="${defaultPrice}"
            min="0" step="1" style="text-align:right;font-size:13px" oninput="recalcItem(${idx})">
        <input type="text" class="form-control item-subtotal" readonly style="text-align:right;font-size:13px;background:var(--surface2)">
        <button type="button" onclick="this.closest('.item-row').remove(); recalcAll()" style="border:none;background:none;cursor:pointer;color:var(--red);font-size:16px">
            <i class="fas fa-times"></i>
        </button>
        <input type="hidden" name="items[${idx}][product_id]" class="item-pid" value="${pid}">
        <input type="hidden" name="items[${idx}][product_name]" class="item-name" value="${name}">
        <input type="hidden" name="items[${idx}][product_sku]" class="item-sku" value="${sku}">
    `;
    document.getElementById('itemsContainer').appendChild(row);
    applyPriceLock(row.querySelector('.item-price'));
    recalcItem(idx);
}

function recalcItem(idx) {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(row => {
        const price    = parseFloat(row.querySelector('.item-price')?.value || 0);
        const qty      = parseFloat(row.querySelector('.item-qty')?.value || 0);
        const sub      = price * qty;
        const subField = row.querySelector('.item-subtotal');
        if (subField) subField.value = fmt(sub);
    });
    recalcAll();
}

function recalcAll() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
        const qty   = parseFloat(row.querySelector('.item-qty')?.value || 0);
        total += price * qty;
    });
    document.getElementById('orderTotal').textContent = fmt(total);
    updateSummary();
    refreshShipmentItemOptions();
}

// ── Shipments ────────────────────────────────────────────────
function addShipment() {
    const idx = shipCount++;
    const card = document.createElement('div');
    card.className = 'ship-card';
    card.id = 'ship-' + idx;
    card.innerHTML = `
        <div class="ship-card-header">
            <div class="font-medium" style="font-size:14px"><i class="fas fa-map-marker-alt text-blue"></i> Tujuan ${idx + 1}</div>
            <button type="button" onclick="document.getElementById('ship-${idx}').remove(); updateSummary()" class="btn btn-ghost btn-sm" style="color:var(--red)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        ${idx === 0 ? `
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text2);margin-bottom:10px;padding:8px 10px;background:var(--surface2);border-radius:8px">
            <input type="checkbox" id="sameAsBuyer" onchange="toggleSameAsBuyer(this)" style="width:16px;height:16px;accent-color:var(--blue);cursor:pointer">
            Nama &amp; alamat sama dengan pembeli (Informasi Order)
        </label>` : ''}
        <div class="grid-2" style="margin-bottom:10px">
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">Nama Penerima *</label>
                <input type="text" name="shipments[${idx}][recipient_name]" class="form-control" required style="font-size:13px" placeholder="Nama penerima">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:12px">No. HP</label>
                <input type="text" name="shipments[${idx}][recipient_phone]" class="form-control" style="font-size:13px" placeholder="08xx-xxxx-xxxx">
            </div>
        </div>
        <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:12px">Tanggal Pengiriman *</label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="date" name="shipments[${idx}][delivery_date]" class="form-control" required style="font-size:13px;flex:1" value="${today}" onfocus="try{this.showPicker()}catch(e){}" onclick="try{this.showPicker()}catch(e){}">
                <select name="shipments[${idx}][delivery_hour]" class="form-control form-select" style="font-size:13px;width:80px">
                    ${hourOptions}
                </select>
                <span style="font-size:13px;color:var(--text3);flex-shrink:0">:</span>
                <select name="shipments[${idx}][delivery_minute]" class="form-control form-select" style="font-size:13px;width:80px">
                    ${minuteOptions}
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:12px">Alamat Pengiriman *</label>
            <textarea name="shipments[${idx}][shipping_address]" class="form-control" rows="2" required style="font-size:13px" placeholder="Alamat lengkap tujuan pengiriman"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:12px">Catatan Pengiriman</label>
            <input type="text" name="shipments[${idx}][notes]" class="form-control" style="font-size:13px" placeholder="Catatan untuk kurir (opsional)">
        </div>

        <div style="background:var(--surface2);border-radius:8px;padding:12px">
            <div style="font-size:12px;font-weight:700;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px">
                Item untuk tujuan ini
            </div>
            <div class="ship-item-row" style="font-size:11px;font-weight:700;color:var(--text3);margin-bottom:4px">
                <span>Produk</span><span>Qty</span><span>Harga</span><span>Subtotal</span><span></span>
            </div>
            <div id="ship-items-${idx}"></div>
            <button type="button" onclick="addShipItem(${idx})" class="btn btn-ghost btn-sm" style="margin-top:4px">
                <i class="fas fa-plus"></i> Tambah Item
            </button>
        </div>`;
    document.getElementById('shipmentsContainer').appendChild(card);
    updateSummary();
}

function getOrderItems() {
    const rows = [];
    document.querySelectorAll('.item-row').forEach(row => {
        const name = row.querySelector('.item-name')?.value;
        const sku  = row.querySelector('.item-sku')?.value;
        const price = parseFloat(row.querySelector('.item-price')?.value || 0);
        if (name) rows.push({ name, sku, price });
    });
    return rows;
}

function addShipItem(shipIdx, selectedName = '', qty = 1, price = 0) {
    const container = document.getElementById('ship-items-' + shipIdx);
    const items     = getOrderItems();
    const siIdx     = container.querySelectorAll('.ship-item-row-data').length;
    // Baris yang dipulihkan (old input) sudah terpilih tanpa memicu onchange — isi SKU-nya di sini
    const selectedSku = items.find(it => it.name === selectedName)?.sku || '';

    const opts = items.map(it =>
        `<option value="${it.name}" data-price="${it.price}" data-sku="${it.sku}" ${it.name===selectedName?'selected':''}>${it.name}</option>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'ship-item-row ship-item-row-data';
    row.innerHTML = `
        <select name="shipments[${shipIdx}][items][${siIdx}][product_name]" class="form-control form-select" style="font-size:12px" onchange="onShipItemChange(this)">
            <option value="">— Pilih —</option>${opts}
        </select>
        <input type="number" name="shipments[${shipIdx}][items][${siIdx}][qty]" value="${qty}" min="0" step="0.01"
            class="form-control" style="font-size:12px;text-align:right" oninput="recalcShipItem(this)">
        <input type="number" name="shipments[${shipIdx}][items][${siIdx}][price]" value="${price}" min="0" step="1"
            readonly class="form-control ship-item-price" style="font-size:12px;text-align:right;background:var(--surface2)" tabindex="-1">
        <input type="text" class="form-control" readonly style="font-size:12px;text-align:right;background:var(--surface)">
        <button type="button" onclick="this.closest('.ship-item-row-data').remove(); updateSummary()" style="border:none;background:none;cursor:pointer;color:var(--red)">
            <i class="fas fa-times" style="font-size:12px"></i>
        </button>
        <input type="hidden" name="shipments[${shipIdx}][items][${siIdx}][product_sku]" class="ship-item-sku" value="${selectedSku}">
    `;
    container.appendChild(row);
    recalcShipItem(row.querySelector('input[type=number]'));
}

function onShipItemChange(sel) {
    const opt = sel.options[sel.selectedIndex];
    const row = sel.closest('.ship-item-row-data');
    row.querySelector('.ship-item-price').value = opt.dataset.price || 0;
    row.querySelector('.ship-item-sku').value   = opt.dataset.sku || '';
    recalcShipItem(row.querySelector('input[type=number]'));
    updateSummary();
}

function recalcShipItem(input) {
    const row  = input.closest('.ship-item-row-data');
    if (!row) return;
    const qty  = parseFloat(row.querySelectorAll('input[type=number]')[0]?.value || 0);
    const price = parseFloat(row.querySelectorAll('input[type=number]')[1]?.value || 0);
    const sub  = row.querySelectorAll('input[type=text]')[0];
    if (sub) sub.value = fmt(qty * price);
    updateSummary();
}

function refreshShipmentItemOptions() {
    syncShipPrices();
}

function getOrderQtyMap() {
    const map = {};
    document.querySelectorAll('.item-row').forEach(row => {
        const name = row.querySelector('.item-name')?.value;
        const qty  = parseFloat(row.querySelector('.item-qty')?.value || 0);
        if (name) map[name] = (map[name] || 0) + qty;
    });
    return map;
}

function getShippedQtyMap() {
    const map = {};
    document.querySelectorAll('.ship-item-row-data').forEach(row => {
        const name = row.querySelector('select')?.value;
        const qty  = parseFloat(row.querySelectorAll('input[type=number]')[0]?.value || 0);
        if (name && qty > 0) map[name] = (map[name] || 0) + qty;
    });
    return map;
}

function validateShipments() {
    const orderMap   = getOrderQtyMap();
    const shippedMap = getShippedQtyMap();
    const errors = [];

    for (const [name, orderQty] of Object.entries(orderMap)) {
        const shippedQty = shippedMap[name] || 0;
        const diff = shippedQty - orderQty;
        if (Math.abs(diff) >= 0.001) {
            if (diff < 0) {
                errors.push(`<strong>${name}</strong>: kurang ${(orderQty - shippedQty).toLocaleString('id-ID')} (dipesan ${orderQty.toLocaleString('id-ID')}, dialokasi ${shippedQty.toLocaleString('id-ID')})`);
            } else {
                errors.push(`<strong>${name}</strong>: kelebihan ${(shippedQty - orderQty).toLocaleString('id-ID')} (dipesan ${orderQty.toLocaleString('id-ID')}, dialokasi ${shippedQty.toLocaleString('id-ID')})`);
            }
        }
    }
    for (const name of Object.keys(shippedMap)) {
        if (!orderMap[name]) {
            errors.push(`<strong>${name}</strong>: ada di pengiriman tapi tidak ada di item order`);
        }
    }
    return errors;
}

function updateSummary() {
    const ships = document.querySelectorAll('.ship-card').length;
    const items = document.querySelectorAll('.item-row').length;
    const total = document.getElementById('orderTotal').textContent;

    document.getElementById('summaryContent').innerHTML = `
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
            <span class="text-muted">Total Item</span><span class="font-medium">${items} jenis</span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
            <span class="text-muted">Tujuan Kirim</span><span class="font-medium">${ships} alamat</span>
        </div>
        <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid var(--border);font-size:16px">
            <span class="font-medium">Total Order</span>
            <span class="money font-bold text-blue">${total}</span>
        </div>`;

    // Allocation status per item
    const orderMap   = getOrderQtyMap();
    const shippedMap = getShippedQtyMap();
    const itemNames  = Object.keys(orderMap);
    const statusEl   = document.getElementById('allocationStatus');

    if (itemNames.length === 0) { statusEl.innerHTML = ''; return; }

    const rows = itemNames.map(name => {
        const ordered  = orderMap[name];
        const shipped  = shippedMap[name] || 0;
        const ok       = Math.abs(shipped - ordered) < 0.001;
        const color    = ok ? 'var(--green)' : 'var(--red)';
        const icon     = ok ? 'fa-check-circle' : 'fa-exclamation-circle';
        return `<div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;padding:3px 0;border-bottom:1px solid var(--border)">
            <span style="color:var(--text2)">${name}</span>
            <span style="color:${color};font-weight:600;white-space:nowrap;margin-left:8px">
                <i class="fas ${icon}" style="margin-right:3px"></i>${shipped.toLocaleString('id-ID')} / ${ordered.toLocaleString('id-ID')}
            </span>
        </div>`;
    }).join('');

    const errors = validateShipments();
    const allOk  = errors.length === 0;

    statusEl.innerHTML = `
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
            Alokasi Item ke Pengiriman
        </div>
        ${rows}
        <div style="margin-top:8px;padding:8px;border-radius:6px;font-size:12px;background:${allOk ? '#E8F5E9' : '#FFEBEE'};color:${allOk ? '#2E7D32' : '#C62828'}">
            <i class="fas ${allOk ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
            ${allOk ? 'Semua item sudah dialokasikan dengan benar.' : 'Item belum sesuai — periksa alokasi pengiriman.'}
        </div>`;

    const btn = document.getElementById('btnSubmit');
    if (btn) {
        btn.disabled = !allOk;
        btn.style.opacity = allOk ? '1' : '0.5';
        btn.title = allOk ? '' : 'Selesaikan alokasi item terlebih dahulu';
    }
}

// Block submit if allocation invalid
document.getElementById('doForm').addEventListener('submit', function(e) {
    if (!document.getElementById('customerId').value) {
        e.preventDefault();
        alert('Pilih customer terlebih dahulu dari daftar saran.');
        document.getElementById('customerSearch').focus();
        return;
    }
    const errors = validateShipments();
    if (errors.length > 0) {
        e.preventDefault();
        alert('Item pengiriman belum sesuai:\n\n• ' + errors.map(s => s.replace(/<[^>]+>/g, '')).join('\n• '));
    }
});

// Init: restore previously entered items/shipments if the save failed validation,
// otherwise start with 1 empty item + 1 empty shipment.
const oldItems     = @json(old('items', []));
const oldShipments = @json(old('shipments', []));

// Pulihkan customer yang sudah dipilih bila simpan gagal validasi
// (alamat penagihan sudah dipulihkan lewat old(), jangan ditimpa).
{
    const oldCustomerId = parseInt(document.getElementById('customerId').value || 0);
    const oldCustomer   = oldCustomerId ? customers.find(c => c.id === oldCustomerId) : null;
    if (oldCustomer) setSelectedCustomer(oldCustomer, false);
}

if (Object.keys(oldItems).length > 0) {
    Object.values(oldItems).forEach(it => {
        addItem(it.product_name || '', it.product_sku || '', it.price || 0, it.qty || 1, it.product_id || '');
    });
}
// Mulai tanpa baris item — item ditambahkan lewat kotak pencarian di atas.

if (Object.keys(oldShipments).length > 0) {
    Object.values(oldShipments).forEach(ship => {
        addShipment();
        const idx = shipCount - 1;
        const setVal = (field, val) => {
            const el = document.querySelector(`[name="shipments[${idx}][${field}]"]`);
            if (el && val !== undefined && val !== null) el.value = val;
        };
        setVal('recipient_name', ship.recipient_name);
        setVal('recipient_phone', ship.recipient_phone);
        setVal('shipping_address', ship.shipping_address);
        setVal('delivery_date', ship.delivery_date);
        if (ship.delivery_hour !== undefined) setVal('delivery_hour', String(ship.delivery_hour).padStart(2, '0'));
        setVal('delivery_minute', ship.delivery_minute);
        setVal('notes', ship.notes);
        if (ship.items) {
            Object.values(ship.items).forEach(si => {
                addShipItem(idx, si.product_name || '', si.qty || 1, si.price || 0);
            });
        }
    });
} else {
    addShipment();
}
recalcAll();
</script>
@endpush
