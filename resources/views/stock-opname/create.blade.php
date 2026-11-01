@extends('layouts.app')
@section('title', 'Buat Stock Opname')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-clipboard-list" style="color:var(--blue);margin-right:8px;font-size:22px;vertical-align:-2px;"></i>
            Buat Stock Opname
        </h1>
        <p class="page-subtitle">Snapshot stok sistem akan diambil saat ini</p>
    </div>
</div>

<div class="card" style="max-width:560px;">
    <div class="card-header" style="padding:20px 24px;">
        <h3 style="margin:0;font-size:15px;font-weight:600;">Informasi Opname</h3>
    </div>
    <form method="POST" action="{{ route('stock-opname.store') }}" style="padding:24px;display:flex;flex-direction:column;gap:20px;">
        @csrf

        <div>
            <label class="form-label">Gudang <span style="color:var(--red);">*</span></label>
            <select name="warehouse_id" class="form-control form-select" required>
                <option value="">-- Pilih Gudang --</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>
                        {{ $wh->warehouse_name ?: $wh->name }}
                        @if($wh->is_default) (default) @endif
                    </option>
                @endforeach
            </select>
            @error('warehouse_id')<div style="color:var(--red);font-size:13px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="form-label">Tanggal Opname <span style="color:var(--red);">*</span></label>
            <input type="date" name="opname_date" class="form-control"
                value="{{ old('opname_date', date('Y-m-d')) }}" required>
            @error('opname_date')<div style="color:var(--red);font-size:13px;margin-top:4px;">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="3"
                placeholder="Opsional...">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;gap:12px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-play"></i> Mulai Opname
            </button>
            <a href="{{ route('stock-opname.index') }}" class="btn btn-ghost">Batal</a>
        </div>
    </form>
</div>
@endsection
