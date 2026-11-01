<div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
    <div>
        <label class="form-label">Kode Kupon <span style="color:var(--red)">*</span></label>
        <input type="text" name="code" class="form-control" placeholder="cth: DISKON10" maxlength="50" required
            style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()">
    </div>
    <div>
        <label class="form-label">Deskripsi</label>
        <input type="text" name="description" class="form-control" placeholder="Opsional" maxlength="255">
    </div>
    <div style="display:flex;gap:12px">
        <div style="flex:1">
            <label class="form-label">Tipe Diskon <span style="color:var(--red)">*</span></label>
            <select name="discount_type" class="form-control" required>
                <option value="fixed">Nominal (Rp)</option>
                <option value="percent">Persentase (%)</option>
            </select>
        </div>
        <div style="flex:1">
            <label class="form-label">Nilai Diskon <span style="color:var(--red)">*</span></label>
            <input type="number" name="discount_value" class="form-control" placeholder="0" min="0.01" step="0.01" required>
        </div>
    </div>
    <div style="display:flex;gap:12px">
        <div style="flex:1">
            <label class="form-label">Min. Pembelian (Rp)</label>
            <input type="number" name="min_purchase" class="form-control" placeholder="0 = tanpa minimum" min="0" step="1000">
        </div>
        <div style="flex:1">
            <label class="form-label">Maks. Pemakaian</label>
            <input type="number" name="max_uses" class="form-control" placeholder="kosong = tidak terbatas" min="1" step="1">
        </div>
    </div>
    <div style="display:flex;gap:12px">
        <div style="flex:1">
            <label class="form-label">Berlaku Mulai</label>
            <input type="date" name="valid_from" class="form-control">
        </div>
        <div style="flex:1">
            <label class="form-label">Berlaku Sampai</label>
            <input type="date" name="valid_until" class="form-control">
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" name="is_active" id="{{ isset($edit) ? 'is_active_edit' : 'is_active_add' }}" value="1" checked style="width:16px;height:16px">
        <label for="{{ isset($edit) ? 'is_active_edit' : 'is_active_add' }}" style="font-size:14px;font-weight:500;cursor:pointer">Kupon Aktif</label>
    </div>
</div>
