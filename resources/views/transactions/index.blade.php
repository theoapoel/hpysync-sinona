{{-- resources/views/transactions/index.blade.php --}}
@extends('layouts.app')
@section('title','Transaksi')

@push('styles')
<style>
    </style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-receipt text-blue"></i> Riwayat Transaksi</div>
        <div class="page-subtitle">Total {{ $transactions->total() }} transaksi</div>
        <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:8px 14px">
                <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Total Penjualan</div>
                <div style="font-size:18px;font-weight:800;color:var(--blue)">Rp {{ number_format($summary->total_amount ?? 0,0,',','.') }}</div>
            </div>
        </div>
    </div>
    <a href="{{ route('pos.index') }}" class="btn btn-primary">
        <i class="fas fa-cash-register"></i> Buka Kasir
    </a>
</div>

<div class="card">
    <div class="card-header" style="flex-wrap:wrap;gap:10px">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;flex:1">
            <input type="text" name="search" class="form-control" placeholder="Cari invoice..."
                value="{{ request('search') }}" style="max-width:200px">
            <input type="text" name="erp_invoice" class="form-control" placeholder="Cari No. Sync ERP..."
                value="{{ request('erp_invoice') }}" style="max-width:200px">
            @if($dateLocked)
            <span class="badge badge-warning" style="display:inline-flex;align-items:center;gap:6px;padding:0 12px">
                <i class="fas fa-calendar-day"></i> Hari ini ({{ today()->format('d/m/Y') }})
            </span>
            @else
            <input type="date" name="date_from" class="form-control"
                value="{{ request('date_from') }}" style="max-width:160px">
            <input type="date" name="date_to" class="form-control"
                value="{{ request('date_to') }}" style="max-width:160px">
            @endif
            <select name="status" class="form-control form-select" style="max-width:150px">
                <option value="">Semua Status</option>
                <option value="completed" {{ request('status')=='completed'?'selected':'' }}>Selesai</option>
                <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>Dibatalkan</option>
            </select>
            <select name="payment_method" class="form-control form-select" style="max-width:170px">
                <option value="">Semua Tipe Bayar</option>
                @foreach($paymentMethods as $pm)
                <option value="{{ $pm }}" {{ request('payment_method')===$pm?'selected':'' }}>{{ strtoupper($pm) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filter</button>
            @if(request()->hasAny(['search','erp_invoice','date_from','date_to','status','payment_method']))
            <a href="{{ route('transactions.index') }}" class="btn btn-ghost"><i class="fas fa-times"></i> Reset</a>
            @endif
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Invoice</th><th>Kasir</th><th>Customer</th><th>Total</th>
                    <th>Bayar</th><th>Status</th><th>Sync ERP</th><th>Status di HPY</th><th>Waktu</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td>
                    <a href="{{ route('transactions.show',$tx) }}" class="text-blue font-medium">
                        {{ $tx->invoice_no }}
                    </a>
                </td>
                <td class="text-sm">{{ $tx->user->name }}</td>
                <td class="text-sm">
                    @if($tx->customer)
                        <div style="display:flex;align-items:center;gap:6px">
                            <div style="width:26px;height:26px;border-radius:50%;background:var(--blue-light);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                                {{ substr($tx->customer->name, 0, 1) }}
                            </div>
                            {{ $tx->customer->name }}
                        </div>
                    @else
                        <span style="color:var(--text3);font-style:italic;font-size:12px">
                            <i class="fas fa-user" style="font-size:10px"></i> Walk-in
                        </span>
                    @endif
                </td>
                <td class="money font-bold">Rp {{ number_format($tx->total,0,',','.') }}</td>
                <td>
                    @php $payBadge=['cash'=>'badge-green','card'=>'badge-blue','transfer'=>'badge-yellow','qris'=>'badge-blue']; @endphp
                    <span class="badge {{ $payBadge[$tx->payment_method]??'badge-gray' }}">
                        {{ strtoupper($tx->payment_method) }}
                    </span>
                </td>
                <td>
                    <span class="badge {{ $tx->status==='completed'?'badge-green':'badge-red' }}">
                        {{ $tx->status==='completed'?'✓ Selesai':'✕ Batal' }}
                    </span>
                </td>
                <td>
                    @php $syncBadge=['pending'=>'badge-yellow','synced'=>'badge-green','failed'=>'badge-red'];
                         $syncLabel=['pending'=>'⏳ Pending','synced'=>'✓ Synced','failed'=>'✕ Failed']; @endphp
                    <span class="badge {{ $syncBadge[$tx->erp_sync_status]??'badge-gray' }}">
                        {{ $syncLabel[$tx->erp_sync_status]??$tx->erp_sync_status }}
                    </span>
                    @if($tx->erp_pos_invoice)
                        <div class="text-sm text-muted" style="margin-top:4px;font-family:monospace">{{ $tx->erp_pos_invoice }}</div>
                    @endif
                </td>
                <td>
                    @php
                        // Dibandingkan dengan status lokal: yang berbahaya adalah transaksi
                        // yang di sini masih 'completed' tapi di ERP sudah batal/hilang.
                        $st = $tx->erp_pos_invoice ? ($erpStates[$tx->erp_pos_invoice] ?? null) : null;
                        $lokalSelesai = $tx->status === 'completed';
                    @endphp
                    @if(!$tx->erp_pos_invoice)
                        <span class="text-muted text-sm">Belum tersinkron</span>
                    @elseif($erpCheckFailed)
                        <span class="badge badge-gray" title="ERP HPY tidak dapat dihubungi saat memuat halaman">? Tidak terperiksa</span>
                    @elseif($st === null)
                        <span class="badge badge-red">✕ Tidak ada di HPY</span>
                        <div class="text-sm text-muted" style="margin-top:4px">Dokumen sudah dihapus di HPY</div>
                    @elseif($st['docstatus'] === 2)
                        <span class="badge badge-red">✕ Dibatalkan di HPY</span>
                        @if($lokalSelesai)
                            <div class="text-sm text-red" style="margin-top:4px">Di sini masih selesai — nilainya ikut terhitung</div>
                        @endif
                    @elseif($st['docstatus'] === 0)
                        <span class="badge badge-yellow">⏳ Draft di HPY</span>
                        <div class="text-sm text-muted" style="margin-top:4px">Belum di-submit</div>
                    @else
                        <span class="badge badge-green">✓ {{ $st['status'] ?: 'Submitted' }}</span>
                    @endif
                </td>
                <td class="text-sm text-muted">{{ local_dt($tx->created_at) }}</td>
                <td style="white-space:nowrap">
                    <a href="{{ route('transactions.show',$tx) }}" class="btn btn-ghost btn-sm" title="Detail">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="{{ route('pos.print',$tx) }}" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Struk">
                        <i class="fas fa-print"></i>
                    </a>
                    <a href="{{ route('pos.print-kitchen',$tx) }}" target="_blank" class="btn btn-ghost btn-sm" title="Cetak Kitchen">
                        <i class="fas fa-utensils"></i>
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" style="text-align:center;padding:60px;color:var(--text3)">
                    <div style="font-size:40px;margin-bottom:12px">🧾</div>
                    <div style="font-size:15px;font-weight:600">Tidak ada transaksi ditemukan</div>
                    <div style="font-size:13px;margin-top:4px">Coba ubah filter pencarian</div>
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- Custom Pagination --}}
    @if($transactions->hasPages())
    <div class="pagination-wrap">
        <div class="pagination-info">
            Menampilkan {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }}
            dari <strong>{{ $transactions->total() }}</strong> transaksi
        </div>
        <ul class="pagination">
            {{-- Prev --}}
            <li class="{{ $transactions->onFirstPage() ? 'disabled' : '' }}">
                @if($transactions->onFirstPage())
                    <span><i class="fas fa-chevron-left" style="font-size:10px"></i> Prev</span>
                @else
                    <a href="{{ $transactions->previousPageUrl() }}">
                        <i class="fas fa-chevron-left" style="font-size:10px"></i> Prev
                    </a>
                @endif
            </li>

            {{-- Page Numbers --}}
            @foreach($transactions->getUrlRange(max(1,$transactions->currentPage()-2), min($transactions->lastPage(),$transactions->currentPage()+2)) as $page => $url)
            <li class="{{ $page==$transactions->currentPage()?'active':'' }}">
                @if($page==$transactions->currentPage())
                    <span>{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            </li>
            @endforeach

            {{-- Next --}}
            <li class="{{ !$transactions->hasMorePages() ? 'disabled' : '' }}">
                @if($transactions->hasMorePages())
                    <a href="{{ $transactions->nextPageUrl() }}">
                        Next <i class="fas fa-chevron-right" style="font-size:10px"></i>
                    </a>
                @else
                    <span>Next <i class="fas fa-chevron-right" style="font-size:10px"></i></span>
                @endif
            </li>
        </ul>
    </div>
    @endif
</div>
@endsection
