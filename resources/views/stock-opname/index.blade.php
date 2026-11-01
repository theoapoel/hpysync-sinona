@extends('layouts.app')
@section('title', 'Stock Opname')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px;"></i>
            Stock Opname
        </h1>
        <p class="page-subtitle">Hitung dan sesuaikan stok fisik dengan sistem</p>
    </div>
    <div>
        <a href="{{ route('stock-opname.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Buat Opname Baru
        </a>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Gudang</th>
                    <th>Dibuat Oleh</th>
                    <th>Status</th>
                    <th>Sync ERP</th>
                    <th>Stock Entry</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($opnames as $op)
                <tr>
                    <td style="font-weight:500;">{{ $op->opname_date->format('d/m/Y') }}</td>
                    <td>{{ $op->warehouse?->warehouse_name ?: $op->warehouse?->name }}</td>
                    <td style="font-size:13px;color:var(--text2);">{{ $op->creator?->name }}</td>
                    <td>
                        @if($op->status === 'draft')
                            <span class="badge badge-yellow">Draft</span>
                        @elseif($op->status === 'submitted')
                            <span class="badge badge-green">Submitted</span>
                        @else
                            <span class="badge badge-red">Dibatalkan</span>
                        @endif
                    </td>
                    <td>
                        @if($op->erp_sync_status === 'synced')
                            <span class="badge badge-green">Synced</span>
                        @elseif($op->erp_sync_status === 'failed')
                            <span class="badge badge-red" title="{{ $op->erp_sync_error }}">Gagal</span>
                        @else
                            <span style="color:var(--text3);font-size:13px;">—</span>
                        @endif
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        @if($op->erp_entry_issue)
                            <div><i class="fas fa-arrow-up" style="color:var(--red);font-size:10px;"></i> {{ $op->erp_entry_issue }}</div>
                        @endif
                        @if($op->erp_entry_receipt)
                            <div><i class="fas fa-arrow-down" style="color:var(--green);font-size:10px;"></i> {{ $op->erp_entry_receipt }}</div>
                        @endif
                        @if(!$op->erp_entry_issue && !$op->erp_entry_receipt)
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('stock-opname.show', $op) }}" class="btn btn-ghost" style="font-size:13px;">
                            <i class="fas fa-eye"></i> Detail
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:48px;color:var(--text2);">
                        Belum ada stock opname. <a href="{{ route('stock-opname.create') }}">Buat sekarang</a>.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($opnames->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border);">
        {{ $opnames->links() }}
    </div>
    @endif
</div>
@endsection
