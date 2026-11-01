<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Surat Jalan – {{ $transfer->transfer_no }}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #000;
    background: #fff;
    padding: 20px;
}

/* ── Layout ── */
.page { max-width: 800px; margin: 0 auto; }

/* ── Header ── */
.doc-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 3px solid #000;
    padding-bottom: 12px;
    margin-bottom: 14px;
}
.company-info { flex: 1; }
.company-name { font-size: 20px; font-weight: 700; letter-spacing: .5px; margin-bottom: 2px; }
.company-sub  { font-size: 11px; color: #444; }

.doc-title-block { text-align: right; }
.doc-title {
    font-size: 18px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1px; border: 2px solid #000;
    padding: 6px 16px; display: inline-block; margin-bottom: 6px;
}
.doc-meta { font-size: 11px; color: #333; }
.doc-meta td { padding: 1px 4px; }
.doc-meta td:first-child { color: #555; }
.doc-meta td:last-child { font-weight: 600; }

/* ── Info Box ── */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 14px;
}
.info-box {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 8px 12px;
}
.info-box .label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: #666; margin-bottom: 4px;
}
.info-box .value { font-size: 13px; font-weight: 600; }
.info-box .sub   { font-size: 11px; color: #444; margin-top: 2px; }

.arrow-box {
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #555;
}

/* ── Items Table ── */
.items-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.items-table th {
    background: #f0f0f0;
    border: 1px solid #999;
    padding: 7px 8px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .4px;
    text-align: left;
}
.items-table th.center, .items-table td.center { text-align: center; }
.items-table th.right,  .items-table td.right  { text-align: right; }
.items-table td {
    border: 1px solid #ccc;
    padding: 7px 8px;
    vertical-align: top;
}
.items-table tbody tr:nth-child(even) { background: #fafafa; }
.items-table tfoot td {
    border: 1px solid #999;
    padding: 7px 8px;
    font-weight: 700;
    background: #f0f0f0;
}

/* ── Notes ── */
.notes-box {
    border: 1px solid #ccc; border-radius: 4px;
    padding: 8px 12px; margin-bottom: 14px;
}
.notes-box .label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #666; margin-bottom: 4px; }

/* ── Signature ── */
.sig-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
    margin-top: 4px;
}
.sig-box { text-align: center; }
.sig-box .role {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .4px; border: 1px solid #999;
    padding: 5px 0; background: #f0f0f0;
    border-radius: 4px 4px 0 0;
}
.sig-space {
    border: 1px solid #999; border-top: none;
    height: 80px;
    border-radius: 0 0 4px 4px;
}
.sig-name {
    font-size: 11px; margin-top: 4px;
    border-top: 1px solid #999;
    padding-top: 4px; min-height: 16px;
}

/* ── Footer ── */
.doc-footer {
    margin-top: 14px;
    font-size: 10px; color: #888;
    text-align: center;
    border-top: 1px dashed #ccc;
    padding-top: 8px;
}

/* ── Status pill (screen only) ── */
.pill {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 700;
}
.pill-blue   { background: #dbeafe; color: #1d4ed8; }
.pill-green  { background: #dcfce7; color: #15803d; }
.pill-gray   { background: #f3f4f6; color: #374151; }
.pill-red    { background: #fee2e2; color: #b91c1c; }

/* ── Print ── */
@media print {
    body { padding: 10px; }
    .no-print { display: none !important; }
    @page { margin: 12mm; size: A4 portrait; }
}
</style>
</head>
<body>
<div class="page">

    {{-- ── Top action bar (screen only) ── --}}
    <div class="no-print" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;">
        <button onclick="window.print()"
            style="background:#1967D2;color:#fff;border:none;padding:8px 18px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;">
            &#128438; Cetak / Print
        </button>
        <a href="{{ route('stock-transfer.show', $transfer) }}"
            style="color:#555;font-size:13px;text-decoration:none;padding:8px 14px;border:1px solid #ccc;border-radius:20px;">
            ← Kembali
        </a>
        <span style="margin-left:4px;font-size:12px;color:#888">
            Tekan <kbd style="background:#eee;border:1px solid #ccc;border-radius:3px;padding:1px 4px">Ctrl+P</kbd> atau klik Cetak untuk mencetak
        </span>
    </div>

    {{-- ── Document Header ── --}}
    <div class="doc-header">
        <div class="company-info">
            <div class="company-name">{{ strtoupper($storeName) }}</div>
            <div class="company-sub">Dokumen Transfer Internal</div>
        </div>
        <div class="doc-title-block">
            <div class="doc-title">
                {{ $transfer->type === 'incoming' ? 'Bukti Terima Barang' : 'Surat Jalan' }}
            </div>
            <table class="doc-meta" style="margin-left:auto;">
                <tr>
                    <td>No. Dokumen</td>
                    <td>: <strong>{{ $transfer->transfer_no }}</strong></td>
                </tr>
                <tr>
                    <td>Tanggal</td>
                    <td>: {{ $transfer->created_at->format('d F Y') }}</td>
                </tr>
                <tr>
                    <td>Dibuat oleh</td>
                    <td>: {{ $transfer->user?->name ?? '—' }}</td>
                </tr>
                @if($transfer->erp_stock_entry)
                <tr>
                    <td>Ref. ERP</td>
                    <td>: {{ $transfer->erp_stock_entry }}</td>
                </tr>
                @endif
                @if($transfer->erp_source_entry)
                <tr>
                    <td>Ref. Pengiriman</td>
                    <td>: {{ $transfer->erp_source_entry }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Warehouse Route ── --}}
    <div class="info-grid" style="grid-template-columns:1fr auto 1fr;gap:8px;margin-bottom:14px;">
        <div class="info-box">
            <div class="label">Dari Gudang (Pengirim)</div>
            <div class="value">{{ $transfer->from_warehouse }}</div>
            @if($transfer->in_transit_warehouse)
            <div class="sub">Transit: {{ $transfer->in_transit_warehouse }}</div>
            @endif
        </div>
        <div class="arrow-box">&#8594;</div>
        <div class="info-box">
            <div class="label">Ke Gudang (Penerima)</div>
            <div class="value">{{ $transfer->to_warehouse }}</div>
        </div>
    </div>

    {{-- ── Items Table ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:32px;" class="center">No</th>
                <th style="width:120px;">Kode Item</th>
                <th>Nama Barang</th>
                <th style="width:70px;" class="right">Qty Kirim</th>
                @if($transfer->type === 'incoming')
                <th style="width:70px;" class="right">Qty Terima</th>
                <th style="width:60px;" class="right">Selisih</th>
                @endif
                <th style="width:50px;" class="center">Satuan</th>
                <th style="width:100px;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transfer->items as $i => $item)
            @php
                $actual = $item->actual_quantity ?? $item->quantity;
                $diff   = $actual - $item->quantity;
            @endphp
            <tr>
                <td class="center" style="color:#666;">{{ $i + 1 }}</td>
                <td style="font-family:monospace;font-size:11px;">{{ $item->item_code }}</td>
                <td style="font-weight:600;">{{ $item->item_name }}</td>
                <td class="right">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                @if($transfer->type === 'incoming')
                <td class="right" style="font-weight:700;">
                    {{ number_format($actual, 2, ',', '.') }}
                </td>
                <td class="right" style="font-weight:700;color:{{ $diff == 0 ? '#555' : ($diff > 0 ? '#15803d' : '#b91c1c') }}">
                    {{ $diff >= 0 ? '+' : '' }}{{ number_format($diff, 2, ',', '.') }}
                </td>
                @endif
                <td class="center">{{ $item->unit }}</td>
                <td style="font-size:11px;color:#555;">{{ $item->notes ?: '' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $transfer->type === 'incoming' ? 3 : 3 }}" style="text-align:right;">
                    Total ({{ $transfer->items->count() }} jenis)
                </td>
                <td class="right">
                    {{ number_format($transfer->items->sum('quantity'), 2, ',', '.') }}
                </td>
                @if($transfer->type === 'incoming')
                <td class="right">
                    {{ number_format($transfer->items->sum('actual_quantity'), 2, ',', '.') }}
                </td>
                <td></td>
                @endif
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    {{-- ── Notes ── --}}
    @if($transfer->notes)
    <div class="notes-box">
        <div class="label">Keterangan</div>
        <div style="font-size:12px;">{{ $transfer->notes }}</div>
    </div>
    @endif

    {{-- ── Signatures ── --}}
    <div class="sig-row">
        <div class="sig-box">
            <div class="role">Yang Menyerahkan</div>
            <div class="sig-space"></div>
            <div class="sig-name">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
            <div style="font-size:10px;color:#666;margin-top:2px;">Gudang Pengirim</div>
        </div>
        <div class="sig-box">
            <div class="role">Pengiriman / Kurir</div>
            <div class="sig-space"></div>
            <div class="sig-name">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
            <div style="font-size:10px;color:#666;margin-top:2px;">Tanda Tangan & Tanggal</div>
        </div>
        <div class="sig-box">
            <div class="role">Yang Menerima</div>
            <div class="sig-space"></div>
            <div class="sig-name">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
            <div style="font-size:10px;color:#666;margin-top:2px;">Gudang Penerima</div>
        </div>
    </div>

    {{-- ── Footer ── --}}
    <div class="doc-footer">
        Dicetak pada {{ now()->format('d/m/Y H:i') }} &nbsp;·&nbsp; {{ $transfer->transfer_no }}
        &nbsp;·&nbsp; Dokumen ini sah tanpa tanda tangan basah apabila telah di-input ke sistem
    </div>

</div>
</body>
</html>
