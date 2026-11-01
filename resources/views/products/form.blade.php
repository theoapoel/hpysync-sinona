@extends('layouts.app')
@section('title', isset($product) ? 'Edit Produk' : 'Tambah Produk')
@section('content')
<div class="page-header">
    <div>
        <div class="page-title">{{ isset($product) ? 'Edit Produk' : 'Tambah Produk' }}</div>
    </div>
    <a href="{{ route('products.index') }}" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Kembali</a>
</div>
<div class="card" style="max-width:760px">
    <div class="card-body">
        <form method="POST" action="{{ isset($product) ? route('products.update',$product) : route('products.store') }}">
            @csrf @if(isset($product)) @method('PUT') @endif
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Produk *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name',$product->name??'') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">SKU *</label>
                    <input type="text" name="sku" class="form-control" value="{{ old('sku',$product->sku??'') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Barcode</label>
                    <input type="text" name="barcode" class="form-control" value="{{ old('barcode',$product->barcode??'') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Item Group</label>
                    <select name="category_id" class="form-control form-select">
                        <option value="">-- Tanpa Item Group --</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" {{ old('category_id',$product->category_id??'')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="item_category_id" class="form-control form-select">
                        <option value="">-- Tanpa Kategori --</option>
                        @foreach($itemCategories as $c)<option value="{{ $c->id }}" {{ old('item_category_id',$product->item_category_id??'')==$c->id?'selected':'' }}>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga Jual *</label>
                    <input type="number" name="price" class="form-control" value="{{ old('price',$product->price??'') }}" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Harga Pokok</label>
                    <input type="number" name="cost_price" class="form-control" value="{{ old('cost_price',$product->cost_price??'') }}" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Stok *</label>
                    <input type="number" name="stock" class="form-control" value="{{ old('stock',$product->stock??0) }}" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Stok Minimum</label>
                    <input type="number" name="min_stock" class="form-control" value="{{ old('min_stock',$product->min_stock??0) }}" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" class="form-control" value="{{ old('unit',$product->unit??'pcs') }}" placeholder="pcs, kg, liter...">
                </div>
                <div class="form-group">
                    <label class="form-label">Pajak (%)</label>
                    <input type="number" name="tax_rate" class="form-control" value="{{ old('tax_rate',$product->tax_rate??0) }}" min="0" max="100" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description',$product->description??'') }}</textarea>
            </div>
            <div style="display:flex;gap:20px;margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active',($product->is_active??true))?'checked':'' }}> Produk Aktif
                </label>
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                    <input type="checkbox" name="track_stock" value="1" {{ old('track_stock',($product->track_stock??true))?'checked':'' }}> Pantau Stok
                </label>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ isset($product)?'Perbarui':'Simpan' }}</button>
                <a href="{{ route('products.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
