@extends('layouts.app')
@section('title', 'Manajemen Role')

@push('styles')
<style>
.color-dot { width:14px; height:14px; border-radius:50%; display:inline-block; flex-shrink:0; }
.color-swatch { width:30px; height:30px; border-radius:6px; border:2px solid transparent; cursor:pointer; transition:all .15s; }
.color-swatch.selected, .color-swatch:hover { border-color: #202124; transform:scale(1.1); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-layer-group text-blue" style="margin-right:8px"></i>Manajemen Role</h1>
        <p class="page-subtitle">Kelola role dan hak akses pengguna</p>
    </div>
    <button onclick="document.getElementById('addModal').classList.add('show')" class="btn btn-primary">
        <i class="fas fa-plus"></i> Tambah Role
    </button>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-layer-group text-blue" style="margin-right:6px"></i>
            Daftar Role <span style="font-weight:400;color:var(--text3)">({{ $roles->count() }})</span>
        </span>
        <a href="{{ route('permissions.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-shield-alt"></i> Atur Hak Akses
        </a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Slug</th>
                    <th>Deskripsi</th>
                    <th>User</th>
                    <th>Tipe</th>
                    <th style="width:110px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <span class="color-dot" style="background:{{ $role->color }}"></span>
                            <span style="font-weight:700;font-size:14px">{{ $role->label }}</span>
                        </div>
                    </td>
                    <td>
                        <code style="font-size:13px;background:var(--surface2);padding:2px 8px;border-radius:4px;color:var(--text2)">{{ $role->name }}</code>
                    </td>
                    <td style="font-size:13px;color:var(--text3);max-width:280px">{{ $role->description ?: '—' }}</td>
                    <td>
                        <span class="badge badge-blue">{{ $role->users_count }} user</span>
                    </td>
                    <td>
                        @if($role->is_system)
                            <span class="badge badge-gray"><i class="fas fa-lock" style="font-size:10px;margin-right:3px"></i>Sistem</span>
                        @else
                            <span class="badge badge-green"><i class="fas fa-user-cog" style="font-size:10px;margin-right:3px"></i>Custom</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <button onclick='openEdit(@json($role))' class="btn btn-ghost btn-sm" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            @if(!$role->is_system)
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirmDel(this, '{{ addslashes($role->label) }}', {{ $role->users_count }})">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Info card --}}
<div class="card" style="margin-top:16px">
    <div class="card-body" style="padding:14px 20px">
        <div style="display:flex;gap:20px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2)">
                <i class="fas fa-lock" style="color:var(--text3)"></i>
                <span><strong>Role Sistem</strong> (admin, manager, kasir) tidak dapat dihapus dan slug-nya tidak dapat diubah.</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2)">
                <i class="fas fa-info-circle" style="color:var(--blue)"></i>
                <span>Role baru ditambahkan tanpa hak akses. Atur di <a href="{{ route('permissions.index') }}" style="color:var(--blue)">Hak Akses</a>.</span>
            </div>
        </div>
    </div>
</div>

{{-- Modul yang tersedia --}}
<div class="card" style="margin-top:16px">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-th-list text-blue" style="margin-right:6px"></i>Modul yang Dapat Dikonfigurasi</span>
    </div>
    <div class="card-body" style="padding:16px 20px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
            @foreach($modules as $key => $meta)
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--surface2);border-radius:8px">
                <i class="fas {{ $meta['icon'] }}" style="color:var(--blue);width:16px;text-align:center"></i>
                <div>
                    <div style="font-size:13px;font-weight:600">{{ $meta['label'] }}</div>
                    <div style="font-size:11px;color:var(--text3);font-family:monospace">{{ $key }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Modal Tambah Role --}}
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-plus-circle text-blue" style="margin-right:8px"></i>Tambah Role Baru</span>
            <button onclick="document.getElementById('addModal').classList.remove('show')" class="btn btn-ghost btn-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('roles.store') }}">
            @csrf
            <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Nama Role <span style="color:var(--red)">*</span></label>
                    <input type="text" name="label" id="addLabel" class="form-control"
                        placeholder="Contoh: Supervisor, Gudang, Akuntan"
                        oninput="genSlug(this.value)" required maxlength="50">
                    @error('label')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        Slug <span style="color:var(--red)">*</span>
                        <span id="slugStatus" style="font-size:11px;margin-left:6px"></span>
                    </label>
                    <input type="text" name="name" id="addSlug" class="form-control"
                        placeholder="huruf_kecil_underscore" required maxlength="50"
                        pattern="^[a-z][a-z0-9_]*$" oninput="checkSlug(this.value)">
                    <p style="font-size:12px;color:var(--text3);margin-top:4px">Identifier unik, hanya huruf kecil, angka, underscore. Tidak dapat diubah setelah dibuat.</p>
                    @error('name')<p style="font-size:12px;color:var(--red);margin-top:4px">{{ $message }}</p>@enderror
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="description" class="form-control"
                        placeholder="Deskripsi singkat role ini..." maxlength="200">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Warna Badge</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        @php
                        $presets = ['#4285F4','#EA4335','#34A853','#FBBC05','#E37400','#9C27B0','#00897B','#E91E63','#607D8B','#795548'];
                        @endphp
                        @foreach($presets as $c)
                        <div class="color-swatch" style="background:{{ $c }}" data-color="{{ $c }}"
                            onclick="selectColor('{{ $c }}', this)"></div>
                        @endforeach
                        <input type="color" id="customColor" value="#4285F4"
                            oninput="selectColor(this.value, null)"
                            style="width:30px;height:30px;border:none;border-radius:6px;cursor:pointer;padding:0">
                    </div>
                    <input type="hidden" name="color" id="colorInput" value="#4285F4">
                </div>

                <div style="display:flex;align-items:center;gap:10px;padding:12px;background:var(--surface2);border-radius:8px">
                    <div id="previewBadge" style="display:inline-flex;align-items:center;padding:4px 12px;border-radius:12px;font-size:13px;font-weight:600;background:#4285F420;color:#4285F4">
                        Preview Role
                    </div>
                    <span style="font-size:12px;color:var(--text3)">Tampilan badge di tabel user</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addModal').classList.remove('show')" class="btn btn-ghost">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Role</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Edit Role --}}
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title"><i class="fas fa-edit text-blue" style="margin-right:8px"></i>Edit Role</span>
            <button onclick="document.getElementById('editModal').classList.remove('show')" class="btn btn-ghost btn-sm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Slug (tidak dapat diubah)</label>
                <input type="text" id="editSlugDisplay" class="form-control" disabled style="background:var(--surface2);color:var(--text3);font-family:monospace">
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Nama Role <span style="color:var(--red)">*</span></label>
                <input type="text" id="editLabel" class="form-control"
                    oninput="updateEditPreview()" maxlength="50">
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Deskripsi</label>
                <input type="text" id="editDescription" class="form-control" maxlength="200">
            </div>

            <div class="form-group" style="margin-bottom:0">
                <label class="form-label">Warna</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    @foreach($presets as $c)
                    <div class="color-swatch edit-swatch" style="background:{{ $c }}" data-color="{{ $c }}"
                        onclick="selectEditColor('{{ $c }}', this)"></div>
                    @endforeach
                    <input type="color" id="editCustomColor"
                        oninput="selectEditColor(this.value, null)"
                        style="width:30px;height:30px;border:none;border-radius:6px;cursor:pointer;padding:0">
                </div>
            </div>

            <div style="padding:12px;background:var(--surface2);border-radius:8px">
                <div id="editPreviewBadge" style="display:inline-flex;align-items:center;padding:4px 12px;border-radius:12px;font-size:13px;font-weight:600">
                    Preview
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="document.getElementById('editModal').classList.remove('show')" class="btn btn-ghost">Batal</button>
            <button onclick="saveEdit()" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let selectedColor = '#4285F4';
let editRoleId    = null;
let editColor     = '#4285F4';

// ── Add modal ────────────────────────────────────────────────────────────────
let slugTimeout = null;

function genSlug(label) {
    const slug = label.toLowerCase().trim()
        .replace(/[^a-z0-9\s_]/g, '')
        .replace(/\s+/g, '_');
    document.getElementById('addSlug').value = slug;
    checkSlug(slug);
    updateAddPreview();
}

function checkSlug(slug) {
    clearTimeout(slugTimeout);
    const el = document.getElementById('slugStatus');
    if (!slug) { el.textContent = ''; return; }
    slugTimeout = setTimeout(async () => {
        const res = await api.post('{{ route("roles.slug") }}', { label: slug });
        if (res.available) {
            el.innerHTML = '<span style="color:var(--green)"><i class="fas fa-check-circle"></i> Tersedia</span>';
        } else {
            el.innerHTML = '<span style="color:var(--red)"><i class="fas fa-times-circle"></i> Sudah digunakan</span>';
        }
    }, 400);
}

function selectColor(hex, el) {
    selectedColor = hex;
    document.getElementById('colorInput').value = hex;
    document.getElementById('customColor').value = hex;
    document.querySelectorAll('.color-swatch:not(.edit-swatch)').forEach(s => s.classList.remove('selected'));
    if (el) el.classList.add('selected');
    updateAddPreview();
}

function updateAddPreview() {
    const label  = document.getElementById('addLabel').value || 'Preview Role';
    const badge  = document.getElementById('previewBadge');
    badge.style.background = selectedColor + '20';
    badge.style.color      = selectedColor;
    badge.textContent      = label;
}

// ── Edit modal ───────────────────────────────────────────────────────────────
function openEdit(role) {
    editRoleId = role.id;
    editColor  = role.color;
    document.getElementById('editSlugDisplay').value = role.name;
    document.getElementById('editLabel').value       = role.label;
    document.getElementById('editDescription').value = role.description || '';
    document.getElementById('editCustomColor').value = role.color;
    document.querySelectorAll('.edit-swatch').forEach(s => {
        s.classList.toggle('selected', s.dataset.color === role.color);
    });
    updateEditPreview();
    document.getElementById('editModal').classList.add('show');
}

function selectEditColor(hex, el) {
    editColor = hex;
    document.getElementById('editCustomColor').value = hex;
    document.querySelectorAll('.edit-swatch').forEach(s => s.classList.remove('selected'));
    if (el) el.classList.add('selected');
    updateEditPreview();
}

function updateEditPreview() {
    const label = document.getElementById('editLabel').value || 'Preview';
    const badge = document.getElementById('editPreviewBadge');
    badge.style.background = editColor + '20';
    badge.style.color      = editColor;
    badge.textContent      = label;
}

async function saveEdit() {
    const label       = document.getElementById('editLabel').value.trim();
    const description = document.getElementById('editDescription').value.trim();
    if (!label) { toast('Nama role tidak boleh kosong.', 'error'); return; }

    try {
        const res = await api.post(`{{ url('roles') }}/${editRoleId}?_method=PUT`, { label, description, color: editColor });
        if (res.success) {
            toast(res.message, 'success');
            document.getElementById('editModal').classList.remove('show');
            setTimeout(() => location.reload(), 800);
        } else {
            toast(res.message || 'Gagal menyimpan.', 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
}

// ── Delete confirm ───────────────────────────────────────────────────────────
function confirmDel(form, label, userCount) {
    if (userCount > 0) {
        toast(`Role "${label}" masih digunakan ${userCount} user.`, 'error');
        return false;
    }
    return confirm(`Hapus role "${label}"? Aksi ini tidak dapat dibatalkan.`);
}

// ── Close modals on backdrop click ──────────────────────────────────────────
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('show');
    });
});

// Init add modal color selection
document.querySelectorAll('.color-swatch:not(.edit-swatch)')[0]?.classList.add('selected');
</script>
@endpush
