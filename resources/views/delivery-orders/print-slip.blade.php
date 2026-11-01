<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Print Slip – {{ $order->order_no }}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    background: #fff;
    color: #000;
}

.slip {
    width: 76mm;
    padding: 6mm 4mm;
    margin: 0 auto;
}

.slip-header { font-size: 10px; margin-bottom: 4mm; }

.order-no {
    font-size: 28px;
    font-weight: 900;
    text-align: center;
    letter-spacing: 1px;
    line-height: 1.1;
    margin: 2mm 0;
    word-break: break-all;
}

.order-date { text-align: center; font-size: 11px; margin-bottom: 1mm; }

.customer-name { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 1mm; }
.customer-phone { text-align: center; font-size: 10px; margin-bottom: 3mm; }

.divider {
    border: none;
    border-top: 1px dashed #000;
    margin: 2mm 0;
}

.section-label {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin: 2mm 0 1mm;
}

.table-header {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: bold;
    padding: 1mm 0;
}

.item-row {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    padding: 1.5mm 0;
    align-items: flex-start;
}
.item-name { flex: 1; padding-right: 4px; }
.item-qty  { font-weight: bold; white-space: nowrap; min-width: 16px; text-align: right; }

/* Tujuan Pengiriman */
.ship-block {
    margin: 2mm 0;
    padding: 2mm 0;
    border-top: 1px dotted #aaa;
}
.ship-block:first-child { border-top: none; }
.ship-seq   { font-size: 10px; font-weight: bold; margin-bottom: 1mm; }
.ship-name  { font-size: 11px; font-weight: bold; }
.ship-phone { font-size: 10px; }
.ship-addr  { font-size: 10px; margin-top: 1mm; }
.ship-date  { font-size: 10px; margin-top: 1mm; }

/* Slip QC extras */
.meta-row { display: flex; justify-content: space-between; margin-bottom: 2mm; font-size: 11px; }
.meta-col { flex: 1; }
.meta-col:last-child { text-align: right; }
.meta-label { font-size: 9px; color: #444; }
.meta-value { font-weight: bold; font-size: 12px; }

.qr-block { text-align: center; margin: 3mm 0 1mm; }
.qr-block svg { width: 100px; height: 100px; }
.qr-id {
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    letter-spacing: 3px;
    margin-bottom: 3mm;
}

@media print {
    .no-print { display: none !important; }
    .slip { page-break-after: always; }
    .slip:last-child { page-break-after: avoid; }
    body { margin: 0; }
}

@media screen {
    body { background: #f0f0f0; padding: 20px; }
    .slip { background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.2); margin-bottom: 20px; }
    .no-print { text-align: center; margin-bottom: 16px; }
}
</style>
</head>
<body>

<div class="no-print" style="font-family:sans-serif">
    <button onclick="window.print()" style="padding:8px 24px;font-size:14px;cursor:pointer;background:#1a73e8;color:#fff;border:none;border-radius:4px;margin-right:8px">
        🖨️ Print
    </button>
    <button id="thermalBtn" onclick="directPrint()" style="padding:8px 24px;font-size:14px;cursor:pointer;background:#0F9D58;color:#fff;border:none;border-radius:4px;margin-right:8px">
        🧾 Print Thermal
    </button>
    <button onclick="window.close()" style="padding:8px 16px;font-size:14px;cursor:pointer;background:#f5f5f5;border:1px solid #ccc;border-radius:4px">
        Tutup
    </button>
</div>

@php
    $items     = $order->items;
    $shipments = $order->shipments->sortBy('sequence');
    $orderNo   = $order->order_no;
    $orderDate = ($order->order_date ?? $order->created_at)->format('d/m/Y');
    $idPadded  = str_pad($order->id, 6, '0', STR_PAD_LEFT);
    $custName  = $order->customer?->name ?? '-';
    $custPhone = $order->customer?->phone ?? '';
@endphp

{{-- Partial: Tujuan Pengiriman (reused di kedua slip) --}}
@php
function shipDate($ship) {
    if (!$ship->delivery_date) return '';
    $d = $ship->delivery_date->format('d/m/Y');
    $t = $ship->delivery_date->format('H:i');
    return $t !== '00:00' ? $d . ' ' . $t : $d;
}
@endphp

{{-- ══════════════════════════════════════════════ --}}
{{-- SLIP 1 : GUDANG                               --}}
{{-- ══════════════════════════════════════════════ --}}
<div class="slip">

    <div class="slip-header">GUDANG – {{ $printAt->format('d/m/Y H:i:s') }}</div>

    <div class="order-no">{{ $orderNo }}</div>
    <div class="order-date">Tanggal Order : {{ $orderDate }}</div>
    <div class="customer-name">{{ $custName }}</div>
    @if($custPhone)
    <div class="customer-phone">{{ $custPhone }}</div>
    @endif

    <hr class="divider">
    <div class="table-header"><span>Barang</span><span>Qty</span></div>
    <hr class="divider">

    @foreach($items as $i => $item)
    <div class="item-row">
        <span class="item-name">{{ $i + 1 }}. {{ $item->product_name }}</span>
        <span class="item-qty">{{ rtrim(rtrim(number_format($item->qty, 2, ',', ''), '0'), ',') }}</span>
    </div>
    @endforeach

    @if($shipments->count())
    <hr class="divider">
    <div class="section-label">Tujuan Pengiriman</div>
    @foreach($shipments as $ship)
    <div class="ship-block">
        <div class="ship-seq">Tujuan #{{ $ship->sequence }}</div>
        <div class="ship-name">{{ $ship->recipient_name }}
            @if($ship->recipient_phone)<span class="ship-phone"> · {{ $ship->recipient_phone }}</span>@endif
        </div>
        <div class="ship-addr">{{ $ship->shipping_address }}</div>
        @if($ship->delivery_date)
        <div class="ship-date">Kirim: {{ shipDate($ship) }}</div>
        @endif
    </div>
    @endforeach
    @endif

</div>

{{-- ══════════════════════════════════════════════ --}}
{{-- SLIP 2 : QC                                   --}}
{{-- ══════════════════════════════════════════════ --}}
<div class="slip">

    <div class="slip-header">QC – {{ $printAt->format('d/m/Y H:i:s') }}</div>

    <div class="meta-row">
        <div class="meta-col">
            <div class="meta-label">Nomor Order</div>
            <div class="meta-value">{{ $orderNo }}</div>
        </div>
        <div class="meta-col">
            <div class="meta-label">Tanggal Order</div>
            <div class="meta-value">{{ $orderDate }}</div>
        </div>
    </div>
    <div class="customer-name" style="text-align:left">{{ $custName }}</div>
    @if($custPhone)
    <div class="customer-phone" style="text-align:left;margin-bottom:3mm">{{ $custPhone }}</div>
    @endif

    <div class="qr-block">
        {!! QrCode::size(100)->generate($orderNo) !!}
    </div>
    <div class="qr-id">{{ $idPadded }}</div>

    <hr class="divider">
    <div class="table-header"><span>Barang</span><span>Qty</span></div>
    <hr class="divider">

    @foreach($items as $i => $item)
    <div class="item-row">
        <span class="item-name">{{ $i + 1 }}. {{ $item->product_name }}</span>
        <span class="item-qty">{{ rtrim(rtrim(number_format($item->qty, 2, ',', ''), '0'), ',') }}</span>
    </div>
    @endforeach

    @if($shipments->count())
    <hr class="divider">
    <div class="section-label">Tujuan Pengiriman</div>
    @foreach($shipments as $ship)
    <div class="ship-block">
        <div class="ship-seq">Tujuan #{{ $ship->sequence }}</div>
        <div class="ship-name">{{ $ship->recipient_name }}
            @if($ship->recipient_phone)<span class="ship-phone"> · {{ $ship->recipient_phone }}</span>@endif
        </div>
        <div class="ship-addr">{{ $ship->shipping_address }}</div>
        @if($ship->delivery_date)
        <div class="ship-date">Kirim: {{ shipDate($ship) }}</div>
        @endif
    </div>
    @endforeach
    @endif

</div>

<script>
function directPrint() {
    const btn = document.getElementById('thermalBtn');
    const original = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Mengirim…';

    fetch('{{ route('delivery-orders.direct-print-slip', $order) }}')
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Gagal mencetak ke printer.');
            }
            alert(data.message || 'Slip berhasil dikirim ke printer.');
        })
        .catch((err) => alert('❌ ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = original;
        });
}

window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 400);
});
</script>

</body>
</html>
