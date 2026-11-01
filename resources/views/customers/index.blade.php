{{-- resources/views/customers/index.blade.php --}}
@extends('layouts.app')
@section('title','Customer')
@section('content')
<div class="page-header">
    <div><div class="page-title"><i class="fas fa-users text-blue"></i> Manajemen Customer</div></div>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('show')"><i class="fas fa-user-plus"></i> Tambah Customer</button>
</div>
<div class="card">
    <div class="card-header">
        <form method="GET" style="display:flex;gap:10px">
            <input type="text" name="search" class="form-control" placeholder="Cari customer..." value="{{ request('search') }}" style="max-width:280px">
            <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Cari</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Kode</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Total Pembelian</th><th>HPY</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($customers as $c)
            <tr>
                <td class="badge badge-blue">{{ $c->code }}</td>
                <td class="font-medium">{{ $c->name }}</td>
                <td>{{ $c->phone ?? '-' }}</td>
                <td>{{ $c->email ?? '-' }}</td>
                <td class="money text-blue">Rp {{ number_format($c->total_purchase,0,',','.') }}</td>
                <td>
                    @if($c->erp_customer_name)
                        <span class="badge badge-green" title="{{ $c->erp_customer_name }}">✓ Synced</span>
                    @else
                        <span class="badge badge-gray">Local</span>
                    @endif
                </td>
                <td style="display:flex;gap:6px">
                    @if(!$c->erp_customer_name)
                    <button class="btn btn-ghost btn-sm" onclick="pushCustomer({{ $c->id }},this)"><i class="fas fa-upload"></i> Push ERP</button>
                    @endif
                    <button class="btn btn-ghost btn-sm" title="Cek & betulkan mapping ke HPY" onclick="reconcileCustomer({{ $c->id }},this)"><i class="fas fa-rotate"></i> Sinkron ERP</button>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text3)">Belum ada customer</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{-- <div style="padding:16px">{{ $customers->links() }}</div> --}}
    @if($customers->hasPages())
    <div class="pagination-wrap">
        <div class="pagination-info">
            Menampilkan {{ $customers->firstItem() }}–{{ $customers->lastItem() }}
            dari <strong>{{ $customers->total() }}</strong> customer
        </div>
        <ul class="pagination">
            <li class="{{ $customers->onFirstPage() ? 'disabled' : '' }}">
                @if($customers->onFirstPage())
                    <span><i class="fas fa-chevron-left" style="font-size:10px"></i> Prev</span>
                @else
                    <a href="{{ $customers->previousPageUrl() }}"><i class="fas fa-chevron-left" style="font-size:10px"></i> Prev</a>
                @endif
            </li>
            @foreach($customers->getUrlRange(max(1,$customers->currentPage()-2), min($customers->lastPage(),$customers->currentPage()+2)) as $page => $url)
            <li class="{{ $page==$customers->currentPage()?'active':'' }}">
                @if($page==$customers->currentPage())
                    <span>{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            </li>
            @endforeach
            <li class="{{ !$customers->hasMorePages() ? 'disabled' : '' }}">
                @if($customers->hasMorePages())
                    <a href="{{ $customers->nextPageUrl() }}">Next <i class="fas fa-chevron-right" style="font-size:10px"></i></a>
                @else
                    <span>Next <i class="fas fa-chevron-right" style="font-size:10px"></i></span>
                @endif
            </li>
        </ul>
    </div>
    @endif
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">Tambah Customer</div><button onclick="document.getElementById('addModal').classList.remove('show')" style="background:none;border:none;cursor:pointer;font-size:20px">&times;</button></div>
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-group"><label class="form-label">Nama *</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Telepon</label><input type="text" name="phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="document.getElementById('addModal').classList.remove('show')">Batal</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button></div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
async function pushCustomer(id, btn) {
    btn.innerHTML = '<span class="spinner" style="border-color:rgba(0,0,0,.2);border-top-color:var(--blue)"></span>';
    btn.disabled = true;
    const resp = await fetch(`{{ url('sync/push-customer') }}/${id}`, {method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});
    const data = await resp.json();
    toast(data.success ? 'Berhasil push ke HPY: '+data.docname : 'Gagal: '+data.error, data.success?'success':'error');
    if (data.success) setTimeout(()=>location.reload(),1000);
    else { btn.innerHTML='<i class="fas fa-upload"></i> Push ERP'; btn.disabled=false; }
}

async function reconcileCustomer(id, btn) {
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner" style="border-color:rgba(0,0,0,.2);border-top-color:var(--blue)"></span>';
    btn.disabled = true;
    const resp = await fetch(`{{ url('sync/reconcile-customer') }}/${id}`, {method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}});
    const data = await resp.json();
    const msg = {synced:'Mapping sudah benar',fixed:'Mapping diperbaiki →',linked:'Dipetakan ke HPY →',pushed:'Dibuat di HPY →'};
    if (data.success) { toast((msg[data.status]||'Selesai')+(data.docname?' '+data.docname:''),'success'); setTimeout(()=>location.reload(),1000); }
    else { toast('Gagal: '+(data.error||'tidak ada customer HPY yang cocok — cek manual'),'error'); btn.innerHTML=orig; btn.disabled=false; }
}
</script>
@endpush
