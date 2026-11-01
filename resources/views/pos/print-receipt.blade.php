<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk {{ $transaction->invoice_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Kertas termal 58mm — area cetak efektif ~48mm */
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
        @media print {
            body { width: 48mm; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    @if($store['store_logo'])
    <div class="center" style="margin-bottom:6px">
        <img src="{{ asset($store['store_logo']) }}" style="max-height:50px;max-width:200px;object-fit:contain">
    </div>
    @endif
    <div class="center bold big">{{ $store['store_name'] }}</div>
    @if($store['store_tagline'])
        <div class="center sm">{{ $store['store_tagline'] }}</div>
    @endif
    @if($store['store_address'])
        <div class="center sm" style="margin-top:2px;">{{ $store['store_address'] }}</div>
    @endif
    @if($store['store_phone'])
        <div class="center sm">Telp: {{ $store['store_phone'] }}</div>
    @endif
    @if($store['store_email'])
        <div class="center sm">{{ $store['store_email'] }}</div>
    @endif

    <hr class="divider">

    {{-- Info transaksi --}}
    <div class="row"><span>Invoice</span><span>{{ $transaction->invoice_no }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ local_dt($transaction->created_at) }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $transaction->user->name }}</span></div>
    @if($transaction->customer)
        <div class="row"><span>Customer</span><span>{{ $transaction->customer->name }}</span></div>
    @endif

    <hr class="divider">

    {{-- Item --}}
    @foreach($transaction->items as $item)
    <div>{{ $item->product_name }}</div>
    <div class="row sm" style="padding-left:3px">
        <span>{{ $item->quantity }} x {{ number_format($item->price,0,',','.') }}</span>
        <span>Rp {{ number_format($item->subtotal,0,',','.') }}</span>
    </div>
    @endforeach

    <hr class="divider">

    {{-- Totals --}}
    @if($transaction->order_type)
        <div class="row"><span>Tipe</span><span>{{ ['dine_in'=>'Dine In','take_away'=>'Take Away','delivery'=>'Delivery'][$transaction->order_type] ?? $transaction->order_type }}</span></div>
    @endif
    <div class="row"><span>Total Qty</span><span>{{ $transaction->items->sum('quantity') }}</span></div>
    <div class="row"><span>Subtotal</span><span>Rp {{ number_format($transaction->subtotal,0,',','.') }}</span></div>
    @if($transaction->discount_amount > 0)
        <div class="row"><span>Diskon</span><span>- Rp {{ number_format($transaction->discount_amount,0,',','.') }}</span></div>
    @endif
    @if($transaction->coupon_code && $transaction->coupon_discount > 0)
        <div class="row"><span>Kupon ({{ $transaction->coupon_code }})</span><span>- Rp {{ number_format($transaction->coupon_discount,0,',','.') }}</span></div>
    @endif
    @if($transaction->tax_amount > 0)
        <div class="row"><span>Pajak</span><span>Rp {{ number_format($transaction->tax_amount,0,',','.') }}</span></div>
    @endif
    @if($transaction->service_charge_amount > 0)
        <div class="row"><span>Service Charge ({{ $transaction->service_charge_pct }}%)</span><span>Rp {{ number_format($transaction->service_charge_amount,0,',','.') }}</span></div>
    @endif
    @if($transaction->pb1_amount > 0)
        <div class="row"><span>PB1 ({{ $transaction->pb1_pct }}%)</span><span>Rp {{ number_format($transaction->pb1_amount,0,',','.') }}</span></div>
    @endif

    <hr class="divider">

    <div class="total-row"><span>TOTAL</span><span>Rp {{ number_format($transaction->total,0,',','.') }}</span></div>
    <div class="row"><span>Bayar ({{ strtoupper($transaction->payment_method) }})</span><span>Rp {{ number_format($transaction->paid_amount,0,',','.') }}</span></div>
    @if($transaction->change_amount > 0)
        <div class="row"><span>Kembalian</span><span>Rp {{ number_format($transaction->change_amount,0,',','.') }}</span></div>
    @endif

    <hr class="divider">

    {{-- Footer --}}
    @if($store['receipt_footer'])
    <div class="center" style="margin-top:8px;">{{ $store['receipt_footer'] }}</div>
    @endif
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

            fetch('{{ route('pos.direct-print', $transaction->id) }}')
                .then(async (res) => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || 'Gagal mencetak ke printer.');
                    }
                    alert(data.message || 'Struk berhasil dikirim ke printer.');
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
