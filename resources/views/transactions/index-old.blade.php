{{-- resources/views/transactions/index.blade.php --}}
@extends('layouts.app')
@section('title','Transaksi')
@section('content')
<div class="page-header">
    <div><div class="page-title"><i class="fas fa-receipt text-blue"></i> Riwayat Transaksi</div></div>
    <a href="{{ route('pos.index') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> Buka Kasir</a>
</div>
<div class="card">
    <div class="card-header">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="text" name="search" class="form-control" placeholder="Cari invoice..." value="{{ request('search') }}" style="max-width:200px">
            <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" style="max-width:160px">
            <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" style="max-width:160px">
            <select name="status" class="form-control form-select" style="max-width:150px">
                <option value="">Semua Status</option>
                <option value="completed" {{ request('status')=='completed'?'selected':'' }}>Selesai</option>
                <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>Dibatalkan</option>
            </select>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Invoice</th><th>Kasir</th><th>Customer</th><th>Total</th><th>Bayar</th><th>Status</th><th>Sync</th><th>Waktu</th><th></th></tr></thead>
            <tbody>
            @forelse($transactions as $tx)
            <tr>
                <td><a href="{{ route('transactions.show',$tx) }}" class="text-blue font-medium">{{ $tx->invoice_no }}</a></td>
                <td class="text-sm">{{ $tx->user->name }}</td>
                <td class="text-sm">{{ $tx->customer?->name ?? '<span class="text-muted">Walk-in</span>' }}</td>
                <td class="money font-bold">Rp {{ number_format($tx->total,0,',','.') }}</td>
                <td><span class="badge {{ ['cash'=>'badge-green','card'=>'badge-blue','transfer'=>'badge-yellow','qris'=>'badge-blue'][$tx->payment_method]??'badge-gray' }}">{{ strtoupper($tx->payment_method) }}</span></td>
                <td><span class="badge {{ $tx->status==='completed'?'badge-green':'badge-red' }}">{{ strtoupper($tx->status) }}</span></td>
                <td><span class="badge {{ ['pending'=>'badge-yellow','synced'=>'badge-green','failed'=>'badge-red'][$tx->erp_sync_status]??'badge-gray' }}">{{ strtoupper($tx->erp_sync_status) }}</span></td>
                <td class="text-sm text-muted">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    <a href="{{ route('pos.print',$tx) }}" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-print"></i></a>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">Tidak ada transaksi</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px">{{ $transactions->links() }}</div>
</div>
@endsection
