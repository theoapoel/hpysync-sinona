@extends('layouts.app')

@section('title', 'Terima Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px;" class="text-blue">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
            Terima Barang
        </h1>
        <p class="page-subtitle">Penerimaan barang — scan nomor dokumen pengiriman</p>
    </div>
    <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">← Kembali</a>
</div>

@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

@if(count($warehouses) === 0)
    <div class="alert alert-warning mb-3">
        <strong>Perhatian:</strong> Belum ada warehouse yang diaktifkan. Silakan pull dan aktifkan warehouse di
        <a href="{{ route('warehouses.index') }}">menu Warehouse</a>, lalu kembali ke halaman ini.
    </div>
@endif

{{-- ═══════════════════════════════════════════════════════════
     SCAN AREA — always visible at top, auto-focused
═══════════════════════════════════════════════════════════ --}}
<div class="card mb-3" id="scanCard">
    <div class="card-body" style="padding:20px 24px;">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="flex-shrink:0;width:48px;height:48px;border-radius:12px;background:var(--blue-light);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-barcode" style="font-size:22px;color:var(--blue);"></i>
            </div>
            <div style="flex:1;min-width:260px;">
                <div style="font-size:13px;font-weight:600;color:var(--text2);margin-bottom:6px;">
                    Scan Nomor Dokumen
                    <span id="scanStatusBadge" style="margin-left:8px;font-size:11px;padding:2px 8px;border-radius:10px;background:var(--surface2);color:var(--text2);font-weight:500;">Siap Scan</span>
                </div>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="scanInput"
                        class="form-control"
                        placeholder="Arahkan barcode scanner ke sini, atau ketik nomor dokumen…"
                        autocomplete="off"
                        style="font-family:'Google Sans',sans-serif;font-size:15px;letter-spacing:.5px;font-weight:600;">
                    <button type="button" class="btn btn-primary" onclick="triggerLoad()" id="scanBtn">
                        <i class="fas fa-download"></i> Muat
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="resetScan()" id="resetBtn" style="display:none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="scanFeedback" style="margin-top:6px;font-size:13px;min-height:18px;"></div>
            </div>
        </div>

        {{-- Fallback: dropdown jika pendingEntries ada --}}
        @if(count($pendingEntries))
        <div id="dropdownFallback" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
            <div style="font-size:12px;color:var(--text3);margin-bottom:6px;font-weight:500;">
                <i class="fas fa-list"></i> Atau pilih dari daftar:
            </div>
            <div style="display:flex;gap:8px;">
                <select id="erpEntrySelect" class="form-select" style="flex:1;">
                    <option value="">-- Pilih Stock Entry --</option>
                    @foreach($pendingEntries as $entry)
                        <option value="{{ $entry['name'] }}">
                            {{ $entry['name'] }} · {{ $entry['posting_date'] }}
                            @if($entry['from_warehouse'] ?? false) · {{ $entry['from_warehouse'] }}@endif
                        </option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-outline" onclick="loadFromDropdown()">Muat Item</button>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     FORM — muncul setelah scan berhasil
═══════════════════════════════════════════════════════════ --}}
<form method="POST" action="{{ route('stock-transfer.receive.store') }}" id="receiveForm" style="display:none;">
    @csrf
    <input type="hidden" name="erp_source_entry" id="erpSourceEntry">

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

        {{-- LEFT --}}
        <div>
            {{-- Info --}}
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">Informasi Penerimaan</span></div>
                <div class="card-body">
                    <div class="grid-2 gap-3">
                        <div class="form-group">
                            <label class="form-label">Gudang Asal (In-Transit) <span style="color:var(--red)">*</span></label>
                            @if(count($warehouses))
                                <select name="from_warehouse" id="fromWarehouse" class="form-select" required>
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
                                <input type="text" name="from_warehouse" id="fromWarehouse" class="form-control"
                                    value="{{ old('from_warehouse') }}" placeholder="Gudang in-transit" required>
                            @endif
                            @error('from_warehouse')<p class="text-red" style="font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gudang Tujuan <span style="color:var(--red)">*</span></label>
                            @if(count($warehouses))
                                <select name="to_warehouse" id="toWarehouse" class="form-select" required>
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
                                <input type="text" name="to_warehouse" id="toWarehouse" class="form-control"
                                    value="{{ old('to_warehouse') }}" placeholder="Gudang tujuan" required>
                            @endif
                            @error('to_warehouse')<p class="text-red" style="font-size:12px;margin-top:4px;">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:12px;">
                        <label class="form-label">Keterangan</label>
                        <textarea name="notes" class="form-control" rows="2"
                            placeholder="Catatan penerimaan (opsional)">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Items --}}
            <div class="card">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <span class="card-title">Daftar Barang Diterima</span>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addRow()">+ Tambah Baris</button>
                </div>
                <div class="card-body" style="padding:0;">
                    <div style="overflow-x:auto;">
                        <table id="itemTable" style="width:100%;border-collapse:collapse;">
                            <thead>
                                <tr style="background:var(--bg);border-bottom:1px solid var(--border);">
                                    <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left;">Kode Item</th>
                                    <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left;">Nama Barang</th>
                                    <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:right;width:95px;">Qty Kirim</th>
                                    <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:right;width:110px;">Qty Terima</th>
                                    <th style="padding:10px 12px;font-size:12px;color:var(--text2);font-weight:600;text-align:left;width:90px;">Satuan</th>
                                    <th style="padding:10px 12px;width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemBody"></tbody>
                        </table>
                    </div>
                    <div id="emptyRow" style="text-align:center;padding:32px;color:var(--text2);font-size:14px;">
                        Belum ada barang.
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Product search --}}
        <div>
            <div class="card" style="position:sticky;top:80px;">
                <div class="card-header"><span class="card-title">Cari Produk Lokal</span></div>
                <div class="card-body">
                    <input type="text" id="productSearch" class="form-control" placeholder="Nama / SKU…"
                        style="margin-bottom:10px;">
                    <div id="productResults" style="max-height:400px;overflow-y:auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary" id="submitBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
            Konfirmasi Penerimaan
        </button>
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">Batal</a>
    </div>
</form>

<style>
.item-row td { padding:8px 12px;border-bottom:1px solid var(--border); }
.item-row input { background:transparent;border:none;outline:none;width:100%;font-size:14px;color:var(--text); }
.item-row input:focus { background:var(--bg);border-radius:4px;padding:2px 4px; }
.item-row .qty-diff { font-size:11px;color:var(--red);display:block;margin-top:2px; }
.product-card { padding:10px;border:1px solid var(--border);border-radius:6px;margin-bottom:6px;cursor:pointer;transition:.15s; }
.product-card:hover { border-color:var(--blue);background:var(--bg); }
.product-card .name { font-size:13px;font-weight:600;color:var(--text); }
.product-card .meta { font-size:11px;color:var(--text2);margin-top:2px; }

#scanCard { transition: border-color .2s, box-shadow .2s; }
#scanCard.scanning { border-color:var(--blue); box-shadow: 0 0 0 3px rgba(66,133,244,.15); }
#scanCard.loaded  { border-color:var(--green); box-shadow: 0 0 0 3px rgba(52,168,83,.12); }
#scanCard.error   { border-color:var(--red);   box-shadow: 0 0 0 3px rgba(234,67,53,.12); }
</style>

<script>
const products = @json($productData);
let rowIdx = 0;

// ── Auto-focus scan input ──────────────────────────────────────
const scanInput = document.getElementById('scanInput');
scanInput.focus();

// ── Enter key triggers load ────────────────────────────────────
scanInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        triggerLoad();
    }
});

// ── Scan / load logic ──────────────────────────────────────────
async function triggerLoad() {
    const val = scanInput.value.trim();
    if (!val) return;
    await doLoad(val);
}

async function loadFromDropdown() {
    const sel = document.getElementById('erpEntrySelect');
    if (!sel?.value) { toast('Pilih Stock Entry terlebih dahulu.', 'error'); return; }
    scanInput.value = sel.value;
    await doLoad(sel.value);
}

async function doLoad(entryName) {
    setScanState('scanning');
    setFeedback('info', '<span class="spinner" style="border-color:rgba(66,133,244,.3);border-top-color:var(--blue);width:14px;height:14px;margin-right:6px;"></span>Memuat dokumen <strong>' + entryName + '</strong>…');
    document.getElementById('scanBtn').disabled = true;

    try {
        const res = await api.post('{{ route("stock-transfer.load-items") }}', { entry_name: entryName });

        if (!res.success) {
            setScanState('error');
            setFeedback('error', '<i class="fas fa-exclamation-circle"></i> ' + (res.error ?? 'Dokumen tidak ditemukan.'));
            document.getElementById('scanBtn').disabled = false;
            scanInput.select();
            return;
        }

        // Isi warehouse
        setWarehouse('fromWarehouse', res.from_warehouse);
        setWarehouse('toWarehouse', res.to_warehouse);
        document.getElementById('erpSourceEntry').value = entryName;

        // Isi tabel
        document.getElementById('itemBody').innerHTML = '';
        rowIdx = 0;
        res.items.forEach(i => addRow({
            item_code: i.item_code,
            name:      i.item_name,
            quantity:  i.quantity,
            unit:      i.unit,
        }));

        // Tampilkan form
        document.getElementById('receiveForm').style.display = '';

        setScanState('loaded');
        setFeedback('success',
            '<i class="fas fa-check-circle"></i> ' +
            '<strong>' + entryName + '</strong> — ' + res.items.length + ' item dimuat.' +
            ' <a href="#" onclick="resetScan();return false;" style="color:var(--blue);text-decoration:underline;margin-left:8px;">Scan dokumen lain</a>');

        document.getElementById('resetBtn').style.display = '';
        document.getElementById('scanInput').readOnly = true;
        document.getElementById('scanBtn').disabled = true;

        renderProducts('');

    } catch (e) {
        setScanState('error');
        setFeedback('error', '<i class="fas fa-exclamation-circle"></i> Gagal: ' + e.message);
        document.getElementById('scanBtn').disabled = false;
        scanInput.select();
    }
}

function resetScan() {
    scanInput.value = '';
    scanInput.readOnly = false;
    document.getElementById('scanBtn').disabled = false;
    document.getElementById('resetBtn').style.display = 'none';
    document.getElementById('receiveForm').style.display = 'none';
    document.getElementById('itemBody').innerHTML = '';
    rowIdx = 0;
    checkEmpty();
    setScanState('');
    setFeedback('', '');
    scanInput.focus();
}

function setScanState(state) {
    const card = document.getElementById('scanCard');
    card.classList.remove('scanning', 'loaded', 'error');
    if (state) card.classList.add(state);

    const badge = document.getElementById('scanStatusBadge');
    const styles = {
        '':         ['Siap Scan',  'background:var(--surface2);color:var(--text2)'],
        'scanning': ['Memuat…',    'background:var(--blue-light);color:var(--blue)'],
        'loaded':   ['Dimuat',     'background:#E6F4EA;color:var(--green)'],
        'error':    ['Error',      'background:#FCE8E6;color:var(--red)'],
    };
    const [label, style] = styles[state] ?? styles[''];
    badge.textContent = label;
    badge.style.cssText = style + ';font-size:11px;padding:2px 8px;border-radius:10px;font-weight:500;margin-left:8px;';
}

function setFeedback(type, html) {
    const el = document.getElementById('scanFeedback');
    const colors = { success: 'var(--green)', error: 'var(--red)', info: 'var(--blue-dark)', '': 'var(--text2)' };
    el.style.color = colors[type] ?? colors[''];
    el.innerHTML = html;
}

function setWarehouse(id, name) {
    const el = document.getElementById(id);
    if (!el || !name) return;
    if (el.tagName === 'SELECT') {
        [...el.options].forEach(o => { if (o.value === name) o.selected = true; });
    } else {
        el.value = name;
    }
}

// ── Table rows ─────────────────────────────────────────────────
function addRow(item = null) {
    const tbody = document.getElementById('itemBody');
    const idx   = rowIdx++;
    const tr    = document.createElement('tr');
    tr.className = 'item-row';
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td><input name="items[${idx}][item_code]" value="${item?.item_code ?? ''}" placeholder="ITEM-001" required></td>
        <td><input name="items[${idx}][item_name]" value="${item?.name ?? ''}" placeholder="Nama barang" required></td>
        <td style="text-align:right;">
            <input name="items[${idx}][quantity]" type="number" step="0.001" min="0.001"
                value="${item?.quantity ?? 1}" style="text-align:right;" readonly
                title="Qty sesuai dokumen pengiriman">
        </td>
        <td style="text-align:right;">
            <input name="items[${idx}][actual_quantity]" type="number" step="0.001" min="0.001"
                value="${item?.quantity ?? 1}" style="text-align:right;" required
                oninput="checkDiff(this)"
                title="Qty aktual yang diterima">
        </td>
        <td><input name="items[${idx}][unit]" value="${item?.unit ?? 'Nos'}" placeholder="Nos"></td>
        <td style="text-align:center;">
            <button type="button" onclick="removeRow(this)"
                style="background:none;border:none;cursor:pointer;color:var(--red);font-size:18px;line-height:1;">×</button>
        </td>`;
    tbody.appendChild(tr);
    checkEmpty();
}

function checkDiff(input) {
    const tr   = input.closest('tr');
    const orig = parseFloat(tr.querySelector('input[name*="[quantity]"]').value) || 0;
    const act  = parseFloat(input.value) || 0;
    let note   = tr.querySelector('.qty-diff');
    if (!note) { note = document.createElement('span'); note.className = 'qty-diff'; input.after(note); }
    note.textContent = act !== orig ? `Selisih: ${(act - orig).toFixed(3)}` : '';
}

function removeRow(btn) { btn.closest('tr').remove(); checkEmpty(); }

function checkEmpty() {
    const tbody = document.getElementById('itemBody');
    document.getElementById('emptyRow').style.display =
        tbody.querySelectorAll('tr').length === 0 ? '' : 'none';
}

// ── Product search (right panel) ───────────────────────────────
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
            (p.sku && p.sku.toLowerCase().includes(q))
          )
        : products.slice(0, 20);

    if (!filtered.length) {
        box.innerHTML = '<p style="color:var(--text2);font-size:13px;text-align:center;padding:16px;">Tidak ditemukan</p>';
        return;
    }
    box.innerHTML = filtered.slice(0, 30).map(p => `
        <div class="product-card" onclick='addRow(${JSON.stringify({ ...p, quantity: 1 })})'>
            <div class="name">${p.name}</div>
            <div class="meta">SKU: ${p.sku ?? '—'} · ${p.unit ?? 'Nos'}</div>
        </div>`).join('');
}

// ── Form submit guard ──────────────────────────────────────────
document.getElementById('receiveForm').addEventListener('submit', function (e) {
    const rows = document.querySelectorAll('#itemBody tr');
    if (rows.length === 0) {
        e.preventDefault();
        toast('Tambahkan minimal 1 barang.', 'error');
        return;
    }
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitBtn').innerHTML = '<span style="opacity:.7">Menyimpan…</span>';
});

checkEmpty();
</script>
@endsection
