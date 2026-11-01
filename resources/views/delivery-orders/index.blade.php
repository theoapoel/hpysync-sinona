@extends('layouts.app')
@section('title', 'Delivery Order')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-truck text-blue"></i> Delivery Order</div>
        <div class="page-subtitle">Kelola pesanan dengan jadwal pengiriman</div>
    </div>
    <a href="{{ route('delivery-orders.create') }}" class="btn btn-primary btn-lg">
        <i class="fas fa-plus"></i> Buat Order
    </a>
</div>

{{-- Filter --}}
<div class="card" style="margin-bottom:20px">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                    placeholder="No. order / customer...">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Tgl Kirim Dari</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Sampai</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Status</label>
                <select name="status" class="form-control form-select">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','confirmed'=>'Confirmed','delivering'=>'Delivering','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l)
                    <option value="{{ $v }}" {{ request('status')===$v ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Cari
            </button>
            @if(request()->hasAny(['search','date_from','date_to','status']))
            <a href="{{ route('delivery-orders.index') }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>No. Order</th><th>Customer</th><th>Tgl Kirim</th>
                <th>Jadwal Produksi</th>
                <th>Tujuan</th><th>Total</th><th>Status</th><th>Status Bayar</th><th>ERP SO</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($orders as $order)
            @php
                $statusColor = ['draft'=>'badge-gray','confirmed'=>'badge-blue','delivering'=>'badge-yellow','completed'=>'badge-green','cancelled'=>'badge-red'][$order->status] ?? 'badge-gray';
                $payStatusColor = ['unpaid'=>'badge-red','partial'=>'badge-yellow','paid'=>'badge-green'][$order->payment_status] ?? 'badge-gray';
                $payStatusLabel = ['unpaid'=>'Belum Lunas','partial'=>'DP / Sebagian','paid'=>'Lunas'][$order->payment_status] ?? '-';
            @endphp
            <tr>
                <td><a href="{{ route('delivery-orders.show', $order) }}" class="text-blue font-medium">{{ $order->order_no }}</a></td>
                <td>{{ $order->customer?->name ?? '-' }}</td>
                <td>{{ $order->delivery_date->isoFormat('D MMM Y') }}</td>
                <td>
                    @if($order->kitchen_scheduled_at)
                        @php $sch = $order->kitchen_scheduled_at; @endphp
                        <div style="font-size:13px;font-weight:600;color:{{ $sch->isFuture() ? 'var(--blue)' : 'var(--green)' }}">
                            <i class="fas fa-calendar-check" style="margin-right:4px"></i>{{ $sch->isoFormat('D MMM Y') }}
                        </div>
                        <div style="font-size:11px;color:var(--text3)">
                            {{ $sch->isFuture() ? 'Pukul ' . $sch->format('H:i') . ' — menunggu' : 'Pukul ' . $sch->format('H:i') . ' — aktif' }}
                        </div>
                    @else
                        <span class="text-muted text-xs">—</span>
                    @endif
                </td>
                <td><span class="badge badge-gray">{{ $order->shipments_count ?? $order->shipments()->count() }} tujuan</span></td>
                <td class="money">Rp {{ number_format($order->total, 0, ',', '.') }}</td>
                <td><span class="badge {{ $statusColor }}">{{ strtoupper($order->status) }}</span></td>
                <td><span class="badge {{ $payStatusColor }}">{{ $payStatusLabel }}</span></td>
                <td>
                    @if($order->erp_sales_order)
                        <span class="badge badge-green text-xs">{{ $order->erp_sales_order }}</span>
                    @elseif($order->erp_sync_status === 'failed')
                        <span class="badge badge-red text-xs">FAILED</span>
                    @else
                        <span class="text-muted text-xs">—</span>
                    @endif
                </td>
                <td style="white-space:nowrap">
                    <a href="{{ route('delivery-orders.show', $order) }}" class="btn btn-ghost btn-sm" title="Detail">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="{{ route('delivery-orders.print-slip', $order) }}" target="_blank" class="btn btn-ghost btn-sm" title="Print Slip">
                        <i class="fas fa-print"></i>
                    </a>
                    <button type="button" class="btn btn-ghost btn-sm" title="Print Thermal (langsung ke printer)"
                            onclick="thermalPrintSlip(this, '{{ route('delivery-orders.direct-print-slip', $order) }}')">
                        <i class="fas fa-receipt"></i>
                    </button>
                    <a href="{{ route('delivery-orders.proforma', $order) }}" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Proforma Invoice">
                        <i class="fas fa-file-invoice"></i>
                    </a>
                    <a href="{{ route('delivery-orders.invoice', $order) }}" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Invoice">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr><td colspan="10" style="text-align:center;padding:40px;color:#80868B">Belum ada delivery order</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $orders->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function thermalPrintSlip(btn, url) {
    const icon = btn.querySelector('i');
    btn.disabled = true;
    icon.className = 'fas fa-spinner fa-spin';

    fetch(url)
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Gagal mencetak ke printer.');
            }
            alert(data.message || 'Slip berhasil dikirim ke printer.');
        })
        .catch((err) => alert('❌ ' + err.message))
        .finally(() => {
            btn.disabled = false;
            icon.className = 'fas fa-receipt';
        });
}
</script>
@endpush
