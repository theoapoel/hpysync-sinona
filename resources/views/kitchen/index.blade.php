@extends('layouts.app')
@section('title', 'Kitchen Monitor')

@push('styles')
<style>
.kitchen-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    height: calc(100vh - 148px);
}
.kitchen-col {
    display: flex;
    flex-direction: column;
    border-radius: 10px;
    overflow: hidden;
    min-height: 0;
}
.kitchen-col-header {
    padding: 14px 18px;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.kitchen-col-body {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
/* Column colors */
.col-pending   { background: #FFF8E1; }
.col-pending   .kitchen-col-header { background: #F9A825; color: #fff; }
.col-preparing { background: #E3F2FD; }
.col-preparing .kitchen-col-header { background: #1565C0; color: #fff; }
.col-ready     { background: #E8F5E9; }
.col-ready     .kitchen-col-header { background: #2E7D32; color: #fff; }

.order-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(0,0,0,.10);
    overflow: hidden;
    transition: box-shadow .15s;
}
.order-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,.14); }
.order-card-head {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.order-no   { font-weight: 700; font-size: 14px; color: #202124; }
.order-date { font-size: 11px; color: #80868B; margin-top: 2px; }
.order-customer { font-size: 12px; color: #5F6368; font-weight: 600; }
.elapsed    { font-size: 11px; color: #80868B; text-align: right; white-space: nowrap; }
.elapsed.urgent { color: #C62828; font-weight: 700; }
.order-items { padding: 8px 14px; border-bottom: 1px solid #f0f0f0; }
.order-item-row { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0; color: #202124; }
.order-item-row .qty { font-weight: 700; color: #1565C0; margin-left: 8px; flex-shrink: 0; }
.order-card-actions { padding: 8px 14px; display: flex; gap: 6px; }

/* FG card accent */
.card-type-fg { border-left: 3px solid #E65100; }
.card-type-fg .order-no { color: #E65100; }

/* Col section label */
.col-section-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px;
    color: #90A4AE; padding: 2px 4px; margin-top: 4px; margin-bottom: 2px;
}

/* Today badge */
.badge-today { background: #FFEBEE; color: #C62828; font-size: 11px; padding: 2px 7px; border-radius: 99px; font-weight: 600; }

/* Buttons */
.btn-kitchen-start  { background:#1565C0; color:#fff; }
.btn-kitchen-start:hover { background:#0D47A1; }
.btn-kitchen-done   { background:#2E7D32; color:#fff; }
.btn-kitchen-done:hover { background:#1B5E20; }
.btn-kitchen-back   { background:#f5f5f5; color:#5F6368; font-size:11px; }
.btn-kitchen-back:hover { background:#e0e0e0; }

/* Auto-refresh ring */
#refreshRing {
    position:fixed; bottom:18px; right:18px;
    width:44px; height:44px; border-radius:50%;
    background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.2);
    display:flex; align-items:center; justify-content:center;
    cursor:pointer; z-index:999;
}
#refreshRing svg { transition: transform .3s; }
#refreshRing:hover svg { transform: rotate(30deg); }
</style>
@endpush

@section('content')
<div class="page-header" style="margin-bottom:14px">
    <div>
        <div class="page-title"><i class="fas fa-utensils text-blue"></i> Kitchen Monitor</div>
        <div class="page-subtitle" id="lastRefresh">Terakhir refresh: —</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <span id="soundBtn" onclick="toggleSound()" class="btn btn-ghost btn-sm" title="Notifikasi suara">
            <i class="fas fa-bell" id="soundIcon"></i>
        </span>
        <button onclick="refreshBoard()" class="btn btn-ghost btn-sm">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<div class="kitchen-board">

    {{-- ── Kolom 1 : Antrian ────────────────────────────────────── --}}
    <div class="kitchen-col col-pending">
        <div class="kitchen-col-header">
            <i class="fas fa-hourglass-start"></i>
            Antrian
            <span class="badge" style="background:rgba(255,255,255,.3);color:#fff;margin-left:auto" id="cnt-pending">
                {{ $pending->count() + $srRequested->count() }}
            </span>
        </div>
        <div class="kitchen-col-body">

            {{-- Delivery orders --}}
            @if($pending->count())
            <div class="col-section-label"><i class="fas fa-truck" style="margin-right:4px"></i>Delivery Order</div>
            @foreach($pending as $order)
                @include('kitchen._card', ['order' => $order, 'col' => 'pending'])
            @endforeach
            @endif

            {{-- FG Requests --}}
            @if($srRequested->count())
            <div class="col-section-label" style="margin-top:6px"><i class="fas fa-clipboard-check" style="margin-right:4px"></i>Permintaan FG</div>
            @foreach($srRequested as $sr)
                @include('kitchen._sr_card', ['sr' => $sr, 'col' => 'requested'])
            @endforeach
            @endif

            @if($pending->count() === 0 && $srRequested->count() === 0)
            <div style="text-align:center;color:#B0BEC5;padding:40px 0;font-size:13px">
                <i class="fas fa-check-circle" style="font-size:28px;display:block;margin-bottom:8px"></i>
                Tidak ada antrian
            </div>
            @endif
        </div>
    </div>

    {{-- ── Kolom 2 : Diproses ───────────────────────────────────── --}}
    <div class="kitchen-col col-preparing">
        <div class="kitchen-col-header">
            <i class="fas fa-fire"></i>
            Diproses
            <span class="badge" style="background:rgba(255,255,255,.3);color:#fff;margin-left:auto" id="cnt-preparing">
                {{ $preparing->count() + $srPreparing->count() }}
            </span>
        </div>
        <div class="kitchen-col-body">

            @if($preparing->count())
            <div class="col-section-label"><i class="fas fa-truck" style="margin-right:4px"></i>Delivery Order</div>
            @foreach($preparing as $order)
                @include('kitchen._card', ['order' => $order, 'col' => 'preparing'])
            @endforeach
            @endif

            @if($srPreparing->count())
            <div class="col-section-label" style="margin-top:6px"><i class="fas fa-clipboard-check" style="margin-right:4px"></i>Permintaan FG</div>
            @foreach($srPreparing as $sr)
                @include('kitchen._sr_card', ['sr' => $sr, 'col' => 'preparing'])
            @endforeach
            @endif

            @if($preparing->count() === 0 && $srPreparing->count() === 0)
            <div style="text-align:center;color:#B0BEC5;padding:40px 0;font-size:13px">
                <i class="fas fa-coffee" style="font-size:28px;display:block;margin-bottom:8px"></i>
                Belum ada yang diproses
            </div>
            @endif
        </div>
    </div>

    {{-- ── Kolom 3 : Siap ───────────────────────────────────────── --}}
    <div class="kitchen-col col-ready">
        <div class="kitchen-col-header">
            <i class="fas fa-check-circle"></i>
            Siap Kirim / Siap Jadi Stock
            <span class="badge" style="background:rgba(255,255,255,.3);color:#fff;margin-left:auto" id="cnt-ready">
                {{ $ready->count() + $srDone->count() }}
            </span>
        </div>
        <div class="kitchen-col-body">

            @if($ready->count())
            <div class="col-section-label"><i class="fas fa-truck" style="margin-right:4px"></i>Siap Kirim</div>
            @foreach($ready as $order)
                @include('kitchen._card', ['order' => $order, 'col' => 'ready'])
            @endforeach
            @endif

            @if($srDone->count())
            <div class="col-section-label" style="margin-top:6px"><i class="fas fa-clipboard-check" style="margin-right:4px"></i>Siap Jadi Stock</div>
            @foreach($srDone as $sr)
                @include('kitchen._sr_card', ['sr' => $sr, 'col' => 'done'])
            @endforeach
            @endif

            @if($ready->count() === 0 && $srDone->count() === 0)
            <div style="text-align:center;color:#B0BEC5;padding:40px 0;font-size:13px">
                <i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px"></i>
                Belum ada yang siap
            </div>
            @endif
        </div>
    </div>

</div>

{{-- Auto-refresh indicator --}}
<div id="refreshRing" onclick="refreshBoard()" title="Refresh sekarang">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5F6368" stroke-width="2.2">
        <polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
    </svg>
</div>
@endsection

@push('scripts')
<script>
const pollUrl       = '{{ route("kitchen.poll") }}';
let soundEnabled    = localStorage.getItem('kitchenSound') !== 'off';
let prevPending     = {{ $pending->count() + $srRequested->count() }};
let prevSrRequested = {{ $srRequested->count() }};

function updateSoundIcon() {
    document.getElementById('soundIcon').className = soundEnabled ? 'fas fa-bell' : 'fas fa-bell-slash';
}
function toggleSound() {
    soundEnabled = !soundEnabled;
    localStorage.setItem('kitchenSound', soundEnabled ? 'on' : 'off');
    updateSoundIcon();
}
updateSoundIcon();

function playBeep() {
    if (!soundEnabled) return;
    try {
        const ctx  = new (window.AudioContext || window.webkitAudioContext)();
        const osc  = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.value = 880;
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime); osc.stop(ctx.currentTime + 0.4);
    } catch(e) {}
}

async function updateKitchenStatus(btn) {
    const url       = btn.dataset.url;
    const newStatus = btn.dataset.status;
    const card      = btn.closest('.order-card');
    card.style.opacity = '0.6';
    btn.disabled = true;
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ kitchen_status: newStatus }),
        });
        const text = await r.text();
        let res;
        try { res = JSON.parse(text); } catch(e) { throw new Error('Server error (HTTP ' + r.status + ')'); }
        if (res.success) { refreshBoard(); }
        else { card.style.opacity='1'; btn.disabled=false; alert('Gagal: ' + (res.error ?? 'Unknown')); }
    } catch(e) {
        card.style.opacity='1'; btn.disabled=false; alert('Error: ' + e.message);
    }
}

async function updateSrStatus(btn) {
    const url       = btn.dataset.url;
    const newStatus = btn.dataset.status;
    const card      = btn.closest('.order-card');
    card.style.opacity = '0.6';
    btn.disabled = true;
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ kitchen_status: newStatus }),
        });
        const res = await r.json();
        if (res.success) { refreshBoard(); }
        else { card.style.opacity='1'; btn.disabled=false; alert('Gagal: ' + (res.error ?? 'Unknown')); }
    } catch(e) {
        card.style.opacity='1'; btn.disabled=false; alert('Error: ' + e.message);
    }
}

function refreshBoard() { location.reload(); }

async function pollCounts() {
    try {
        const r   = await fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
        const res = await r.json();

        const newPending     = (res.pending ?? 0) + (res.sr_requested ?? 0);
        const newSrRequested = res.sr_requested ?? 0;

        if (newPending > prevPending || newSrRequested > prevSrRequested) {
            playBeep();
            refreshBoard();
        }
        prevPending     = newPending;
        prevSrRequested = newSrRequested;

        document.getElementById('cnt-pending').textContent  = (res.pending ?? 0) + (res.sr_requested ?? 0);
        document.getElementById('cnt-preparing').textContent = (res.preparing ?? 0) + (res.sr_preparing ?? 0);
        document.getElementById('cnt-ready').textContent    = (res.ready ?? 0) + (res.sr_done ?? 0);
    } catch(e) {}

    document.getElementById('lastRefresh').textContent =
        'Terakhir refresh: ' + new Date().toLocaleTimeString('id-ID');
}

function updateElapsed() {
    document.querySelectorAll('[data-since]').forEach(el => {
        const since = parseInt(el.dataset.since, 10);
        if (!since) return;
        const mins = Math.floor((Date.now() / 1000 - since) / 60);
        const secs = Math.floor((Date.now() / 1000 - since) % 60);
        if (mins >= 60) {
            const h = Math.floor(mins / 60);
            el.textContent = h + ' jam ' + (mins % 60) + ' mnt';
        } else if (mins > 0) {
            el.textContent = mins + ' mnt ' + secs + 'd lalu';
        } else {
            el.textContent = secs + 'd lalu';
        }
        el.className = 'elapsed' + (mins >= 30 ? ' urgent' : '');
    });
}

pollCounts();
setInterval(pollCounts, 30000);
setInterval(updateElapsed, 5000);
updateElapsed();
</script>
@endpush
