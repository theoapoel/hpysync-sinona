@extends('layouts.app')
@section('title', 'Tambah User')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-plus text-blue" style="margin-right:8px"></i>Tambah User</h1>
        <p class="page-subtitle">Buat akun pengguna baru</p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-id-card text-blue" style="margin-right:6px"></i>Informasi User</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name') }}" placeholder="Nama pengguna" autofocus>
                        @error('name')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span style="color:var(--red)">*</span></label>
                        <input type="email" name="email" class="form-control"
                            value="{{ old('email') }}" placeholder="email@toko.com">
                        @error('email')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Password Lokal <span style="color:var(--text3);font-weight:400">(opsional)</span></label>
                        <input type="password" name="password" class="form-control"
                            placeholder="Kosongkan — login pakai password ERP HPY" autocomplete="new-password">
                        <p style="font-size:12px;color:var(--text3);margin-top:3px">
                            <i class="fas fa-info-circle"></i>
                            Login menggunakan password akun ERP HPY. Password ini hanya dipakai sebagai cadangan jika server ERP HPY tidak dapat dihubungi.
                        </p>
                        @error('password')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">PIN Kasir</label>
                        <input type="text" name="pin" class="form-control"
                            value="{{ old('pin') }}" placeholder="6 digit (opsional)"
                            maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                        <p style="font-size:12px;color:var(--text3);margin-top:3px">PIN untuk login cepat di POS</p>
                        @error('pin')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span style="color:var(--red)">*</span></label>
                    <select name="role" id="roleSelect" class="form-control form-select" onchange="updatePermMatrix(this.value)" required>
                        <option value="">— Pilih Role —</option>
                        @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            data-color="{{ $role->color }}"
                            {{ old('role', 'cashier') === $role->name ? 'selected' : '' }}>
                            {{ $role->label }}{{ $role->is_system ? '' : ' (custom)' }}
                        </option>
                        @endforeach
                    </select>
                    @error('role')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active','1') == '1' ? 'checked' : '' }}
                            style="width:18px;height:18px;accent-color:var(--blue);cursor:pointer">
                        <span class="form-label" style="margin-bottom:0">Aktifkan akun ini</span>
                    </label>
                </div>

                <hr class="divider">
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan User</button>
                    <a href="{{ route('users.index') }}" class="btn btn-ghost">Batal</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Permission matrix panel --}}
    <div id="permCard" style="position:sticky;top:80px"></div>
</div>
@endsection

@push('scripts')
<script>
const ROLE_PERMS = @json($rolePerms);
const ROLES_META = @json($roles->keyBy('name')->map(fn($r) => ['label'=>$r->label,'color'=>$r->color]));
const MODULE_LABELS = @json(\App\Models\RolePermission::modules());

function updatePermMatrix(roleName) {
    const panel = document.getElementById('permCard');
    if (!roleName || !ROLE_PERMS[roleName]) {
        panel.innerHTML = '';
        return;
    }
    const meta  = ROLES_META[roleName];
    const perms = ROLE_PERMS[roleName];
    const total = Object.keys(MODULE_LABELS).length;
    const count = Object.values(perms).filter(Boolean).length;

    const rows = Object.entries(MODULE_LABELS).map(([key, mod]) => `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border)">
            <div style="display:flex;align-items:center;gap:8px;font-size:13px">
                <i class="fas ${mod.icon}" style="width:16px;text-align:center;color:var(--text3)"></i>
                ${mod.label}
            </div>
            ${perms[key]
                ? `<span style="color:var(--green);font-size:12px;font-weight:700"><i class="fas fa-check-circle"></i> Ya</span>`
                : `<span style="color:var(--text3);font-size:12px"><i class="fas fa-minus-circle"></i> Tidak</span>`}
        </div>`).join('');

    panel.innerHTML = `
        <div class="card">
            <div class="card-header">
                <span class="card-title" style="display:flex;align-items:center;gap:8px">
                    <span style="width:12px;height:12px;border-radius:50%;background:${meta.color};display:inline-block"></span>
                    ${meta.label}
                </span>
                <span class="badge" style="background:${meta.color}20;color:${meta.color}">${count}/${total} Modul</span>
            </div>
            <div class="card-body" style="padding:12px 20px">${rows}</div>
            <div style="padding:10px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text3)">
                <i class="fas fa-info-circle"></i> Atur hak akses di <a href="{{ route('permissions.index') }}" style="color:var(--blue)">Hak Akses</a>
            </div>
        </div>`;
}

// Init
updatePermMatrix(document.getElementById('roleSelect').value);
</script>
@endpush
