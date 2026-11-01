@extends('layouts.app')
@section('title', 'Warehouse')

@push('styles')
<style>
.toggle { position:relative; display:inline-block; width:42px; height:22px; }
.toggle input { opacity:0; width:0; height:0; }
.toggle-slider { position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:22px; transition:.25s; }
.toggle-slider:before { content:''; position:absolute; width:16px; height:16px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.25s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
input:checked + .toggle-slider { background:var(--green); }
input:checked + .toggle-slider:before { transform:translateX(20px); }

.flag-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; border-radius:16px; font-size:12px; font-weight:600; cursor:pointer; border:2px solid transparent; transition:all .2s; white-space:nowrap; }
.flag-btn.default-btn { border-color:var(--border); color:var(--text3); background:transparent; }
.flag-btn.default-btn.active { border-color:var(--blue); color:var(--blue); background:var(--blue-light); }
.flag-btn.transit-btn { border-color:var(--border); color:var(--text3); background:transparent; }
.flag-btn.transit-btn.active { border-color:#9C27B0; color:#9C27B0; background:#F3E8FF; }
.flag-btn:hover:not(.active) { background:var(--surface2); }

.type-badge { display:inline-flex; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.group-row td { background:#FAFAFA; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-warehouse text-blue" style="margin-right:8px"></i>Warehouse</h1>
        <p class="page-subtitle">Kelola warehouse yang digunakan oleh sistem POS ini</p>
    </div>
    <button onclick="pullWarehouses()" id="pullBtn" class="btn btn-primary">
        <i class="fas fa-cloud-download-alt"></i> Pull dari ERP HPY
    </button>
</div>

{{-- Stats bar --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-warehouse"></i></div>
        <div>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <div class="stat-label">Total Warehouse</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value">{{ $stats['active'] }}</div>
            <div class="stat-label">Aktif / Assigned</div>
        </div>
    </div>
    <div class="stat-card" style="flex-direction:column;align-items:flex-start;gap:4px">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
            <i class="fas fa-store" style="color:var(--blue);margin-right:4px"></i>Default Transaksi
        </div>
        <div style="font-size:13px;font-weight:600;color:{{ $stats['default'] ? 'var(--text)' : 'var(--text3)' }}">
            {{ $stats['default'] ?? '— Belum diset —' }}
        </div>
        <div style="font-size:11px;color:var(--text3)">Digunakan saat sync POS Invoice</div>
    </div>
    <div class="stat-card" style="flex-direction:column;align-items:flex-start;gap:4px">
        <div style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">
            <i class="fas fa-exchange-alt" style="color:#9C27B0;margin-right:4px"></i>Warehouse Transit
        </div>
        <div style="font-size:13px;font-weight:600;color:{{ $stats['transit'] ? 'var(--text)' : 'var(--text3)' }}">
            {{ $stats['transit'] ?? '— Belum diset —' }}
        </div>
        <div style="font-size:11px;color:var(--text3)">Titik singgah saat transfer barang</div>
    </div>
</div>

@if($stats['last_pulled'])
<div class="alert alert-info" style="margin-bottom:16px">
    <i class="fas fa-info-circle"></i>
    Terakhir dipull: <strong>{{ \Carbon\Carbon::parse($stats['last_pulled'])->diffForHumans() }}</strong>.
    Klik <strong>Pull dari ERP HPY</strong> untuk memperbarui daftar warehouse.
</div>
@else
<div class="alert alert-warning" style="margin-bottom:16px">
    <i class="fas fa-exclamation-triangle"></i>
    Belum ada warehouse yang dipull. Klik <strong>Pull dari ERP HPY</strong> untuk mengambil data warehouse dari ERP HPY.
</div>
@endif

{{-- Filter --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:12px 20px">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:200px">
                <input type="text" name="search" class="form-control"
                    placeholder="Cari nama warehouse..." value="{{ request('search') }}">
            </div>
            <div>
                <select name="show" class="form-control form-select" style="width:180px">
                    <option value="">Semua Warehouse</option>
                    <option value="active"  {{ request('show') === 'active' ? 'selected' : '' }}>Aktif Saja</option>
                    <option value="leaf"    {{ request('show') === 'leaf'   ? 'selected' : '' }}>Non-Grup Saja</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Cari</button>
            @if(request()->hasAny(['search','show']))
                <a href="{{ route('warehouses.index') }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- Legend --}}
<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;font-size:12px;color:var(--text3)">
    <span><span class="flag-btn default-btn active" style="pointer-events:none"><i class="fas fa-store"></i> Default</span> = Digunakan saat sync transaksi POS ke ERP HPY</span>
    <span><span class="flag-btn transit-btn active" style="pointer-events:none"><i class="fas fa-exchange-alt"></i> Transit</span> = Gudang perantara saat kirim barang antar lokasi</span>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-list text-blue" style="margin-right:6px"></i>
            Daftar Warehouse <span style="font-weight:400;color:var(--text3)">({{ $warehouses->total() }})</span>
        </span>
        <div style="font-size:12px;color:var(--text3)">
            {{ $warehouses->firstItem() }}–{{ $warehouses->lastItem() }} dari {{ $warehouses->total() }}
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nama Warehouse</th>
                    <th>Tipe</th>
                    <th>Company</th>
                    <th style="text-align:center;white-space:nowrap">
                        <i class="fas fa-store" style="color:var(--blue)"></i> Default
                    </th>
                    <th style="text-align:center;white-space:nowrap">
                        <i class="fas fa-exchange-alt" style="color:#9C27B0"></i> Transit
                    </th>
                    <th style="text-align:center">Aktif</th>
                </tr>
            </thead>
            <tbody id="warehouseTable">
                @forelse($warehouses as $wh)
                <tr id="row_{{ $wh->id }}" class="{{ $wh->is_group ? 'group-row' : '' }}">
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            @if($wh->is_group)
                                <i class="fas fa-folder" style="color:#E37400;font-size:14px;width:18px"></i>
                            @else
                                <i class="fas fa-warehouse" style="color:var(--text3);font-size:13px;width:18px"></i>
                            @endif
                            <div>
                                <div style="font-weight:600;font-size:14px">{{ $wh->warehouse_name ?: $wh->name }}</div>
                                @if($wh->warehouse_name && $wh->warehouse_name !== $wh->name)
                                <div style="font-size:11px;color:var(--text3);font-family:monospace">{{ $wh->name }}</div>
                                @endif
                            </div>
                            @if($wh->is_group)
                            <span style="font-size:11px;color:#E37400;background:#FEF3E2;padding:1px 6px;border-radius:8px;font-weight:600">GRUP</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @php
                        $typeColors = ['Transit'=>['#F3E8FF','#9C27B0'],'Stores'=>['var(--blue-light)','var(--blue)'],'Supplier'=>['#FEF3E2','#E37400'],'All Warehouses'=>['var(--surface2)','var(--text2)']];
                        [$tbg,$tc] = $typeColors[$wh->warehouse_type ?? ''] ?? ['var(--surface2)','var(--text2)'];
                        @endphp
                        @if($wh->warehouse_type)
                        <span class="type-badge" style="background:{{ $tbg }};color:{{ $tc }}">{{ $wh->warehouse_type }}</span>
                        @else
                        <span style="color:var(--text3);font-size:13px">—</span>
                        @endif
                    </td>
                    <td style="font-size:13px;color:var(--text3)">{{ $wh->company ?: '—' }}</td>

                    {{-- Default --}}
                    <td style="text-align:center">
                        @if(!$wh->is_group)
                        <button onclick="setDefault({{ $wh->id }}, this)"
                            class="flag-btn default-btn {{ $wh->is_default ? 'active' : '' }}"
                            data-id="{{ $wh->id }}"
                            {{ !$wh->is_active ? 'disabled title="Aktifkan warehouse ini terlebih dahulu"' : '' }}
                            style="{{ !$wh->is_active ? 'opacity:.4;cursor:not-allowed' : '' }}">
                            <i class="fas {{ $wh->is_default ? 'fa-star' : 'fa-star' }}" style="font-size:11px"></i>
                            {{ $wh->is_default ? 'Default' : 'Set Default' }}
                        </button>
                        @else
                        <span style="color:var(--text3);font-size:12px">—</span>
                        @endif
                    </td>

                    {{-- Transit --}}
                    <td style="text-align:center">
                        @if(!$wh->is_group)
                        <button onclick="setTransit({{ $wh->id }}, this)"
                            class="flag-btn transit-btn {{ $wh->is_transit ? 'active' : '' }}"
                            data-id="{{ $wh->id }}"
                            {{ !$wh->is_active ? 'disabled title="Aktifkan warehouse ini terlebih dahulu"' : '' }}
                            style="{{ !$wh->is_active ? 'opacity:.4;cursor:not-allowed' : '' }}">
                            <i class="fas fa-exchange-alt" style="font-size:11px"></i>
                            {{ $wh->is_transit ? 'Transit' : 'Set Transit' }}
                        </button>
                        @else
                        <span style="color:var(--text3);font-size:12px">—</span>
                        @endif
                    </td>

                    {{-- Active toggle --}}
                    <td style="text-align:center">
                        <label class="toggle">
                            <input type="checkbox" {{ $wh->is_active ? 'checked' : '' }}
                                onchange="toggleActive({{ $wh->id }}, this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:48px;color:var(--text3)">
                        <i class="fas fa-warehouse" style="font-size:36px;margin-bottom:12px;display:block;opacity:.3"></i>
                        @if(request('search') || request('show'))
                            Tidak ada warehouse sesuai filter.
                        @else
                            Belum ada warehouse. Klik <strong>Pull dari ERP HPY</strong> untuk mengambil data.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($warehouses->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $warehouses->links() }}
    </div>
    @endif
</div>

{{-- How it works info --}}
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-question-circle text-blue" style="margin-right:6px"></i>Cara Kerja Warehouse Assignment</span>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">
        <div>
            <div style="font-weight:700;margin-bottom:8px;color:var(--blue)"><i class="fas fa-cloud-download-alt"></i> 1. Pull</div>
            <p style="font-size:13px;color:var(--text3);line-height:1.6">Ambil semua warehouse dari ERP HPY. Jalankan ulang jika ada warehouse baru di ERP.</p>
        </div>
        <div>
            <div style="font-weight:700;margin-bottom:8px;color:var(--green)"><i class="fas fa-toggle-on"></i> 2. Aktifkan</div>
            <p style="font-size:13px;color:var(--text3);line-height:1.6">Toggle <strong>Aktif</strong> pada warehouse yang boleh digunakan sistem POS ini. Hanya warehouse aktif yang muncul di form Transfer Barang.</p>
        </div>
        <div>
            <div style="font-weight:700;margin-bottom:8px;color:#9C27B0"><i class="fas fa-star"></i> 3. Set Flag</div>
            <p style="font-size:13px;color:var(--text3);line-height:1.6">
                <strong>Default</strong>: warehouse yang dipakai saat sync transaksi POS ke ERP HPY.<br>
                <strong>Transit</strong>: gudang perantara saat Kirim Barang antar lokasi.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const whUrl = (id, action) => '{{ url("warehouses") }}/' + id + '/' + action;

async function pullWarehouses() {
    const btn = document.getElementById('pullBtn');
    btn.innerHTML = '<span class="spinner"></span> Menarik...';
    btn.disabled = true;

    try {
        const res = await api.post('{{ route("warehouses.pull") }}');
        if (res.success) {
            toast(res.message, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            toast('Gagal: ' + (res.error || 'Error tidak diketahui'), 'error');
            btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Pull dari ERP HPY';
            btn.disabled = false;
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Pull dari ERP HPY';
        btn.disabled = false;
    }
}

async function toggleActive(id, checkbox) {
    const row = document.getElementById(`row_${id}`);
    try {
        const res = await api.post(whUrl(id, 'toggle'));
        if (res.success) {
            toast(res.message, 'success');
            // If deactivated, disable flag buttons
            if (!res.is_active) {
                row.querySelectorAll('.flag-btn').forEach(btn => {
                    btn.disabled = true;
                    btn.style.opacity = '.4';
                    btn.style.cursor = 'not-allowed';
                    btn.classList.remove('active');
                    btn.innerHTML = btn.classList.contains('default-btn')
                        ? '<i class="fas fa-star" style="font-size:11px"></i> Set Default'
                        : '<i class="fas fa-exchange-alt" style="font-size:11px"></i> Set Transit';
                });
            } else {
                row.querySelectorAll('.flag-btn').forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '';
                    btn.style.cursor = '';
                });
            }
        } else {
            checkbox.checked = !checkbox.checked; // revert
            toast(res.error || 'Gagal mengubah status.', 'error');
        }
    } catch(e) {
        checkbox.checked = !checkbox.checked;
        toast('Error: ' + e.message, 'error');
    }
}

async function setDefault(id, btn) {
    const isAlreadyDefault = btn.classList.contains('active');

    // If already default, clicking clears it
    const url = isAlreadyDefault
        ? whUrl(id, 'clear-flag')
        : whUrl(id, 'set-default');
    const body = isAlreadyDefault ? { flag: 'is_default' } : {};

    try {
        const res = await api.post(url, body);
        if (res.success) {
            // Clear all default buttons
            document.querySelectorAll('.default-btn').forEach(b => {
                b.classList.remove('active');
                b.innerHTML = '<i class="fas fa-star" style="font-size:11px"></i> Set Default';
            });
            if (!isAlreadyDefault) {
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-star" style="font-size:11px"></i> Default';
            }
            toast(res.message || 'Disimpan.', 'success');
            updateStatBar();
        } else {
            toast(res.error, 'error');
        }
    } catch(e) { toast('Error: ' + e.message, 'error'); }
}

async function setTransit(id, btn) {
    const isAlreadyTransit = btn.classList.contains('active');

    const url = isAlreadyTransit
        ? whUrl(id, 'clear-flag')
        : whUrl(id, 'set-transit');
    const body = isAlreadyTransit ? { flag: 'is_transit' } : {};

    try {
        const res = await api.post(url, body);
        if (res.success) {
            document.querySelectorAll('.transit-btn').forEach(b => {
                b.classList.remove('active');
                b.innerHTML = '<i class="fas fa-exchange-alt" style="font-size:11px"></i> Set Transit';
            });
            if (!isAlreadyTransit) {
                btn.classList.add('active');
                btn.innerHTML = '<i class="fas fa-exchange-alt" style="font-size:11px"></i> Transit';
            }
            toast(res.message || 'Disimpan.', 'success');
        } else {
            toast(res.error, 'error');
        }
    } catch(e) { toast('Error: ' + e.message, 'error'); }
}

function updateStatBar() {
    // Simple: count active checkboxes
    const activeCount = document.querySelectorAll('.toggle input:checked').length;
}
</script>
@endpush
