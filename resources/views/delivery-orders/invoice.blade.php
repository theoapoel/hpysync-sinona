<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    @php
        $isProforma = ($type ?? 'invoice') === 'proforma';
        $docTitle   = $isProforma ? 'Proforma Invoice' : 'Invoice';
        $docNo      = ($isProforma ? 'PI-' : 'INV-') . $order->order_no;
        $rp         = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $qty        = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $psLabel    = ['paid' => 'LUNAS', 'partial' => 'DP / SEBAGIAN'][$order->payment_status] ?? 'BELUM LUNAS';
        $directUrl  = $isProforma
            ? route('delivery-orders.direct-print-proforma', $order)
            : route('delivery-orders.direct-print-invoice', $order);
    @endphp
    <title>{{ $docTitle }} {{ $order->order_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Kertas termal 58mm — area cetak efektif ~48mm (sama dengan struk kasir) */
        @page { size: 58mm auto; margin: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px; line-height: 1.15;
            width: 48mm; margin: 0 auto; padding: 0;
            background: #fff; color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .big { font-size: 12px; }
        .sm { font-size: 9px; }
        .divider { border: none; border-top: 1px dashed #000; margin: 2px 0; }
        .row { display: flex; justify-content: space-between; gap: 4px; margin: 0; }
        .row > span:last-child { white-space: nowrap; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 11px; margin: 2px 0; }
        .status { font-size: 13px; font-weight: bold; letter-spacing: 1px; margin: 3px 0; }
        @media print {
            body { width: 48mm; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Header toko --}}
    @if(!empty($store['store_logo']))
    <div class="center" style="margin-bottom:6px">
        <img src="{{ asset($store['store_logo']) }}" style="max-height:50px;max-width:200px;object-fit:contain">
    </div>
    @endif
    @if(!empty($store['store_name']))
        <div class="center bold big">{{ $store['store_name'] }}</div>
    @endif
    @if(!empty($store['store_tagline']))
        <div class="center sm">{{ $store['store_tagline'] }}</div>
    @endif
    @if(!empty($store['store_address']))
        <div class="center sm" style="margin-top:2px;">{{ $store['store_address'] }}</div>
    @endif
    @if(!empty($store['store_phone']))
        <div class="center sm">Telp: {{ $store['store_phone'] }}</div>
    @endif
    @if(!empty($store['store_email']))
        <div class="center sm">{{ $store['store_email'] }}</div>
    @endif

    <hr class="divider">

    {{-- Judul dokumen --}}
    <div class="center bold">{{ strtoupper($docTitle) }}</div>
    <div class="center">{{ $docNo }}</div>

    <hr class="divider">

    {{-- Info order --}}
    <div class="row"><span>No. Order</span><span>{{ $order->order_no }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ ($order->order_date ?? $order->created_at)->format('d/m/Y') }}</span></div>
    @if($order->delivery_date)
        <div class="row"><span>Tgl Kirim</span><span>{{ $order->delivery_date->format('d/m/Y') }}</span></div>
    @endif
    @if($order->customer)
        <div class="row"><span>Customer</span><span>{{ $order->customer->name }}</span></div>
        @if($order->customer->phone)
            <div class="row"><span>Telp</span><span>{{ $order->customer->phone }}</span></div>
        @endif
    @endif

    <hr class="divider">

    {{-- Item --}}
    @foreach($order->items as $item)
    <div>{{ $item->product_name }}</div>
    <div class="row sm" style="padding-left:3px">
        <span>{{ $qty($item->qty) }} x {{ number_format($item->price, 0, ',', '.') }}</span>
        <span>{{ $rp($item->subtotal) }}</span>
    </div>
    @endforeach

    <hr class="divider">

    {{-- Totals --}}
    <div class="row"><span>Total Qty</span><span>{{ $qty($order->items->sum('qty')) }}</span></div>
    <div class="row"><span>Subtotal</span><span>{{ $rp($order->subtotal ?? $order->total) }}</span></div>
    <div class="total-row"><span>TOTAL</span><span>{{ $rp($order->total) }}</span></div>

    @if($isProforma)
        <hr class="divider">
        <div class="center sm">Proforma invoice (estimasi tagihan), bukan bukti pembayaran yang sah.</div>
    @else
        <div class="row"><span>Dibayar</span><span>{{ $rp($totalPaid) }}</span></div>
        <div class="row bold"><span>Sisa Tagihan</span><span>{{ $rp($outstanding) }}</span></div>

        <hr class="divider">
        <div class="center status">{{ $psLabel }}</div>

        @if($order->payments->count())
            <hr class="divider">
            <div class="bold">RIWAYAT PEMBAYARAN</div>
            @foreach($order->payments as $pmt)
            <div class="row">
                <span>{{ $pmt->methodLabel() }}</span>
                <span>{{ $rp($pmt->amount) }}</span>
            </div>
            <div class="sm" style="padding-left:6px">
                {{ optional($pmt->payment_date)->format('d/m/Y') ?? '-' }}@if($pmt->reference_no) · Ref: {{ $pmt->reference_no }}@endif
            </div>
            @endforeach
        @endif
    @endif

    <hr class="divider">

    {{-- Footer --}}
    @if(!empty($store['receipt_footer']))
    <div class="center" style="margin-top:8px;">{{ $store['receipt_footer'] }}</div>
    @endif
    <div class="center sm" style="margin-top:4px;">Dicetak {{ $printAt->format('d/m/Y H:i') }}</div>
    <div class="center sm" style="margin-top:4px;color:#888;">Powered by HPY Solution</div>

    <div class="no-print" style="text-align:center;margin-top:20px">
        <button onclick="window.print()" style="padding:8px 20px;background:#4285F4;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print</button>
        <button id="thermalBtn" onclick="directPrint()" style="padding:8px 20px;background:#0F9D58;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin-left:8px">🧾 Print Thermal</button>
        <button onclick="window.close()" style="padding:8px 20px;border:1px solid #ccc;border-radius:6px;cursor:pointer;font-size:14px;margin-left:8px">Tutup</button>
    </div>
    <script>
        function directPrint() {
            const btn = document.getElementById('thermalBtn');
            const original = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Mengirim…';

            fetch(@json($directUrl))
                .then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Gagal mencetak ke printer.');
                    }
                    alert(data.message || '{{ $docTitle }} berhasil dikirim ke printer.');
                })
                .catch((err) => alert('❌ ' + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = original;
                });
        }

        window.onload = () => window.print();
    </script>
</body>
</html>
