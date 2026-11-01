@extends('layouts.app')

@section('title', $transfer->transfer_no)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            @if($transfer->type === 'outgoing')
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px;" class="text-blue"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            @else
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-5px;margin-right:8px;" class="text-blue"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            @endif
            {{ $transfer->transfer_no }}
        </h1>
        <p class="page-subtitle">
            {{ $transfer->type === 'outgoing' ? 'Pengiriman Barang' : 'Penerimaan Barang' }} ·
            dibuat oleh {{ $transfer->user->name }} · {{ local_dt($transfer->created_at, 'd M Y H:i') }}
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        @if($transfer->erp_sync_status === 'failed' || ($transfer->erp_sync_status === 'pending' && $transfer->status === 'draft'))
            <form method="POST" action="{{ route('stock-transfer.retry', $transfer) }}">
                @csrf
                <button class="btn btn-outline">Retry Sync ERP HPY</button>
            </form>
        @endif
        <a href="{{ route('stock-transfer.surat-jalan', $transfer) }}" target="_blank" class="btn btn-primary btn-sm">
            <i class="fas fa-print"></i>
            {{ $transfer->type === 'incoming' ? 'Cetak Bukti Terima' : 'Cetak Surat Jalan' }}
        </a>
        <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">← Kembali</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning mb-3">{{ session('warning') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;">

    {{-- LEFT: Items --}}
    <div>
        <div class="card">
            <div class="card-header"><span class="card-title">Daftar Barang</span></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kode Item</th>
                            <th>Nama Barang</th>
                            <th style="text-align:right;">Qty {{ $transfer->isIncoming() ? 'Kirim' : '' }}</th>
                            @if($transfer->isIncoming())
                                <th style="text-align:right;">Qty Terima</th>
                                <th style="text-align:right;">Selisih</th>
                            @endif
                            <th>Satuan</th>
                            <th>Produk Lokal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfer->items as $i => $item)
                        <tr>
                            <td style="color:var(--text2);">{{ $i + 1 }}</td>
                            <td><code style="font-size:12px;">{{ $item->item_code }}</code></td>
                            <td>{{ $item->item_name }}</td>
                            <td style="text-align:right;">{{ number_format($item->quantity, 3) }}</td>
                            @if($transfer->isIncoming())
                                <td style="text-align:right;font-weight:600;">
                                    {{ number_format($item->actual_quantity ?? $item->quantity, 3) }}
                                </td>
                                <td style="text-align:right;">
                                    @php $diff = ($item->actual_quantity ?? $item->quantity) - $item->quantity; @endphp
                                    @if($diff != 0)
                                        <span style="color:{{ $diff > 0 ? 'var(--green)' : 'var(--red)' }}">
                                            {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 3) }}
                                        </span>
                                    @else
                                        <span style="color:var(--text2);">—</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $item->unit }}</td>
                            <td>
                                @if($item->product)
                                    <span style="font-size:13px;color:var(--blue);">{{ $item->product->name }}</span>
                                @else
                                    <span style="font-size:13px;color:var(--text2);">Tidak terlink</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- RIGHT: Summary --}}
    <div>
        <div class="card mb-3">
            <div class="card-header"><span class="card-title">Ringkasan</span></div>
            <div class="card-body">
                <table style="width:100%;border-collapse:collapse;">
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Tipe</td>
                        <td style="padding:8px 0;text-align:right;">
                            @if($transfer->type === 'outgoing')
                                <span class="badge badge-blue">Kirim</span>
                            @else
                                <span class="badge badge-green">Terima</span>
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Status Lokal</td>
                        <td style="padding:8px 0;text-align:right;">
                            @if($transfer->local_status === 'received')
                                <span class="badge badge-green">Diterima</span>
                            @elseif($transfer->local_status === 'sent')
                                <span class="badge badge-blue">Dikirim</span>
                            @else
                                <span class="badge badge-gray">Draft</span>
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Sync ERP HPY</td>
                        <td style="padding:8px 0;text-align:right;">
                            @if($transfer->erp_sync_status === 'synced')
                                <span class="badge badge-green">Synced</span>
                            @elseif($transfer->erp_sync_status === 'failed')
                                <span class="badge badge-red">Failed</span>
                            @else
                                <span class="badge badge-yellow">Pending</span>
                            @endif
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Dari Gudang</td>
                        <td style="padding:8px 0;text-align:right;font-size:13px;max-width:160px;word-break:break-word;">
                            {{ $transfer->from_warehouse }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Ke Gudang</td>
                        <td style="padding:8px 0;text-align:right;font-size:13px;max-width:160px;word-break:break-word;">
                            {{ $transfer->to_warehouse }}
                        </td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Total Item</td>
                        <td style="padding:8px 0;text-align:right;font-weight:600;">
                            {{ $transfer->items->count() }} jenis
                        </td>
                    </tr>
                    @if($transfer->submitted_at)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 0;font-size:13px;color:var(--text2);">Submitted</td>
                        <td style="padding:8px 0;text-align:right;font-size:13px;">
                            {{ $transfer->submitted_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- ERP HPY doc --}}
        @if($transfer->erp_stock_entry || $transfer->erp_source_entry || $transfer->erp_sync_error)
        <div class="card mb-3">
            <div class="card-header"><span class="card-title">ERP HPY</span></div>
            <div class="card-body">
                @if($transfer->erp_stock_entry)
                <div style="margin-bottom:8px;">
                    <div style="font-size:12px;color:var(--text2);margin-bottom:2px;">Stock Entry</div>
                    <code style="font-size:13px;color:var(--blue);">{{ $transfer->erp_stock_entry }}</code>
                </div>
                @endif
                @if($transfer->erp_source_entry)
                <div style="margin-bottom:8px;">
                    <div style="font-size:12px;color:var(--text2);margin-bottom:2px;">Source Entry</div>
                    <code style="font-size:13px;color:var(--text);">{{ $transfer->erp_source_entry }}</code>
                </div>
                @endif
                @if($transfer->erp_sync_error)
                <div style="background:var(--bg);border-radius:6px;padding:10px;margin-top:8px;border:1px solid #FECACA;">
                    <div style="font-size:12px;color:var(--red);font-weight:600;margin-bottom:4px;">Error</div>
                    <div style="font-size:12px;color:var(--text);word-break:break-all;max-height:120px;overflow:auto;">
                        {{ $transfer->erp_sync_error }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($transfer->notes)
        <div class="card">
            <div class="card-header"><span class="card-title">Keterangan</span></div>
            <div class="card-body">
                <p style="font-size:14px;color:var(--text2);margin:0;">{{ $transfer->notes }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
