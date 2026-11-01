@extends('layouts.app')
@section('title', 'Detail Permintaan FG')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-clipboard-check text-blue"></i> {{ $req->request_no }}</div>
        <div class="page-subtitle">
            Dibuat oleh {{ $req->requester->name ?? '—' }} pada {{ $req->created_at->isoFormat('D MMM Y, HH:mm') }}
        </div>
    </div>
    <div style="display:flex;gap:8px">
        @if($req->status === 'draft')
        <form method="POST" action="{{ route('stock-requests.submit', $req) }}" onsubmit="return confirm('Ajukan permintaan ini ke dapur dan ERP?')">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Ajukan
            </button>
        </form>
        <form method="POST" action="{{ route('stock-requests.cancel', $req) }}" onsubmit="return confirm('Batalkan permintaan ini?')">
            @csrf
            <button type="submit" class="btn btn-ghost" style="color:var(--red)">
                <i class="fas fa-times"></i> Batalkan
            </button>
        </form>
        @elseif($req->status === 'submitted' && $req->erp_sync_status !== 'synced')
        <button onclick="syncErp()" class="btn btn-outline">
            <i class="fas fa-sync-alt"></i> Sync ERP
        </button>
        @endif
        <a href="{{ route('stock-requests.index') }}" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

    {{-- Kiri --}}
    <div>
        {{-- Info --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle text-blue"></i> Detail</div>
                @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-blue','cancelled'=>'badge-red'][$req->status] ?? 'badge-gray'; @endphp
                <span class="badge {{ $sc }}">{{ $req->status_label }}</span>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div class="text-muted" style="font-size:12px;margin-bottom:2px">Tanggal Dibutuhkan</div>
                        <div class="font-medium">{{ $req->needed_date ? $req->needed_date->isoFormat('D MMM Y') : '—' }}{{ $req->needed_time ? ', ' . substr($req->needed_time, 0, 5) : '' }}</div>
                    </div>
                    <div>
                        <div class="text-muted" style="font-size:12px;margin-bottom:2px">Status Dapur</div>
                        @if($req->kitchen_status)
                        @php $kc = ['requested'=>'badge-yellow','preparing'=>'badge-blue','done'=>'badge-green'][$req->kitchen_status] ?? 'badge-gray'; @endphp
                        <span class="badge {{ $kc }}">{{ $req->kitchen_status_label }}</span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </div>
                    @if($req->kitchen_started_at)
                    <div>
                        <div class="text-muted" style="font-size:12px;margin-bottom:2px">Mulai Dipersiapkan</div>
                        <div class="font-medium">{{ $req->kitchen_started_at->isoFormat('D MMM Y, HH:mm') }}</div>
                    </div>
                    @endif
                    @if($req->kitchen_done_at)
                    <div>
                        <div class="text-muted" style="font-size:12px;margin-bottom:2px">Selesai</div>
                        <div class="font-medium">{{ $req->kitchen_done_at->isoFormat('D MMM Y, HH:mm') }}</div>
                    </div>
                    @endif
                </div>
                @if($req->notes)
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
                    <div class="text-muted" style="font-size:12px;margin-bottom:2px">Catatan</div>
                    <div>{{ $req->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Items --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-box text-blue"></i> Produk yang Diminta</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th>Item Code</th>
                            <th style="text-align:right">Qty</th>
                            <th>Satuan</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($req->items as $i => $item)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="font-medium">{{ $item->item_name }}</td>
                        <td class="text-muted" style="font-size:12px;font-family:monospace">{{ $item->item_code ?: '—' }}</td>
                        <td style="text-align:right;font-weight:700">{{ number_format($item->qty, 2) }}</td>
                        <td>{{ $item->uom }}</td>
                        <td class="text-muted" style="font-size:12px">{{ $item->notes ?: '—' }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Kanan --}}
    <div>
        {{-- ERP Status --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header"><div class="card-title"><i class="fab fa-github"></i> Status ERP</div></div>
            <div class="card-body">
                @if($req->erp_material_request)
                <div class="alert alert-success" style="margin-bottom:0">
                    <i class="fas fa-check-circle"></i>
                    Material Request: <strong>{{ $req->erp_material_request }}</strong>
                </div>
                @elseif($req->erp_sync_status === 'failed')
                <div class="alert alert-danger" style="margin-bottom:12px">
                    <i class="fas fa-exclamation-circle"></i>
                    Sync gagal: {{ $req->erp_sync_error }}
                </div>
                @else
                <div class="alert" style="background:var(--surface2);margin-bottom:0">
                    <i class="fas fa-clock"></i>
                    Belum disync ke ERP.
                    @if($req->status === 'submitted')
                    <button onclick="syncErp()" class="btn btn-outline btn-sm" style="margin-left:8px">
                        <i class="fas fa-sync-alt"></i> Sync Sekarang
                    </button>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Kitchen status update (if submitted) --}}
        @if($req->status === 'submitted' && $req->kitchen_status && $req->kitchen_status !== 'done')
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-utensils text-blue"></i> Update Dapur</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
                @if($req->kitchen_status === 'requested')
                <button onclick="updateKitchen('preparing')" class="btn btn-primary" style="border-radius:8px">
                    <i class="fas fa-fire"></i> Mulai Persiapan
                </button>
                @endif
                @if($req->kitchen_status === 'preparing')
                <button onclick="updateKitchen('done')" class="btn btn-success" style="border-radius:8px">
                    <i class="fas fa-check"></i> Selesai Dipersiapkan
                </button>
                <button onclick="updateKitchen('requested')" class="btn btn-ghost btn-sm">
                    <i class="fas fa-undo"></i> Kembalikan ke Diminta
                </button>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<div id="syncLog" style="display:none;margin-top:16px"></div>
@endsection

@push('scripts')
<script>
async function syncErp() {
    const logEl = document.getElementById('syncLog');
    logEl.style.display = 'block';
    logEl.innerHTML = '<div class="alert" style="background:var(--surface2)"><span class="spinner"></span> Mengirim ke ERP...</div>';
    try {
        const res = await api.post('{{ route("stock-requests.sync-erp", $req) }}');
        if (res.success) {
            logEl.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Berhasil! Material Request: <strong>${res.docname}</strong></div>`;
            setTimeout(() => location.reload(), 2000);
        } else {
            logEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${res.error}</div>`;
        }
    } catch(e) {
        logEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${e.message}</div>`;
    }
}

async function updateKitchen(status) {
    try {
        await api.post('{{ route("stock-requests.kitchen-status", $req) }}', { kitchen_status: status });
        location.reload();
    } catch(e) {
        alert('Gagal update status: ' + e.message);
    }
}
</script>
@endpush
