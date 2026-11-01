@extends('layouts.app')
@section('title', 'Hak Akses')

@push('styles')
<style>
.toggle-wrap { display:flex; align-items:center; justify-content:center; }
.toggle { position:relative; display:inline-block; width:46px; height:24px; }
.toggle input { opacity:0; width:0; height:0; }
.toggle-slider {
    position:absolute; cursor:pointer; inset:0;
    background:#ccc; border-radius:24px; transition:.25s;
}
.toggle-slider:before {
    content:''; position:absolute;
    width:18px; height:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.25s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
input:checked + .toggle-slider { background:var(--green); }
input:checked + .toggle-slider:before { transform:translateX(22px); }
input:disabled + .toggle-slider { cursor:not-allowed; opacity:.55; }

.perm-table { width:100%; border-collapse:collapse; }
.perm-table th { padding:12px 16px; font-size:11px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.6px; border-bottom:2px solid var(--border); text-align:center; white-space:nowrap; }
.perm-table th:first-child { text-align:left; width:220px; }
.perm-table td { padding:10px 16px; border-bottom:1px solid var(--border); }
.perm-table tbody tr:hover { background:var(--surface2); }
.perm-table tbody tr:last-child td { border-bottom:none; }
.perm-table .role-col { width:110px; text-align:center; }
.section-row td { background:var(--surface2); padding:7px 16px; font-size:11px; font-weight:700; color:var(--text3); text-transform:uppercase; letter-spacing:.6px; }
.module-label { display:flex; align-items:center; gap:10px; }
.module-icon  { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
.role-head    { display:flex; flex-direction:column; align-items:center; gap:4px; padding:4px 0; }
.role-dot     { width:10px; height:10px; border-radius:50%; }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-shield-alt text-blue" style="margin-right:8px"></i>Hak Akses</h1>
        <p class="page-subtitle">Atur modul yang dapat diakses per role — perubahan aktif langsung setelah disimpan</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <a href="{{ route('roles.index') }}" class="btn btn-ghost btn-sm">
            <i class="fas fa-layer-group"></i> Kelola Role
        </a>
        <span id="saveStatus" class="badge badge-gray" style="display:none"></span>
        <button onclick="savePermissions()" id="saveBtn" class="btn btn-primary">
            <i class="fas fa-save"></i> Simpan
        </button>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:16px">
    <i class="fas fa-info-circle"></i>
    Role <strong>Admin</strong> selalu memiliki akses penuh. Beberapa aksi (batalkan transaksi, konfigurasi sync) selalu memerlukan Admin atau Manager.
    Role baru dapat ditambahkan di <a href="{{ route('roles.index') }}" style="color:var(--blue-dark);font-weight:600">Manajemen Role</a>.
</div>

<div class="card">
    <div class="table-wrap" style="overflow-x:auto">
        <table class="perm-table">
            <thead>
                <tr>
                    <th>Modul</th>
                    {{-- Admin: fixed --}}
                    <th class="role-col">
                        <div class="role-head">
                            <span class="role-dot" style="background:#4285F4"></span>
                            <span>Admin</span>
                        </div>
                    </th>
                    {{-- Dynamic roles --}}
                    @foreach($roles as $role)
                    <th class="role-col">
                        <div class="role-head">
                            <span class="role-dot" style="background:{{ $role->color }}"></span>
                            <span>{{ $role->label }}</span>
                            @if(!$role->is_system)
                                <span style="font-size:10px;color:var(--text3);font-weight:400;text-transform:none">custom</span>
                            @endif
                        </div>
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                {{-- Configurable modules --}}
                <tr class="section-row">
                    <td colspan="{{ 2 + $roles->count() }}">
                        <i class="fas fa-sliders-h" style="margin-right:6px"></i>Modul Configurable
                    </td>
                </tr>

                @php
                $moduleStyle = [
                    'dashboard'      => ['#E8F0FE','var(--blue)'],
                    'pos'            => ['#E6F4EA','var(--green)'],
                    'transactions'   => ['#FEF3E2','#E37400'],
                    'products'       => ['#FCE8E6','var(--red)'],
                    'customers'      => ['#E8F0FE','var(--blue)'],
                    'stock_transfer' => ['#F3E8FF','#9C27B0'],
                    'stock'          => ['#FFF8E1','#F9A825'],
                    'delivery'       => ['#E3F2FD','#1565C0'],
                    'kitchen'        => ['#FFF3E0','#E65100'],
                    'stock_request'  => ['#E0F7FA','#00838F'],
                    'sync'           => ['#E6F4EA','var(--green)'],
                ];
                @endphp

                @foreach($modules as $key => $meta)
                @php [$bg, $color] = $moduleStyle[$key] ?? ['var(--surface2)','var(--text2)']; @endphp
                <tr>
                    <td>
                        <div class="module-label">
                            <div class="module-icon" style="background:{{ $bg }};color:{{ $color }}">
                                <i class="fas {{ $meta['icon'] }}"></i>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px">{{ $meta['label'] }}</div>
                                @if($key === 'transactions')
                                    <div style="font-size:11px;color:var(--text3)">Batalkan: Admin/Manager</div>
                                @elseif($key === 'sync')
                                    <div style="font-size:11px;color:var(--text3)">Konfigurasi: Admin saja</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    {{-- Admin: always on --}}
                    <td class="role-col">
                        <div class="toggle-wrap">
                            <label class="toggle"><input type="checkbox" checked disabled><span class="toggle-slider"></span></label>
                        </div>
                    </td>
                    {{-- Dynamic roles --}}
                    @foreach($roles as $role)
                    <td class="role-col">
                        <div class="toggle-wrap">
                            <label class="toggle">
                                <input type="checkbox" class="perm-toggle"
                                    data-role="{{ $role->name }}" data-module="{{ $key }}"
                                    {{ ($permissions[$role->name][$key] ?? false) ? 'checked' : '' }}>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </td>
                    @endforeach
                </tr>
                @endforeach

                {{-- Admin-only fixed --}}
                <tr class="section-row">
                    <td colspan="{{ 2 + $roles->count() }}">
                        <i class="fas fa-lock" style="margin-right:6px"></i>Modul Khusus Admin (tidak dapat diubah)
                    </td>
                </tr>

                @foreach([['Manajemen User','fa-users-cog','var(--blue-light)','var(--blue)'],['Hak Akses','fa-shield-alt','#E8F0FE','var(--blue)'],['Pengaturan Toko','fa-store','#FEF3E2','#E37400'],['Factory Reset','fa-trash-alt','#FCE8E6','var(--red)']] as [$lbl,$ico,$bg,$color])
                <tr style="opacity:.65">
                    <td>
                        <div class="module-label">
                            <div class="module-icon" style="background:{{ $bg }};color:{{ $color }}"><i class="fas {{ $ico }}"></i></div>
                            <span style="font-weight:600;font-size:13px">{{ $lbl }}</span>
                        </div>
                    </td>
                    <td class="role-col"><div class="toggle-wrap"><label class="toggle"><input type="checkbox" checked disabled><span class="toggle-slider"></span></label></div></td>
                    @foreach($roles as $r)
                    <td class="role-col"><div class="toggle-wrap" style="color:var(--text3);font-size:18px"><i class="fas fa-minus-circle"></i></div></td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Summary cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-top:20px">
    {{-- Admin card --}}
    <div class="card">
        <div class="card-body" style="text-align:center;padding:16px">
            <div style="width:44px;height:44px;border-radius:50%;background:var(--blue-light);color:var(--blue);display:flex;align-items:center;justify-content:center;font-size:18px;margin:0 auto 10px">
                <i class="fas fa-crown"></i>
            </div>
            <div style="font-weight:700;font-size:14px;margin-bottom:4px">Admin</div>
            <span class="badge badge-blue">Semua Modul</span>
        </div>
    </div>
    {{-- Dynamic role summary cards --}}
    @foreach($roles as $role)
    @php $activeCount = collect($permissions[$role->name] ?? [])->filter()->count(); @endphp
    <div class="card" id="summaryCard_{{ $role->name }}">
        <div class="card-body" style="text-align:center;padding:16px">
            <div style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;margin:0 auto 10px;background:{{ $role->color }}20;color:{{ $role->color }}">
                <i class="fas fa-user-tag"></i>
            </div>
            <div style="font-weight:700;font-size:14px;margin-bottom:4px">{{ $role->label }}</div>
            <span class="badge" style="background:{{ $role->color }}20;color:{{ $role->color }}" id="countBadge_{{ $role->name }}">
                {{ $activeCount }}/{{ count($modules) }} Modul
            </span>
        </div>
    </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
const MODULES = @json(array_keys($modules));
const ROLES   = @json($roles->pluck('name')->toArray());

async function savePermissions() {
    const btn    = document.getElementById('saveBtn');
    const status = document.getElementById('saveStatus');

    const permissions = {};
    ROLES.forEach(role => {
        permissions[role] = {};
        MODULES.forEach(module => {
            const el = document.querySelector(`.perm-toggle[data-role="${role}"][data-module="${module}"]`);
            permissions[role][module] = el ? el.checked : false;
        });
    });

    btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
    btn.disabled  = true;

    try {
        const res = await api.post('{{ route("permissions.save") }}', { permissions });
        if (res.success) {
            status.className   = 'badge badge-green';
            status.textContent = '✓ Tersimpan';
            status.style.display = '';
            toast(res.message, 'success');
            setTimeout(() => status.style.display = 'none', 3000);
        } else {
            toast('Gagal menyimpan.', 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }

    btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
    btn.disabled  = false;
}

// Live update summary badges
document.querySelectorAll('.perm-toggle').forEach(el => {
    el.addEventListener('change', () => {
        const role  = el.dataset.role;
        const count = document.querySelectorAll(`.perm-toggle[data-role="${role}"]:checked`).length;
        const badge = document.getElementById(`countBadge_${role}`);
        if (badge) badge.textContent = `${count}/${MODULES.length} Modul`;
    });
});
</script>
@endpush
