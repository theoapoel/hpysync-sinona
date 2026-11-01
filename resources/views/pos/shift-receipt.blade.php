<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tutup Kasir {{ $shift->id }}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    @page { size: 58mm auto; margin: 0; }
    body { font-family:'Courier New', monospace; font-size:10px; line-height:1.15; color:#000; background:#fff; padding:0; }
    .receipt { width:48mm; margin:0 auto; }
    h1 { font-size:12px; text-align:center; margin-bottom:1px; }
    .sub { text-align:center; font-size:9px; margin-bottom:4px; }
    hr { border:none; border-top:1px dashed #000; margin:6px 0; }
    .row { display:flex; justify-content:space-between; gap:8px; }
    .row .r { text-align:right; }
    .muted { color:#555; }
    .bold { font-weight:bold; }
    table { width:100%; border-collapse:collapse; font-size:9px; }
    th, td { text-align:right; padding:0; }
    th:first-child, td:first-child { text-align:left; }
    .diff-pos { color:#000; }
    .center { text-align:center; }
    @media print { body { padding:0; } .receipt { width:48mm; } .no-print { display:none; } }
</style>
</head>
<body onload="window.print()">
<div class="receipt">
    <h1>LAPORAN TUTUP KASIR</h1>
    <div class="sub">{{ $shift->pos_profile ?: 'POS' }}</div>

    <hr>
    <div class="row"><span>Kasir</span><span class="r">{{ $shift->user->name ?? '—' }}</span></div>
    <div class="row"><span>Shift #</span><span class="r">{{ $shift->id }}</span></div>
    <div class="row"><span>Buka</span><span class="r">{{ $shift->opened_at->isoFormat('D/M/YY HH:mm') }}</span></div>
    <div class="row"><span>Tutup</span><span class="r">{{ optional($shift->closed_at)->isoFormat('D/M/YY HH:mm') ?? '—' }}</span></div>
    @if($shift->erp_closing_entry)
    <div class="row"><span>Closing ERP</span><span class="r">{{ $shift->erp_closing_entry }}</span></div>
    @endif

    <hr>
    <div class="row bold"><span>Penjualan</span><span class="r">Rp {{ number_format($shift->total_sales, 0, ',', '.') }}</span></div>
    <div class="row muted"><span>Jml transaksi</span><span class="r">{{ $shift->invoice_count }}</span></div>
    <div class="row muted"><span>Modal awal (kas)</span><span class="r">Rp {{ number_format($shift->opening_cash, 0, ',', '.') }}</span></div>

    <hr>
    <div class="bold center">REKONSILIASI PER METODE</div>
    <table>
        <thead>
            <tr><th>Metode</th><th>Sistem</th><th>Hitung</th><th>Selisih</th></tr>
        </thead>
        <tbody>
        @foreach(($shift->payment_breakdown ?? []) as $b)
            <tr>
                <td>{{ $b['mode'] }}</td>
                <td>{{ number_format($b['expected'], 0, ',', '.') }}</td>
                <td>{{ number_format($b['counted'], 0, ',', '.') }}</td>
                <td>{{ ($b['difference'] < 0 ? '-' : '') }}{{ number_format(abs($b['difference']), 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <hr>
    <div class="row bold"><span>KAS SISTEM</span><span class="r">Rp {{ number_format($shift->expected_cash, 0, ',', '.') }}</span></div>
    <div class="row bold"><span>KAS DIHITUNG</span><span class="r">Rp {{ number_format($shift->counted_cash, 0, ',', '.') }}</span></div>
    <div class="row bold">
        <span>SELISIH KAS</span>
        <span class="r">{{ ($shift->cash_difference < 0 ? '-' : '') }}Rp {{ number_format(abs($shift->cash_difference), 0, ',', '.') }}</span>
    </div>

    <hr>
    <div class="center muted">Dicetak {{ now()->isoFormat('D/M/YY HH:mm') }}</div>
    <div class="center no-print" style="margin-top:10px">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>
</div>
</body>
</html>
