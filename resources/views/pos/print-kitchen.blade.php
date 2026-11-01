<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kitchen {{ $transaction->invoice_no }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* Kertas termal 58mm — area cetak efektif ~48mm */
        @page { size: 58mm auto; margin: 0; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px; line-height: 1.15;
            width: 48mm; margin: 0 auto; padding: 0;
            background: #fff; color: #000;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .big { font-size: 13px; }
        .sm { font-size: 9px; }
        .divider { border: none; border-top: 1px dashed #000; margin: 2px 0; }
        .row { display: flex; justify-content: space-between; gap: 4px; margin: 0; }
        .row > span:last-child { white-space: nowrap; }
        .cat { font-weight: bold; margin: 5px 0 1px; }
        .item { display: flex; justify-content: space-between; align-items: flex-start; margin: 1px 0; }
        .item .name { padding-right: 4px; }
        .item .qty { font-weight: bold; min-width: 16px; text-align: right; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 12px; margin-top: 3px; }
        @media print {
            body { width: 48mm; margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <div class="center bold big">--- COPY KITCHEN ---</div>
    <hr class="divider">

    <div class="row"><span>Struk No</span><span class="bold">{{ $transaction->invoice_no }}</span></div>
    <div class="row"><span>Tgl</span><span>{{ local_dt($transaction->created_at, 'd-m-Y') }}</span></div>
    @if($table !== '')
        <div class="row"><span>Meja</span><span class="bold">{{ $table }}</span></div>
    @elseif($transaction->customer)
        <div class="row"><span>Customer</span><span class="bold">{{ $transaction->customer->name }}</span></div>
    @endif
    <div class="row"><span>Kasir</span><span>{{ $transaction->user->name }}</span></div>
    @if($transaction->order_type)
        <div class="row"><span>Tipe</span><span>{{ ['dine_in'=>'Dine In','take_away'=>'Take Away','delivery'=>'Delivery'][$transaction->order_type] ?? $transaction->order_type }}</span></div>
    @endif
    <div class="row"><span>Order Time</span><span>{{ local_dt($transaction->created_at, 'H:i:s') }}</span></div>

    <hr class="divider">
    <div class="row bold"><span>Description</span><span>Qty</span></div>
    <hr class="divider">

    {{-- Item dikelompokkan per kategori --}}
    @foreach($groups as $categoryName => $items)
        <div class="cat">=== {{ strtoupper($categoryName) }} ==={{ $table !== '' ? ' Meja '.$table : '' }}</div>
        @foreach($items as $item)
        <div class="item">
            <span class="name">{{ strtoupper($item->product_name) }}</span>
            <span class="qty">{{ $item->quantity }}</span>
        </div>
        @endforeach
    @endforeach

    <hr class="divider">
    <div class="total-row"><span>Total Qty</span><span>{{ $transaction->items->sum('quantity') }}</span></div>

    @if($transaction->notes)
    <hr class="divider">
    <div class="sm bold">Catatan:</div>
    <div class="sm">{{ $transaction->notes }}</div>
    @endif

    <div class="no-print" style="text-align:center;margin-top:20px">
        <button onclick="window.print()" style="padding:8px 20px;background:#4285F4;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print</button>
        <button onclick="window.close()" style="padding:8px 20px;border:1px solid #ccc;border-radius:6px;cursor:pointer;font-size:14px;margin-left:8px">Tutup</button>
    </div>
    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
