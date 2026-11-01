{{-- Shift Kasir (Buka/Tutup Kasir → POS Opening/Closing Entry).
     Dipakai di semua layout POS (Klasik/Quick/Express). Bergantung pada helper
     global tiap layout: csrf, fmt(), toast(), closeModal(). --}}

{{-- ===== Modal: Buka Kasir ===== --}}
<div class="modal-overlay" id="openShiftModal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-cash-register" style="color:var(--primary)"></i> Buka Kasir</div>
        </div>
        <div class="modal-body">
            <p style="color:var(--text3);font-size:13px;margin-bottom:14px">Masukkan modal kas awal (uang di laci) untuk memulai shift.</p>
            <label style="font-size:12px;font-weight:700;color:var(--text3)">Modal Kas Awal (Rp)</label>
            <input type="number" id="openingCashInput" class="form-control" value="0" min="0" step="1000"
                style="font-size:20px;text-align:right;margin-top:6px">
            <p style="font-size:11px;color:var(--text3);margin-top:10px">
                <i class="fas fa-wifi"></i> Tanpa internet kasir tetap bisa dibuka; pencatatan ke ERP HPY disusulkan otomatis saat online.
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" style="width:100%" id="btnDoOpenShift" onclick="doOpenShift()">
                <i class="fas fa-unlock"></i> Buka Kasir
            </button>
        </div>
    </div>
</div>

{{-- ===== Modal: Tutup Kasir ===== --}}
<div class="modal-overlay" id="closeShiftModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-cash-register" style="color:var(--red)"></i> Tutup Kasir</div>
            <button onclick="closeModal('closeShiftModal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text3)">&times;</button>
        </div>
        <div class="modal-body" id="closeShiftBody" style="max-height:60vh;overflow-y:auto">
            <div style="text-align:center;color:var(--text3);padding:20px">Memuat rekonsiliasi…</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('closeShiftModal')">Batal</button>
            <button class="btn btn-primary" id="btnDoCloseShift" onclick="doCloseShift()" disabled>
                <i class="fas fa-lock"></i> Tutup &amp; Cetak
            </button>
        </div>
    </div>
</div>

<script>
// ============================================================
// SHIFT KASIR (Buka/Tutup Kasir → POS Opening/Closing Entry)
// ============================================================
let currentShift = null;

async function checkShift() {
    try {
        const r = await fetch('{{ route("pos-shift.current") }}', { headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        const d = await r.json();
        currentShift = d.has_open_shift ? d.shift : null;
        updateShiftUI();
        if (!currentShift) showOpenShift();
    } catch(e) { console.error(e); }
}

function updateShiftUI() {
    const label = document.getElementById('shiftBtnLabel');
    const btn = document.getElementById('btnShift');
    if (!label || !btn) return;
    if (currentShift) {
        label.textContent = currentShift.offline ? 'Tutup Kasir (offline)' : 'Tutup Kasir';
        btn.style.color = 'var(--red)';
        btn.title = currentShift.offline
            ? 'Shift ini dibuka saat internet mati; akan disusulkan ke ERP HPY otomatis saat online.'
            : '';
    } else { label.textContent = 'Buka Kasir'; btn.style.color = ''; btn.title = ''; }
}

function onShiftButton() { currentShift ? showCloseShift() : showOpenShift(); }

function showOpenShift() {
    document.getElementById('openShiftModal').classList.add('show');
    setTimeout(() => document.getElementById('openingCashInput').focus(), 100);
}

async function doOpenShift() {
    const btn = document.getElementById('btnDoOpenShift');
    const cash = parseFloat(document.getElementById('openingCashInput').value || '0');
    if (isNaN(cash) || cash < 0) { toast('Modal kas tidak valid.', 'err'); return; }
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membuka…';
    try {
        const r = await fetch('{{ route("pos-shift.open") }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ opening_cash: cash })
        });
        const d = await r.json();
        if (d.success) {
            toast(d.message || 'Kasir dibuka.', d.offline ? 'err' : 'ok');
            closeModal('openShiftModal');
            await checkShift();
        }
        else { toast(d.error || 'Gagal buka kasir.', 'err'); }
    } catch(e) { toast('Gagal terhubung ke server.', 'err'); }
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-unlock"></i> Buka Kasir';
}

async function showCloseShift() {
    document.getElementById('closeShiftModal').classList.add('show');
    const body = document.getElementById('closeShiftBody');
    document.getElementById('btnDoCloseShift').disabled = true;
    body.innerHTML = '<div style="text-align:center;color:var(--text3);padding:20px"><i class="fas fa-spinner fa-spin"></i> Memuat rekonsiliasi…</div>';
    try {
        const r = await fetch('{{ route("pos-shift.reconcile") }}', { headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        const d = await r.json();
        if (!d.success) { body.innerHTML = `<div style="color:var(--red);padding:12px">${d.error||'Gagal memuat.'}</div>`; return; }
        renderCloseBody(d);
        document.getElementById('btnDoCloseShift').disabled = false;
    } catch(e) { body.innerHTML = '<div style="color:var(--red);padding:12px">Gagal terhubung ke server.</div>'; }
}

function renderCloseBody(d) {
    const t = d.totals;
    const rows = d.modes.map(m => `
        <tr>
            <td style="padding:6px 4px">${m.mode_of_payment}${m.is_cash?' <span style="color:var(--text3);font-size:11px">(kas)</span>':''}</td>
            <td style="text-align:right;padding:6px 4px;color:var(--text3)">${fmt(m.expected_amount)}</td>
            <td style="padding:6px 4px">
                <input type="number" class="form-control close-count" data-mode="${m.mode_of_payment}" data-expected="${m.expected_amount}"
                    value="${m.is_cash ? '' : m.expected_amount}" ${m.is_cash?'placeholder="hitung fisik…"':''}
                    min="0" step="1000" style="text-align:right;font-size:13px;padding:6px" oninput="updateCloseDiff()">
            </td>
            <td style="text-align:right;padding:6px 4px" class="close-diff" data-mode="${m.mode_of_payment}">—</td>
        </tr>`).join('');
    document.getElementById('closeShiftBody').innerHTML = `
        <div style="display:flex;gap:12px;margin-bottom:12px">
            <div style="flex:1;background:var(--surface2);border-radius:8px;padding:10px">
                <div style="font-size:11px;color:var(--text3)">Transaksi</div>
                <div style="font-size:18px;font-weight:800">${t.invoice_count}</div>
            </div>
            <div style="flex:2;background:var(--surface2);border-radius:8px;padding:10px">
                <div style="font-size:11px;color:var(--text3)">Total Penjualan</div>
                <div style="font-size:18px;font-weight:800">Rp ${fmt(t.grand_total)}</div>
            </div>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr style="border-bottom:1px solid var(--border);font-size:11px;color:var(--text3);text-transform:uppercase">
                <th style="text-align:left;padding:6px 4px">Metode</th>
                <th style="text-align:right;padding:6px 4px">Sistem</th>
                <th style="text-align:left;padding:6px 4px">Hitung Fisik</th>
                <th style="text-align:right;padding:6px 4px">Selisih</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table>
        <p style="font-size:11px;color:var(--text3);margin-top:10px"><i class="fas fa-info-circle"></i> Metode non-tunai biarkan sesuai sistem. Untuk <b>kas</b>, isi jumlah uang fisik yang dihitung.</p>`;
    updateCloseDiff();
}

function updateCloseDiff() {
    document.querySelectorAll('.close-count').forEach(inp => {
        const exp = parseFloat(inp.dataset.expected || '0');
        const val = inp.value === '' ? exp : parseFloat(inp.value || '0');
        const diff = val - exp;
        const cell = document.querySelector(`.close-diff[data-mode="${CSS.escape(inp.dataset.mode)}"]`);
        if (cell) {
            cell.textContent = (diff < 0 ? '-' : '') + fmt(Math.abs(diff));
            cell.style.color = diff === 0 ? 'var(--text3)' : (diff < 0 ? 'var(--red)' : 'var(--primary)');
        }
    });
}

async function doCloseShift() {
    const btn = document.getElementById('btnDoCloseShift');
    const counted = {};
    document.querySelectorAll('.close-count').forEach(inp => {
        const exp = parseFloat(inp.dataset.expected || '0');
        counted[inp.dataset.mode] = inp.value === '' ? exp : parseFloat(inp.value || '0');
    });
    if (!confirm('Tutup kasir sekarang? Transaksi akan dikonsolidasi di ERP dan shift ditutup.')) return;
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menutup…';
    try {
        const r = await fetch('{{ route("pos-shift.close") }}', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ counted })
        });
        const d = await r.json();
        if (d.success) {
            toast('Kasir ditutup.');
            closeModal('closeShiftModal');
            currentShift = null; updateShiftUI();
            if (d.print_url) window.open(d.print_url, '_blank');
            setTimeout(showOpenShift, 600);
        } else { toast(d.error || 'Gagal tutup kasir.', 'err'); btn.disabled = false; }
    } catch(e) { toast('Gagal terhubung ke server.', 'err'); btn.disabled = false; }
    btn.innerHTML = '<i class="fas fa-lock"></i> Tutup & Cetak';
}

document.addEventListener('DOMContentLoaded', checkShift);
</script>
