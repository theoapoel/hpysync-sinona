@extends('layouts.app')
@section('title', 'Repack — Konversi Item')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-scissors text-blue"></i> Repack</div>
        <div class="page-subtitle">Konversi qty item — mis. 1 Bolu dipotong menjadi 8 potong</div>
    </div>
    <div style="display:flex;gap:8px">
        <form method="POST" action="{{ route('slices.refresh-all') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('i').classList.add('fa-spin')">
            @csrf
            <button type="submit" class="btn btn-outline" title="Baca ulang status semua Stock Entry Repack di ERP HPY yang belum sinkron, tanpa mengirim apa pun">
                <i class="fas fa-rotate"></i> Refresh Semua Status
            </button>
        </form>
        <a href="{{ route('slices.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Buat Repack
        </a>
    </div>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="form-group" style="margin-bottom:0;min-width:140px">
                <label class="form-label" style="font-size:11px">Status</label>
                <select name="status" class="form-control form-select" style="font-size:13px">
                    <option value="">Semua Status</option>
                    <option value="draft"     {{ request('status')==='draft'     ?'selected':'' }}>Draft</option>
                    <option value="submitted" {{ request('status')==='submitted' ?'selected':'' }}>Disubmit</option>
                    <option value="cancelled" {{ request('status')==='cancelled' ?'selected':'' }}>Dibatalkan</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:11px">Dari</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" style="font-size:13px">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:11px">Sampai</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" style="font-size:13px">
            </div>
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
                <label class="form-label" style="font-size:11px">Cari</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="No. slice…" style="font-size:13px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            @if(request()->hasAny(['status','date_from','date_to','search']))
            <a href="{{ route('slices.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. Repack</th>
                    <th>Dibuat</th>
                    <th>Konversi</th>
                    <th>Status</th>
                    <th>ERP Stock Entry</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($slices as $slice)
            <tr>
                <td><a href="{{ route('slices.show', $slice) }}" class="font-medium text-blue">{{ $slice->slice_no }}</a></td>
                <td>
                    <div style="font-size:13px">{{ $slice->creator->name ?? '—' }}</div>
                    <div class="text-muted" style="font-size:11px">{{ $slice->created_at->isoFormat('D MMM Y HH:mm') }}</div>
                </td>
                <td>
                    <span class="badge badge-red">{{ $slice->issues_count }} keluar</span>
                    <i class="fas fa-arrow-right text-muted" style="font-size:10px;margin:0 2px"></i>
                    <span class="badge badge-green">{{ $slice->receipts_count }} masuk</span>
                </td>
                <td>
                    @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-blue','cancelled'=>'badge-red'][$slice->status] ?? 'badge-gray'; @endphp
                    <span class="badge {{ $sc }}">{{ $slice->status_label }}</span>
                </td>
                <td>
                    @if($slice->erp_stock_entry)
                    <span class="badge badge-green" style="font-size:11px">{{ $slice->erp_stock_entry }}</span>
                    @elseif($slice->erp_sync_status === 'failed')
                    <span class="badge badge-red" style="font-size:11px">Gagal</span>
                    @else
                    <span class="text-muted" style="font-size:11px">—</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('slices.show', $slice) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align:center;color:var(--text3);padding:40px">
                    <i class="fas fa-scissors" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4"></i>
                    Belum ada slice.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($slices->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $slices->links() }}
    </div>
    @endif
</div>
@endsection
