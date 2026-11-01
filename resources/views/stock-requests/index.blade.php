@extends('layouts.app')
@section('title', 'Permintaan Finish Goods')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-clipboard-check text-blue"></i> Permintaan Finish Goods</div>
        <div class="page-subtitle">Daftar permintaan bahan ke dapur / gudang</div>
    </div>
    <a href="{{ route('stock-requests.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Buat Permintaan
    </a>
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
                    <option value="submitted" {{ request('status')==='submitted' ?'selected':'' }}>Diajukan</option>
                    <option value="cancelled" {{ request('status')==='cancelled' ?'selected':'' }}>Dibatalkan</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:140px">
                <label class="form-label" style="font-size:11px">Dapur</label>
                <select name="kitchen_status" class="form-control form-select" style="font-size:13px">
                    <option value="">Semua</option>
                    <option value="requested" {{ request('kitchen_status')==='requested' ?'selected':'' }}>Diminta</option>
                    <option value="preparing" {{ request('kitchen_status')==='preparing' ?'selected':'' }}>Dipersiapkan</option>
                    <option value="done"      {{ request('kitchen_status')==='done'      ?'selected':'' }}>Selesai</option>
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
                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="No. request…" style="font-size:13px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filter
            </button>
            @if(request()->hasAny(['status','kitchen_status','date_from','date_to','search']))
            <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>No. Request</th>
                    <th>Dibuat</th>
                    <th>Dibutuhkan</th>
                    <th>Item</th>
                    <th>Status</th>
                    <th>Dapur</th>
                    <th>ERP MR</th>
                    <th style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($requests as $req)
            <tr>
                <td><a href="{{ route('stock-requests.show', $req) }}" class="font-medium text-blue">{{ $req->request_no }}</a></td>
                <td>
                    <div style="font-size:13px">{{ $req->requester->name ?? '—' }}</div>
                    <div class="text-muted" style="font-size:11px">{{ $req->created_at->isoFormat('D MMM Y HH:mm') }}</div>
                </td>
                <td>{{ $req->needed_date ? $req->needed_date->isoFormat('D MMM Y') : '—' }}{{ $req->needed_time ? ', ' . substr($req->needed_time, 0, 5) : '' }}</td>
                <td>
                    <span class="badge badge-blue">{{ $req->items_count ?? $req->items->count() }} item</span>
                </td>
                <td>
                    @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-blue','cancelled'=>'badge-red'][$req->status] ?? 'badge-gray'; @endphp
                    <span class="badge {{ $sc }}">{{ $req->status_label }}</span>
                </td>
                <td>
                    @if($req->kitchen_status)
                    @php $kc = ['requested'=>'badge-yellow','preparing'=>'badge-blue','done'=>'badge-green'][$req->kitchen_status] ?? 'badge-gray'; @endphp
                    <span class="badge {{ $kc }}">{{ $req->kitchen_status_label }}</span>
                    @else
                    <span class="text-muted" style="font-size:12px">—</span>
                    @endif
                </td>
                <td>
                    @if($req->erp_material_request)
                    <span class="badge badge-green" style="font-size:11px">{{ $req->erp_material_request }}</span>
                    @elseif($req->erp_sync_status === 'failed')
                    <span class="badge badge-red" style="font-size:11px">Gagal</span>
                    @else
                    <span class="text-muted" style="font-size:11px">—</span>
                    @endif
                </td>
                <td>
                    <a href="{{ route('stock-requests.show', $req) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="text-align:center;color:var(--text3);padding:40px">
                    <i class="fas fa-clipboard-check" style="font-size:32px;display:block;margin-bottom:8px;opacity:.4"></i>
                    Belum ada permintaan bahan.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requests->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $requests->links() }}
    </div>
    @endif
</div>
@endsection
