@extends('layouts.app')
@section('title', 'Kupon')
@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-ticket-alt text-blue"></i> Manajemen Kupon</div>
        <div class="page-subtitle" style="font-size:12px;color:var(--text3);margin-top:2px">
            Kupon diambil dari ERP HPY beserta Pricing Rule-nya
        </div>
    </div>
    <button class="btn btn-primary" id="pullErpBtn" onclick="pullFromErp()">
        <i class="fas fa-download"></i> Tarik dari ERP HPY
    </button>
</div>

<div id="pullStatus" style="display:none;margin-bottom:12px"></div>

<div class="card">
    <div class="card-header">
        <form method="GET" style="display:flex;gap:10px">
            <input type="text" name="search" class="form-control" placeholder="Cari kode atau deskripsi..." value="{{ $search }}" style="max-width:280px">
            <button type="submit" class="btn btn-outline"><i class="fas fa-search"></i> Cari</button>
            @if($search)
                <a href="{{ route('coupons.index') }}" class="btn btn-ghost">Reset</a>
            @endif
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Deskripsi</th>
                    <th>Pricing Rule</th>
                    <th>Diskon</th>
                    <th>Min. Pembelian</th>
                    <th>Pemakaian</th>
                    <th>Berlaku</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($coupons as $c)
            <tr>
                <td><span class="badge badge-blue" style="font-family:monospace;font-size:13px;letter-spacing:.5px">{{ $c->code }}</span></td>
                <td>{{ $c->description ?? '-' }}</td>
                <td>
                    @if($c->erp_pricing_rule)
                        <span style="font-size:12px;color:var(--text2)">{{ $c->erp_pricing_rule }}</span>
                    @else
                        <span style="color:var(--text3)">-</span>
                    @endif
                </td>
                <td class="font-medium">
                    @if($c->discount_type === 'percent')
                        {{ $c->discount_value }}%
                    @else
                        Rp {{ number_format($c->discount_value, 0, ',', '.') }}
                    @endif
                </td>
                <td>
                    @if($c->min_purchase > 0)
                        Rp {{ number_format($c->min_purchase, 0, ',', '.') }}
                    @else
                        <span style="color:var(--text3)">-</span>
                    @endif
                </td>
                <td>
                    {{ $c->used_count }}{{ $c->max_uses ? ' / ' . $c->max_uses : '' }}
                </td>
                <td style="font-size:12px">
                    @if($c->valid_from || $c->valid_until)
                        {{ $c->valid_from?->format('d/m/Y') ?? '∞' }} – {{ $c->valid_until?->format('d/m/Y') ?? '∞' }}
                    @else
                        <span style="color:var(--text3)">Selamanya</span>
                    @endif
                </td>
                <td>
                    @if($c->is_active)
                        <span class="badge badge-green">Aktif</span>
                    @else
                        <span class="badge badge-gray">Nonaktif</span>
                    @endif
                </td>
                <td>
                    <form method="POST" action="{{ route('coupons.destroy', $c) }}" onsubmit="return confirm('Hapus kupon {{ $c->code }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center;padding:40px;color:var(--text3)">
                    <div style="font-size:32px;margin-bottom:8px">🎫</div>
                    Belum ada kupon. Klik <strong>Tarik dari ERP HPY</strong> untuk mengambil Coupon Code beserta Pricing Rule-nya.
                </td>
            </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($coupons->hasPages())
    <div class="pagination-wrap">
        <div class="pagination-info">Menampilkan {{ $coupons->firstItem() }}–{{ $coupons->lastItem() }} dari {{ $coupons->total() }}</div>
        <ul class="pagination">
            <li class="{{ $coupons->onFirstPage() ? 'disabled' : '' }}">
                @if(!$coupons->onFirstPage()) <a href="{{ $coupons->previousPageUrl() }}">Prev</a> @else <span>Prev</span> @endif
            </li>
            @foreach($coupons->getUrlRange(max(1,$coupons->currentPage()-2), min($coupons->lastPage(),$coupons->currentPage()+2)) as $page => $url)
            <li class="{{ $page==$coupons->currentPage()?'active':'' }}">
                @if($page==$coupons->currentPage()) <span>{{ $page }}</span> @else <a href="{{ $url }}">{{ $page }}</a> @endif
            </li>
            @endforeach
            <li class="{{ !$coupons->hasMorePages() ? 'disabled' : '' }}">
                @if($coupons->hasMorePages()) <a href="{{ $coupons->nextPageUrl() }}">Next</a> @else <span>Next</span> @endif
            </li>
        </ul>
    </div>
    @endif
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

async function pullFromErp() {
    const btn = document.getElementById('pullErpBtn');
    const statusEl = document.getElementById('pullStatus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Menarik dari ERP HPY...';
    statusEl.style.display = 'none';

    try {
        const resp = await fetch('{{ route("sync.pull-coupons") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const data = await resp.json();
        if (data.success) {
            statusEl.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i> ${data.message}</div>`;
            statusEl.style.display = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            statusEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ${data.error || 'Gagal menarik kupon dari ERP HPY.'}</div>`;
            statusEl.style.display = '';
        }
    } catch(e) {
        statusEl.innerHTML = `<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error: ${e.message}</div>`;
        statusEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> Tarik dari ERP HPY';
    }
}
</script>
@endsection
