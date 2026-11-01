@extends('layouts.app')
@section('title', 'Edit User: ' . $user->name)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-user-edit text-blue" style="margin-right:8px"></i>Edit User</h1>
        <p class="page-subtitle">{{ $user->name }} &mdash; {{ $user->email }}</p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

    <div class="card">
        <div class="card-header">
            <span class="card-title" style="display:flex;align-items:center;gap:10px">
                @php $currentRole = $roles->firstWhere('name', $user->role); @endphp
                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff;background:{{ $currentRole?->color ?? '#ccc' }}">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                {{ $user->name }}
                @if($user->id === auth()->id())
                    <span class="badge badge-blue" style="font-size:11px">Anda</span>
                @endif
            </span>
            <span class="badge {{ $user->is_active ? 'badge-green' : 'badge-gray' }}">
                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf @method('PUT')

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $user->name) }}" autofocus>
                        @error('name')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span style="color:var(--red)">*</span></label>
                        <input type="email" name="email" class="form-control"
                            value="{{ old('email', $user->email) }}">
                        @error('email')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-control"
                        placeholder="Kosongkan jika tidak diubah" autocomplete="new-password">
                    <p style="font-size:12px;color:var(--text3);margin-top:3px">Min. 8 karakter. Biarkan kosong untuk mempertahankan password lama.</p>
                    @error('password')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">PIN Kasir</label>
                    @if($user->pin)
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:8px 12px;background:var(--surface2);border-radius:8px">
                        <i class="fas fa-key text-blue" style="font-size:13px"></i>
                        <span style="font-family:monospace;letter-spacing:4px">••••••</span>
                        <span class="badge badge-green" style="font-size:11px">PIN Aktif</span>
                    </div>
                    @endif
                    <input type="text" name="pin" class="form-control"
                        placeholder="{{ $user->pin ? 'Masukkan PIN baru untuk mengganti' : '6 digit (opsional)' }}"
                        maxlength="6" pattern="[0-9]{6}" inputmode="numeric">
                    @if($user->pin)
                    <label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer">
                        <input type="checkbox" name="clear_pin" value="1"
                            style="width:16px;height:16px;cursor:pointer;accent-color:var(--red)">
                        <span style="font-size:13px;color:var(--red)">Hapus PIN</span>
                    </label>
                    @endif
                    @error('pin')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Role <span style="color:var(--red)">*</span></label>
                    @if($user->id === auth()->id())
                    <div class="alert alert-warning" style="margin-bottom:8px">
                        <i class="fas fa-exclamation-triangle"></i> Anda tidak dapat mengubah role akun Anda sendiri.
                    </div>
                    @endif
                    <select name="role" id="roleSelect" class="form-control form-select"
                        onchange="updatePermMatrix(this.value)"
                        {{ $user->id === auth()->id() ? 'disabled' : '' }} required>
                        @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            data-color="{{ $role->color }}"
                            {{ old('role', $user->role) === $role->name ? 'selected' : '' }}>
                            {{ $role->label }}{{ $role->is_system ? '' : ' (custom)' }}
                        </option>
                        @endforeach
                    </select>
                    @if($user->id === auth()->id())
                    <input type="hidden" name="role" value="{{ $user->role }}">
                    @endif
                    @error('role')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:10px;cursor:{{ $user->id === auth()->id() ? 'not-allowed' : 'pointer' }}">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                            {{ old('is_active', $user->is_active ? '1' : '0') == '1' ? 'checked' : '' }}
                            {{ $user->id === auth()->id() ? 'disabled' : '' }}
                            style="width:18px;height:18px;accent-color:var(--blue)">
                        <div>
                            <span class="form-label" style="margin-bottom:0">Akun Aktif</span>
                            @if($user->id === auth()->id())
                            <p style="font-size:11px;color:var(--text3);margin-top:2px">Tidak dapat menonaktifkan akun sendiri</p>
                            @endif
                        </div>
                    </label>
                </div>

                <hr class="divider">
                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                    <a href="{{ route('users.index') }}" class="btn btn-ghost">Batal</a>
                    @if($user->id !== auth()->id())
                    <button type="button" onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->name) }}')"
                        class="btn btn-ghost" style="color:var(--red);margin-left:auto">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Permission matrix panel --}}
    <div id="permCard" style="position:sticky;top:80px"></div>
</div>

{{-- Delete modal --}}
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" style="color:var(--red)"><i class="fas fa-trash" style="margin-right:8px"></i>Hapus User</span>
            <button onclick="document.getElementById('deleteModal').classList.remove('show')" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p>Hapus user <strong id="deleteUserName"></strong>?</p>
            <p style="font-size:13px;color:var(--text3);margin-top:8px">Jika memiliki transaksi, penghapusan ditolak. Nonaktifkan saja.</p>
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('deleteModal').classList.remove('show')" class="btn btn-ghost">Batal</button>
            <form id="deleteForm" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Hapus</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const ROLE_PERMS  = @json($rolePerms);
const ROLES_META  = @json($roles->keyBy('name')->map(fn($r) => ['label'=>$r->label,'color'=>$r->color]));
const MODULE_LABELS = @json(\App\Models\RolePermission::modules());

function updatePermMatrix(roleName) {
    const panel = document.getElementById('permCard');
    if (!roleName || !ROLE_PERMS[roleName]) { panel.innerHTML = ''; return; }
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
                <i class="fas fa-info-circle"></i> Atur di <a href="{{ route('permissions.index') }}" style="color:var(--blue)">Hak Akses</a>
            </div>
        </div>`;
}

updatePermMatrix(document.getElementById('roleSelect').value);

function confirmDelete(userId, name) {
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteForm').action = `/users/${userId}`;
    document.getElementById('deleteModal').classList.add('show');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
</script>
@endpush
