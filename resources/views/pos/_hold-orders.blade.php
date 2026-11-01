{{-- =========================================================
     HOLD / RECALL ORDERS (client-side, localStorage)
     Shared across POS layouts (index / quick / express).
     Relies on shared globals defined in each layout's main
     <script>: cart, selectedCustomer, selectedOrderType,
     selectedDeliveryPlatform, appliedCoupon, walkinCustomerName,
     and helpers renderCart(), recalculate(), renderCustomerBtn(),
     selectOrderType(), selectDeliveryPlatform(), removeCoupon().
     ========================================================= --}}
<style>
    .hold-btn-group { display:inline-flex; align-items:center; gap:6px; margin-left:8px; vertical-align:middle; }
    .hold-pill {
        display:inline-flex; align-items:center; gap:5px; padding:5px 11px; border-radius:20px;
        font-size:12px; font-weight:800; cursor:pointer; border:2px solid var(--border);
        background:var(--surface2); color:var(--text2); font-family:'Nunito',sans-serif; white-space:nowrap;
        transition:filter .12s;
    }
    .hold-pill:hover { filter:brightness(.97); }
    .hold-pill.hold-do { border-color:var(--secondary,#f59e0b); color:var(--secondary,#f59e0b); background:transparent; }
    .hold-pill.hold-recall { border-color:var(--primary); color:#fff; background:var(--primary); }
    .hold-pill.hold-kitchen { border-color:var(--secondary,#f59e0b); color:#fff; background:var(--secondary,#f59e0b); }
    .hold-pill .hold-count {
        background:#fff; color:var(--primary); border-radius:10px; padding:0 6px; font-size:11px; font-weight:900; line-height:18px;
    }

    .held-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999;
        display:none; align-items:center; justify-content:center; padding:16px;
    }
    .held-overlay.show { display:flex; }
    .held-box {
        background:var(--surface,#fff); border-radius:16px; width:100%; max-width:560px; max-height:82vh;
        display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.3);
    }
    .held-head { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .held-head h3 { font-size:16px; font-weight:900; color:var(--text,#111); display:flex; align-items:center; gap:8px; }
    .held-close { border:none; background:var(--surface2); width:32px; height:32px; border-radius:8px; cursor:pointer; font-size:16px; color:var(--text2); }
    .held-list { padding:12px 16px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; }
    .held-empty { text-align:center; padding:40px 20px; color:var(--text3); font-weight:700; }
    .held-card { border:2px solid var(--border); border-radius:12px; padding:12px 14px; display:flex; align-items:center; gap:12px; }
    .held-card-info { flex:1; min-width:0; }
    .held-card-title { font-size:14px; font-weight:900; color:var(--text,#111); display:flex; align-items:center; gap:6px; }
    .held-card-meta { font-size:11px; font-weight:700; color:var(--text3); margin-top:2px; }
    .held-card-total { font-size:14px; font-weight:900; color:var(--green,#16a34a); font-family:'Roboto Mono',monospace; }
    .held-card-actions { display:flex; gap:6px; }
    .held-act { border:none; border-radius:9px; padding:8px 12px; font-size:12px; font-weight:800; cursor:pointer; font-family:'Nunito',sans-serif; }
    .held-act.recall { background:var(--primary); color:#fff; }
    .held-act.kitchen { background:var(--secondary,#f59e0b); color:#fff; }
    .held-act.del { background:var(--red-light,#fee2e2); color:var(--red,#dc2626); }
    .held-tag { font-size:10px; font-weight:800; padding:2px 7px; border-radius:8px; background:var(--primary-light,#e0e7ff); color:var(--primary); }
</style>

<div class="held-overlay" id="heldOverlay" onclick="if(event.target===this) closeHeldModal()">
    <div class="held-box">
        <div class="held-head">
            <h3><i class="fas fa-pause-circle" style="color:var(--secondary,#f59e0b)"></i> Pesanan Ditahan</h3>
            <button class="held-close" onclick="closeHeldModal()">&times;</button>
        </div>
        <div class="held-list" id="heldList"></div>
    </div>
</div>

<script>
(function () {
    const HELD_KEY = 'pos_held_orders_v1';

    function getHeld() {
        try { return JSON.parse(localStorage.getItem(HELD_KEY) || '[]'); }
        catch (e) { return []; }
    }
    function setHeld(list) {
        localStorage.setItem(HELD_KEY, JSON.stringify(list));
        renderHeldBadge();
    }
    function rp(n) { return (typeof fmt === 'function') ? fmt(n) : Number(n || 0).toLocaleString('id-ID'); }
    function notify(msg, type) { if (typeof toast === 'function') toast(msg, type); }

    // Sum used only for the list preview (net item subtotal).
    function previewTotal(items) {
        return (items || []).reduce((s, i) => s + Math.max(0, (i.price * i.qty) - (i.discount || 0)), 0);
    }

    // ---- Hold the current order ----------------------------------------
    window.holdCurrentOrder = function () {
        if (!Array.isArray(cart) || cart.length === 0) {
            notify('Keranjang masih kosong', 'warn');
            return;
        }
        const tableEl = document.getElementById('tableNumber');
        const snapshot = {
            id: Date.now(),
            ts: new Date().toISOString(),
            cart: JSON.parse(JSON.stringify(cart)),
            customer: (typeof selectedCustomer !== 'undefined' && selectedCustomer)
                ? JSON.parse(JSON.stringify(selectedCustomer)) : null,
            orderType: (typeof selectedOrderType !== 'undefined') ? selectedOrderType : 'dine_in',
            deliveryPlatform: (typeof selectedDeliveryPlatform !== 'undefined') ? selectedDeliveryPlatform : null,
            tableNumber: tableEl ? tableEl.value.trim() : '',
            discountAmt: document.getElementById('discountAmt') ? document.getElementById('discountAmt').value : '',
            discountPct: document.getElementById('discountPct') ? document.getElementById('discountPct').value : '',
            coupon: (typeof appliedCoupon !== 'undefined') ? appliedCoupon : null,
        };

        const list = getHeld();
        list.unshift(snapshot);
        setHeld(list);

        // Clear current order for a fresh start.
        if (typeof clearCart === 'function') clearCart();
        if (typeof setWalkin === 'function') setWalkin();

        notify('Pesanan ditahan', 'ok');
    };

    // ---- Recall a held order -------------------------------------------
    window.recallHeldOrder = function (id) {
        const list = getHeld();
        const h = list.find(x => x.id === id);
        if (!h) return;

        if (Array.isArray(cart) && cart.length > 0) {
            if (!confirm('Keranjang saat ini akan diganti dengan pesanan yang dipanggil. Lanjutkan?')) return;
        }

        // Cart
        cart = JSON.parse(JSON.stringify(h.cart || []));

        // Customer — pakai fungsi layout supaya state ter-update di scope yang benar
        if (h.customer && h.customer.id) {
            if (typeof selectCustomer === 'function') {
                selectCustomer(h.customer.id, h.customer.name, h.customer.code || '');
            } else {
                selectedCustomer = h.customer;
                if (typeof renderCustomerBtn === 'function') renderCustomerBtn();
            }
        } else {
            if (typeof setWalkin === 'function') setWalkin();
            else selectedCustomer = { id: null, name: (typeof walkinCustomerName !== 'undefined' ? walkinCustomerName : 'Walk-in') };
        }

        // Order type (re-trigger the layout handler so UI + related bars update)
        const otBtn = document.querySelector('.order-type-btn[data-type="' + (h.orderType || 'dine_in') + '"]');
        if (otBtn && typeof selectOrderType === 'function') selectOrderType(otBtn);

        // Delivery platform
        selectedDeliveryPlatform = null;
        if (h.deliveryPlatform) {
            const pBtn = document.querySelector('.platform-btn[data-platform="' + h.deliveryPlatform + '"]');
            if (pBtn && typeof selectDeliveryPlatform === 'function') selectDeliveryPlatform(pBtn);
        }

        // Table number
        const tableEl = document.getElementById('tableNumber');
        if (tableEl) tableEl.value = h.tableNumber || '';

        // Discounts
        if (document.getElementById('discountAmt')) document.getElementById('discountAmt').value = h.discountAmt || '';
        if (document.getElementById('discountPct')) document.getElementById('discountPct').value = h.discountPct || '';

        // Coupon
        if (typeof removeCoupon === 'function') removeCoupon();
        if (h.coupon) {
            appliedCoupon = h.coupon;
            const ci = document.getElementById('couponInput');
            if (ci) { ci.value = h.coupon.code || ''; ci.disabled = true; }
            const ab = document.getElementById('couponApplyBtn');
            if (ab) ab.style.display = 'none';
        }

        if (typeof renderCart === 'function') renderCart();
        if (typeof recalculate === 'function') recalculate();

        // Remove from held store
        setHeld(list.filter(x => x.id !== id));
        closeHeldModal();
        notify('Pesanan dipanggil kembali', 'ok');
    };

    // ---- Delete a held order -------------------------------------------
    // ---- Cetak struk dapur untuk pesanan yang BELUM disubmit ------------
    // Dikirim lewat form POST ke tab baru; server merender struk tanpa
    // menyimpan transaksi apa pun.
    window.printKitchenDraft = function (items, meta) {
        const rows = (items || []).filter(i => Number(i.qty) > 0);
        if (rows.length === 0) { notify('Tidak ada item untuk dicetak', 'warn'); return; }

        const f = document.createElement('form');
        f.method = 'POST';
        f.action = '{{ route('pos.print-kitchen-draft') }}';
        f.target = '_blank';
        f.style.display = 'none';

        const add = (name, value) => {
            const el = document.createElement('input');
            el.type = 'hidden';
            el.name = name;
            el.value = value ?? '';
            f.appendChild(el);
        };

        add('_token', '{{ csrf_token() }}');
        rows.forEach((it, i) => {
            add(`items[${i}][id]`, it.id ?? '');
            add(`items[${i}][name]`, it.name ?? '');
            add(`items[${i}][qty]`, it.qty ?? 0);
        });
        add('table', (meta && meta.table) || '');
        add('order_type', (meta && meta.orderType) || 'dine_in');
        add('customer_name', (meta && meta.customerName) || '');

        document.body.appendChild(f);
        f.submit();
        setTimeout(() => f.remove(), 1000);
    };

    // Cetak dapur untuk satu pesanan yang ditahan.
    window.printHeldKitchen = function (id) {
        const h = getHeld().find(x => x.id === id);
        if (!h) { notify('Pesanan tidak ditemukan', 'warn'); return; }
        printKitchenDraft(h.cart || [], {
            table: h.tableNumber,
            orderType: h.orderType,
            customerName: h.customer ? h.customer.name : '',
        });
    };

    // Cetak dapur untuk keranjang berjalan (belum ditahan, belum disubmit).
    window.printCurrentKitchen = function () {
        if (!Array.isArray(cart) || cart.length === 0) {
            notify('Keranjang masih kosong', 'warn');
            return;
        }
        const tableEl = document.getElementById('tableNumber');
        printKitchenDraft(cart, {
            table: tableEl ? tableEl.value.trim() : '',
            orderType: (typeof selectedOrderType !== 'undefined') ? selectedOrderType : 'dine_in',
            customerName: (typeof selectedCustomer !== 'undefined' && selectedCustomer) ? selectedCustomer.name : '',
        });
    };

    window.deleteHeldOrder = function (id) {
        if (!confirm('Hapus pesanan yang ditahan ini?')) return;
        setHeld(getHeld().filter(x => x.id !== id));
        renderHeldList();
    };

    // ---- Modal ---------------------------------------------------------
    window.openHeldOrders = function () {
        renderHeldList();
        document.getElementById('heldOverlay').classList.add('show');
    };
    window.closeHeldModal = function () {
        document.getElementById('heldOverlay').classList.remove('show');
    };

    function renderHeldList() {
        const list = getHeld();
        const box = document.getElementById('heldList');
        if (!box) return;
        if (list.length === 0) {
            box.innerHTML = '<div class="held-empty"><i class="fas fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;opacity:.5"></i>Tidak ada pesanan yang ditahan</div>';
            return;
        }
        const otLabel = { dine_in: 'Dine In', take_away: 'Take Away', delivery: 'Delivery' };
        box.innerHTML = list.map(h => {
            const items = h.cart || [];
            const qty = items.reduce((s, i) => s + i.qty, 0);
            // Nama walk-in mengikuti setting POS (mis. "Hi Customer") agar
            // sama persis dengan yang tampil di layar kasir.
            const walkin = (typeof walkinCustomerName !== 'undefined' && walkinCustomerName)
                ? walkinCustomerName : 'Walk-in';
            const custName = (h.customer && h.customer.id) ? h.customer.name : walkin;
            const time = new Date(h.ts).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            const tableTxt = h.tableNumber ? (' • Meja ' + h.tableNumber) : '';
            return `
            <div class="held-card">
                <div class="held-card-info">
                    <div class="held-card-title">
                        <i class="fas fa-user" style="font-size:11px;color:var(--text3)"></i> ${custName}
                        <span class="held-tag">${otLabel[h.orderType] || h.orderType}</span>
                    </div>
                    <div class="held-card-meta">${qty} item${tableTxt} • ${time}</div>
                    <div class="held-card-total">Rp ${rp(previewTotal(items))}</div>
                </div>
                <div class="held-card-actions">
                    <button class="held-act recall" onclick="recallHeldOrder(${h.id})"><i class="fas fa-play"></i> Panggil</button>
                    <button class="held-act kitchen" onclick="printHeldKitchen(${h.id})" title="Cetak struk dapur"><i class="fas fa-utensils"></i> Dapur</button>
                    <button class="held-act del" onclick="deleteHeldOrder(${h.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        }).join('');
    }

    // ---- Header buttons (injected next to the item count) --------------
    window.renderHeldBadge = function () {
        const btn = document.getElementById('heldRecallBtn');
        if (!btn) return;
        const n = getHeld().length;
        btn.style.display = n > 0 ? 'inline-flex' : 'none';
        const c = document.getElementById('heldCount');
        if (c) c.textContent = n;
    };

    function injectButtons() {
        const anchor = document.getElementById('orderCount');
        if (!anchor || document.getElementById('holdBtnGroup')) return;
        const group = document.createElement('span');
        group.className = 'hold-btn-group';
        group.id = 'holdBtnGroup';
        group.innerHTML =
            '<button type="button" class="hold-pill hold-do" onclick="holdCurrentOrder()" title="Tahan pesanan ini">' +
                '<i class="fas fa-pause"></i> Tahan</button>' +
            '<button type="button" class="hold-pill hold-kitchen" onclick="printCurrentKitchen()" title="Cetak struk dapur (belum disubmit)">' +
                '<i class="fas fa-utensils"></i> Dapur</button>' +
            '<button type="button" class="hold-pill hold-recall" id="heldRecallBtn" onclick="openHeldOrders()" style="display:none" title="Pesanan yang ditahan">' +
                '<i class="fas fa-list"></i> Ditahan <span class="hold-count" id="heldCount">0</span></button>';
        anchor.insertAdjacentElement('afterend', group);
        renderHeldBadge();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectButtons);
    } else {
        injectButtons();
    }
})();
</script>
