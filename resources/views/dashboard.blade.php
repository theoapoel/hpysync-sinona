@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Dashboard</div>
        <div class="page-subtitle">{{ now()->isoFormat('dddd, D MMMM Y') }}</div>
    </div>
    <a href="{{ route('pos.index') }}" class="btn btn-primary btn-lg">
        <i class="fas fa-cash-register"></i> Buka Kasir
    </a>
</div>

<!-- Stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-money-bill-wave"></i></div>
        <div>
            <div class="stat-value text-blue money">{{ number_format($stats['today_sales'], 0, ',', '.') }}</div>
            <div class="stat-label">Penjualan Hari Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div>
            <div class="stat-value text-green">{{ $stats['today_count'] }}</div>
            <div class="stat-label">Transaksi Hari Ini</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-box"></i></div>
        <div>
            <div class="stat-value" style="color:#E37400">{{ $stats['total_products'] }}</div>
            <div class="stat-label">Total Produk Aktif</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
        <div>
            <div class="stat-value text-red">{{ $stats['low_stock'] }}</div>
            <div class="stat-label">Stok Menipis</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value text-blue">{{ $stats['total_customers'] }}</div>
            <div class="stat-label">Total Customer</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon {{ $stats['pending_sync'] > 0 ? 'yellow' : 'green' }}">
            <i class="fas fa-sync-alt"></i>
        </div>
        <div>
            <div class="stat-value" style="color:{{ $stats['pending_sync'] > 0 ? '#E37400' : '#34A853' }}">
                {{ $stats['pending_sync'] }}
            </div>
            <div class="stat-label">Pending Sync HPY</div>
        </div>
    </div>
</div>

<!-- Charts + Recent -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Sales Chart -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-chart-bar text-blue"></i> Penjualan 7 Hari</div>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="200"></canvas>
        </div>
    </div>

    <!-- Top Products -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-star text-yellow" style="color:#FBBC05"></i> Produk Terlaris</div>
            <span class="text-sm text-muted">30 hari terakhir</span>
        </div>
        <div class="card-body" style="padding:12px 0">
            @forelse($topProducts as $i => $p)
            <div style="display:flex;align-items:center;padding:10px 20px;gap:12px">
                <div style="width:28px;height:28px;border-radius:50%;background:{{ ['#4285F4','#34A853','#FBBC05','#EA4335','#9C27B0'][$i] }};color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">{{ $i+1 }}</div>
                <div style="flex:1">
                    <div style="font-size:14px;font-weight:600">{{ $p->product_name }}</div>
                    <div style="font-size:12px;color:#80868B">{{ number_format($p->total_qty) }} terjual</div>
                </div>
                <div class="money" style="font-size:14px;color:#4285F4">Rp {{ number_format($p->total_revenue, 0, ',', '.') }}</div>
            </div>
            @empty
            <div style="padding:20px;text-align:center;color:#80868B;font-size:14px">Belum ada data penjualan</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-clock text-blue"></i> Transaksi Terbaru</div>
        <a href="{{ route('transactions.index') }}" class="btn btn-ghost btn-sm">Lihat Semua <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Invoice</th><th>Kasir</th><th>Customer</th>
                <th>Total</th><th>Pembayaran</th><th>Status Sync</th><th>Waktu</th>
            </tr></thead>
            <tbody>
            @forelse($recentTx as $tx)
            <tr>
                <td><a href="{{ route('transactions.show', $tx) }}" class="text-blue font-medium">{{ $tx->invoice_no }}</a></td>
                <td>{{ $tx->user->name }}</td>
                <td>{{ $tx->customer?->name ?? '<span class="text-muted">Walk-in</span>' }}</td>
                <td class="money">Rp {{ number_format($tx->total, 0, ',', '.') }}</td>
                <td>
                    <span class="badge {{ ['cash'=>'badge-green','card'=>'badge-blue','transfer'=>'badge-yellow','qris'=>'badge-blue'][$tx->payment_method] ?? 'badge-gray' }}">
                        {{ strtoupper($tx->payment_method) }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ ['pending'=>'badge-yellow','synced'=>'badge-green','failed'=>'badge-red'][$tx->erp_sync_status] ?? 'badge-gray' }}">
                        {{ strtoupper($tx->erp_sync_status) }}
                    </span>
                </td>
                <td class="text-muted text-sm">{{ $tx->created_at->diffForHumans() }}</td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:#80868B">Belum ada transaksi</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
const salesData = @json($salesChart);
const labels = salesData.map(d => {
    const dt = new Date(d.date);
    return dt.toLocaleDateString('id-ID', {weekday:'short', day:'numeric', month:'short'});
});
const totals = salesData.map(d => parseFloat(d.total));

new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Penjualan',
            data: totals,
            backgroundColor: ['#4285F4','#EA4335','#FBBC05','#34A853','#4285F4','#EA4335','#FBBC05'],
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => 'Rp ' + ctx.raw.toLocaleString('id-ID')
                }
            }
        },
        scales: {
            y: {
                ticks: { callback: v => 'Rp ' + (v/1000).toFixed(0) + 'k' },
                grid: { color: '#F1F3F4' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
@endpush
