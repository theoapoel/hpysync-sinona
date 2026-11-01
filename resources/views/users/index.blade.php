@extends('layouts.app')
@section('title', 'Manajemen User')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-users-cog text-blue" style="margin-right:8px"></i>Manajemen User</h1>
        <p class="page-subtitle">Kelola akun pengguna dan hak akses sistem</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Tambah User
    </a>
</div>

{{-- Search & Filter --}}
<div class="card" style="margin-bottom:16px">
    <div class="card-body" style="padding:14px 20px">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:220px">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control"
                    placeholder="Nama atau email..." value="{{ request('search') }}" autofocus>
            </div>
            <div>
                <label class="form-label">Role</label>
                <select name="role" class="form-control form-select" style="width:160px">
                    <option value="">Semua Role</option>
                    <option value="admin"   {{ request('role') === 'admin'   ? 'selected' : '' }}>Admin</option>
                    <option value="manager" {{ request('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                    <option value="cashier" {{ request('role') === 'cashier' ? 'selected' : '' }}>Kasir</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Cari</button>
            @if(request('search') || request('role'))
                <a href="{{ route('users.index') }}" class="btn btn-ghost"><i class="fas fa-times"></i> Reset</a>
            @endif
        </form>
    </div>
</div>

{{-- User Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-users text-blue" style="margin-right:6px"></i>
            Daftar User <span style="font-weight:400;color:var(--text3)">({{ $users->total() }})</span>
        </span>
        <div style="display:flex;gap:8px">
            @foreach(['admin'=>['badge-blue','Admin'], 'manager'=>['badge-yellow','Manager'], 'cashier'=>['badge-green','Kasir']] as $r => [$cls, $lbl])
                <span class="badge {{ $cls }}">{{ \App\Models\User::where('role',$r)->count() }} {{ $lbl }}</span>
            @endforeach
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>PIN</th>
                    <th>Status</th>
                    <th>Bergabung</th>
                    <th style="width:100px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px">
                            <div style="width:38px;height:38px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;color:#fff;
                                background:{{ $user->role === 'admin' ? 'var(--blue)' : ($user->role === 'manager' ? '#E37400' : 'var(--green)') }}">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:14px">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="badge badge-blue" style="font-size:10px;margin-left:4px">Anda</span>
                                    @endif
                                </div>
                                <div style="font-size:12px;color:var(--text3)">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($user->role === 'admin')
                            <span class="badge badge-blue"><i class="fas fa-crown" style="font-size:10px;margin-right:4px"></i>Admin</span>
                        @elseif($user->role === 'manager')
                            <span class="badge badge-yellow"><i class="fas fa-user-tie" style="font-size:10px;margin-right:4px"></i>Manager</span>
                        @else
                            <span class="badge badge-green"><i class="fas fa-cash-register" style="font-size:10px;margin-right:4px"></i>Kasir</span>
                        @endif
                    </td>
                    <td>
                        @if($user->pin)
                            <span style="font-family:monospace;letter-spacing:3px;color:var(--text2);font-size:13px">••••••</span>
                        @else
                            <span class="text-muted text-sm">—</span>
                        @endif
                    </td>
                    <td>
                        <button onclick="toggleActive({{ $user->id }}, this)"
                            data-active="{{ $user->is_active ? '1' : '0' }}"
                            class="btn btn-sm {{ $user->is_active ? 'btn-success' : 'btn-ghost' }}"
                            {{ $user->id === auth()->id() ? 'disabled title="Tidak dapat mengubah status akun sendiri"' : '' }}
                            style="{{ $user->id === auth()->id() ? 'opacity:.5;cursor:not-allowed' : '' }}">
                            <i class="fas {{ $user->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </button>
                    </td>
                    <td style="color:var(--text3);font-size:13px">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            @if($user->id !== auth()->id())
                            <button onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->name) }}')"
                                class="btn btn-ghost btn-sm" style="color:var(--red)" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:48px;color:var(--text3)">
                        <i class="fas fa-users" style="font-size:36px;margin-bottom:12px;display:block;opacity:.3"></i>
                        Tidak ada user ditemukan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border)">
        {{ $users->links() }}
    </div>
    @endif
</div>

{{-- Hapus Modal --}}
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" style="color:var(--red)"><i class="fas fa-trash" style="margin-right:8px"></i>Hapus User</span>
            <button onclick="document.getElementById('deleteModal').classList.remove('show')" class="btn btn-ghost btn-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>Hapus user <strong id="deleteUserName"></strong>?</p>
            <p style="font-size:13px;color:var(--text3);margin-top:8px">
                Jika user memiliki data transaksi, penghapusan akan ditolak.
                Gunakan toggle <strong>Status</strong> untuk menonaktifkan user.
            </p>
        </div>
        <div class="modal-footer">
            <button onclick="document.getElementById('deleteModal').classList.remove('show')" class="btn btn-ghost">Batal</button>
            <form id="deleteForm" method="POST" style="display:inline">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Ya, Hapus</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function toggleActive(userId, btn) {
    btn.disabled = true;
    try {
        const res = await api.post(`{{ url('users') }}/${userId}/toggle-active`);
        if (res.success) {
            btn.dataset.active = res.is_active ? '1' : '0';
            btn.className = `btn btn-sm ${res.is_active ? 'btn-success' : 'btn-ghost'}`;
            btn.innerHTML = `<i class="fas ${res.is_active ? 'fa-toggle-on' : 'fa-toggle-off'}"></i> ${res.is_active ? 'Aktif' : 'Nonaktif'}`;
            toast(res.message, 'success');
        } else {
            toast(res.error, 'error');
        }
    } catch(e) {
        toast('Gagal mengubah status user.', 'error');
    }
    btn.disabled = false;
}

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
