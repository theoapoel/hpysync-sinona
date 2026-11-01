@extends('layouts.app')
@section('title', 'Detail Repack')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-scissors text-blue"></i> {{ $slice->slice_no }}</div>
        <div class="page-subtitle">
            Dibuat oleh {{ $slice->creator->name ?? '—' }} pada {{ $slice->created_at->isoFormat('D MMM Y, HH:mm') }}
        </div>
    </div>
    <div style="display:flex;gap:8px">
        @if($slice->status === 'draft')
        <form method="POST" action="{{ route('slices.submit', $slice) }}" onsubmit="return confirm('Proses konversi ini ke ERP HPY (Repack)? Stok sumber akan keluar dan hasil masuk.')">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Submit ke ERP
            </button>
        </form>
        <form method="POST" action="{{ route('slices.cancel', $slice) }}" onsubmit="return confirm('Batalkan repack ini?')">
            @csrf
            <button type="submit" class="btn btn-ghost" style="color:var(--red)">
                <i class="fas fa-times"></i> Batalkan
            </button>
        </form>
        @elseif($slice->status === 'submitted')
        @if($slice->erp_sync_status !== 'synced')
        <button onclick="syncErp()" class="btn btn-outline">
            <i class="fas fa-sync-alt"></i> Sync ERP
        </button>
        @endif
        <form method="POST" action="{{ route('slices.cancel', $slice) }}" onsubmit="return confirm('Batalkan slice ini? Stock Entry (Repack) di ERP akan dibatalkan dan pergerakan stok dibalik.')">
            @csrf
            <button type="submit" class="btn btn-ghost" style="color:var(--red)">
                <i class="fas fa-times"></i> Batalkan
            </button>
        </form>
        @endif
        @if($slice->erp_stock_entry && $slice->status !== 'cancelled')
        <form method="POST" action="{{ route('slices.refresh-status', $slice) }}">
            @csrf
            <button type="submit" class="btn btn-outline" title="Baca ulang status Stock Entry di ERP HPY tanpa mengirim apa pun">
                <i class="fas fa-rotate"></i> Refresh Status
            </button>
        </form>
        @endif
        <a href="{{ route('slices.index') }}" class="btn btn-ghost">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">

    {{-- Kiri --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-info-circle text-blue"></i> Detail</div>
                @php $sc = ['draft'=>'badge-gray','submitted'=>'badge-blue','cancelled'=>'badge-red'][$slice->status] ?? 'badge-gray'; @endphp
                <span class="badge {{ $sc }}">{{ $slice->status_label }}</span>
            </div>
            @if($slice->notes)
            <div class="card-body">
                <div class="text-muted" style="font-size:12px;margin-bottom:2px">Catatan</div>
                <div>{{ $slice->notes }}</div>
            </div>
            @endif
        </div>

        {{-- TABEL 1 — Issue --}}
        <div class="card" style="margin-bottom:16px">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-up-from-bracket text-red"></i> Item Dibuang (Issue)</div>
                <span class="badge badge-red">{{ $slice->issues->count() }} item</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th style="text-align:right">Qty</th>
                            <th>Gudang Asal</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($slice->issues as $i => $line)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="font-medium">{{ $line->item_name }}</div>
                            <div class="text-muted" style="font-size:11px;font-family:monospace">{{ $line->item_code ?: '—' }}</div>
                        </td>
                        <td style="text-align:right;font-weight:700">{{ number_format($line->qty, 2) }} <span class="text-muted" style="font-weight:400;font-size:11px">{{ $line->uom }}</span></td>
                        <td style="font-size:12px">{{ $line->warehouse ?: 'Gudang default' }}</td>
                        <td class="text-muted" style="font-size:12px">{{ $line->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted" style="text-align:center;padding:16px">Tidak ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TABEL 2 — Receipt --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-arrow-down-to-bracket text-green"></i> Jadi Item (Receipt)</div>
                <span class="badge badge-green">{{ $slice->receipts->count() }} item</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th style="text-align:right">Qty</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($slice->receipts as $i => $line)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td>
                            <div class="font-medium">{{ $line->item_name }}</div>
                            <div class="text-muted" style="font-size:11px;font-family:monospace">{{ $line->item_code ?: '—' }}</div>
                        </td>
                        <td style="text-align:right;font-weight:700">{{ number_format($line->qty, 2) }} <span class="text-muted" style="font-weight:400;font-size:11px">{{ $line->uom }}</span></td>
                        <td class="text-muted" style="font-size:12px">{{ $line->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted" style="text-align:center;padding:16px">Tidak ada item.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Kanan --}}
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-cloud"></i> Status ERP</div></div>
            <div class="card-body">
                @if($slice->erp_stock_entry)
                <div class="alert alert-success" style="margin-bottom:0">
                    <i class="fas fa-check-circle"></i>
                    Stock Entry (Repack): <strong>{{ $slice->erp_stock_entry }}</strong>
                </div>
                @elseif($slice->erp_sync_status === 'failed')
                <div class="alert alert-danger" style="margin-bottom:0">
                    <i class="fas fa-exclamation-circle"></i>
                    Sync gagal: {{ $slice->erp_sync_error }}
                </div>
                @else
                <div class="alert" style="background:var(--surface2);margin-bottom:0">
                    <i class="fas fa-clock"></i>
                    Belum diproses ke ERP.
                </div>
                @endif
            </div>
        </div>
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
        const res = await api.post('{{ route("slices.sync-erp", $slice) }}');
        if (res.success) {
            logEl.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> Berhasil! Stock Entry: <strong>${res.docname}</strong></div>`;
            setTimeout(() => location.reload(), 2000);
        } else {
            logEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${res.error}</div>`;
        }
    } catch(e) {
        logEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${e.message}</div>`;
    }
}
</script>
@endpush
