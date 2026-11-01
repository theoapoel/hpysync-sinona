@extends('layouts.app')

@section('title', 'Transfer Barang')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-6px;margin-right:8px;" class="text-blue">
                <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
            Transfer Barang
        </h1>
        <p class="page-subtitle">Kirim & terima barang melalui ERP HPY Material Transfer</p>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('stock-transfer.report') }}" class="btn btn-ghost btn-sm">
            <i class="fas fa-file-alt"></i> Laporan Detail
        </a>
        <a href="{{ route('stock-transfer.receive.create') }}" class="btn btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;">
                <path d="M12 5v14M5 12l7 7 7-7"/>
            </svg>
            Terima Barang
        </a>
        <a href="{{ route('stock-transfer.send.create') }}" class="btn btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:4px;">
                <path d="M12 19V5M5 12l7-7 7 7"/>
            </svg>
            Kirim Barang
        </a>
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

<div class="card">
    <div class="card-header" style="padding:16px 20px;">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="search" value="{{ request('search') }}"
                class="form-control" placeholder="Cari no. transfer / gudang..." style="width:260px;">
            <select name="type" class="form-select" style="width:150px;">
                <option value="">Semua Tipe</option>
                <option value="outgoing" @selected(request('type')==='outgoing')>Kirim</option>
                <option value="incoming" @selected(request('type')==='incoming')>Terima</option>
            </select>
            <select name="status" class="form-select" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="draft" @selected(request('status')==='draft')>Draft</option>
                <option value="submitted" @selected(request('status')==='submitted')>Submitted</option>
                <option value="cancelled" @selected(request('status')==='cancelled')>Dibatalkan</option>
            </select>
            <button type="submit" class="btn btn-outline">Filter</button>
            @if(request()->hasAny(['search','type','status']))
                <a href="{{ route('stock-transfer.index') }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. Transfer</th>
                    <th>Tipe</th>
                    <th>Dari Gudang</th>
                    <th>Ke Gudang</th>
                    <th>ERP HPY Entry</th>
                    <th>Status</th>
                    <th>Sync</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                <tr>
                    <td><strong>{{ $t->transfer_no }}</strong></td>
                    <td>
                        @if($t->type === 'outgoing')
                            <span class="badge badge-blue">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:3px;"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                                Kirim
                            </span>
                        @else
                            <span class="badge badge-green">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:3px;"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                                Terima
                            </span>
                        @endif
                    </td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $t->from_warehouse }}">
                        {{ $t->from_warehouse }}
                    </td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $t->to_warehouse }}">
                        {{ $t->to_warehouse }}
                    </td>
                    <td>
                        @if($t->erp_stock_entry)
                            <code style="font-size:12px;color:var(--blue);">{{ $t->erp_stock_entry }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($t->local_status === 'received')
                            <span class="badge badge-green">Diterima</span>
                        @elseif($t->local_status === 'sent')
                            <span class="badge badge-blue">Dikirim</span>
                        @else
                            <span class="badge badge-gray">Draft</span>
                        @endif
                    </td>
                    <td>
                        @if($t->erp_sync_status === 'synced')
                            <span class="badge badge-green">Synced</span>
                        @elseif($t->erp_sync_status === 'failed')
                            <span class="badge badge-red">Failed</span>
                        @else
                            <span class="badge badge-yellow">Pending</span>
                        @endif
                    </td>
                    <td style="white-space:nowrap;color:var(--text2);font-size:13px;">
                        {{ local_dt($t->created_at) }}
                    </td>
                    <td>
                        <a href="{{ route('stock-transfer.show', $t) }}" class="btn btn-sm btn-outline">Detail</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" style="text-align:center;padding:48px;color:var(--text2);">
                        Belum ada data transfer barang
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transfers->hasPages())
    <div class="pagination-wrap">
        <span style="color:var(--text2);font-size:13px;">
            Menampilkan {{ $transfers->firstItem() }}–{{ $transfers->lastItem() }} dari {{ $transfers->total() }}
        </span>
        <div class="pagination">
            @if($transfers->onFirstPage())
                <span class="page-btn disabled">‹</span>
            @else
                <a href="{{ $transfers->previousPageUrl() }}" class="page-btn">‹</a>
            @endif
            @foreach($transfers->getUrlRange(max(1,$transfers->currentPage()-2), min($transfers->lastPage(),$transfers->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}" class="page-btn {{ $page == $transfers->currentPage() ? 'active' : '' }}">{{ $page }}</a>
            @endforeach
            @if($transfers->hasMorePages())
                <a href="{{ $transfers->nextPageUrl() }}" class="page-btn">›</a>
            @else
                <span class="page-btn disabled">›</span>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
