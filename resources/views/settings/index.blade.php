@extends('layouts.app')
@section('title', 'Pengaturan Toko')

@section('content')
<style>
    .ig-chip { display:inline-flex; align-items:center; padding:6px 12px; border:2px solid var(--border); border-radius:20px; font-size:12px; font-weight:600; cursor:pointer; user-select:none; transition:all .15s; }
    .ig-chip:hover { border-color:var(--blue); }
    .ig-chip.on { background:var(--blue); border-color:var(--blue); color:#fff; }
</style>
<div class="page-header">
    <div>
        <h1 class="page-title">
            <i class="fas fa-store text-blue" style="margin-right:8px;"></i>Pengaturan Toko
        </h1>
        <p class="page-subtitle">Informasi toko yang tampil di struk pembayaran</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    {{-- Form Pengaturan --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-edit text-blue" style="margin-right:6px;"></i>Informasi Toko</span>
            <span id="saveStatus" class="badge badge-gray" style="display:none;"></span>
        </div>
        <div class="card-body">
            <form id="storeSettingsForm">
                <div class="form-group">
                    <label class="form-label">Nama Toko</label>
                    <input type="text" name="store_name" class="form-control"
                        value="{{ $settings['store_name'] }}"
                        placeholder="Contoh: HPYSync" maxlength="100"
                        oninput="updatePreview()">
                    @error('store_name')<p style="font-size:12px;color:var(--red);margin-top:4px;">{{ $message }}</p>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Tagline / Slogan</label>
                    <input type="text" name="store_tagline" class="form-control"
                        value="{{ $settings['store_tagline'] }}"
                        placeholder="Contoh: Point of Sale System" maxlength="150"
                        oninput="updatePreview()">
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Toko</label>
                    <textarea name="store_address" class="form-control" rows="2"
                        placeholder="Jl. Contoh No. 1, Jakarta" maxlength="300"
                        oninput="updatePreview()">{{ $settings['store_address'] }}</textarea>
                </div>

                <div class="grid-2 gap-3">
                    <div class="form-group">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="store_phone" class="form-control"
                            value="{{ $settings['store_phone'] }}"
                            placeholder="021-12345678" maxlength="30"
                            oninput="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="store_email" class="form-control"
                            value="{{ $settings['store_email'] }}"
                            placeholder="info@toko.com" maxlength="100"
                            oninput="updatePreview()">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Pesan Footer Struk</label>
                    <input type="text" name="receipt_footer" class="form-control"
                        value="{{ $settings['receipt_footer'] }}"
                        placeholder="Terima kasih atas kunjungan Anda!" maxlength="200"
                        oninput="updatePreview()">
                    <p style="font-size:12px;color:var(--text3);margin-top:4px;">Pesan yang tampil di bagian bawah struk</p>
                </div>

                <div class="form-group">
                    <label class="form-label">POS Class</label>
                    <input type="text" name="pos_class" class="form-control"
                        value="{{ $settings['pos_class'] }}"
                        placeholder="Contoh: Retail, Wholesale, B2B…" maxlength="100">
                    <p style="font-size:12px;color:var(--text3);margin-top:4px;">Digunakan saat sinkronisasi transaksi ke ERP HPY</p>
                </div>

                <div class="form-group">
                    <label class="form-label">🧾 Thermal Printer (ESC/POS)</label>
                    <p style="font-size:12px;color:var(--text3);margin:2px 0 8px;">
                        Untuk fitur "Print Thermal" langsung ke printer 58mm.
                        Server ini terdeteksi sebagai <strong>{{ $settings['os_family'] }}</strong> —
                        field yang aktif ditandai di bawah.
                    </p>

                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px">
                            <label class="form-label" style="font-size:12px">
                                Device Path (Linux){{ $settings['os_family'] !== 'Windows' ? ' • aktif' : '' }}
                            </label>
                            <input type="text" name="thermal_printer_device" class="form-control"
                                value="{{ $settings['thermal_printer_device'] }}"
                                placeholder="/dev/usb/lp1" maxlength="255">
                        </div>
                        <div style="flex:1;min-width:200px">
                            <label class="form-label" style="font-size:12px">
                                Nama Printer (Windows){{ $settings['os_family'] === 'Windows' ? ' • aktif' : '' }}
                            </label>
                            <input type="text" name="thermal_printer_name" class="form-control"
                                value="{{ $settings['thermal_printer_name'] }}"
                                placeholder="EPPOS58" maxlength="255">
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--text3);margin-top:4px;">
                        Windows: gunakan nama <em>shared printer</em> (mis. <code>EPPOS58</code>).
                        Linux: path device USB printer (mis. <code>/dev/usb/lp1</code>).
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">📱 API Aplikasi Tablet (Kitchen Monitor)</label>
                    <p style="font-size:12px;color:var(--text3);margin:2px 0 8px;">
                        Kredensial untuk aplikasi tablet dapur. Isi Server URL di aplikasi tablet dengan alamat
                        server ini, lalu masukkan API Key &amp; Secret di bawah. Kosongkan keduanya untuk menonaktifkan API.
                    </p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                        <div style="flex:1;min-width:200px">
                            <label class="form-label" style="font-size:12px">API Key</label>
                            <input type="text" name="mobile_api_key" id="mobileApiKey" class="form-control"
                                value="{{ $settings['mobile_api_key'] }}" maxlength="64" autocomplete="off">
                        </div>
                        <div style="flex:1;min-width:200px">
                            <label class="form-label" style="font-size:12px">API Secret</label>
                            <input type="text" name="mobile_api_secret" id="mobileApiSecret" class="form-control"
                                value="{{ $settings['mobile_api_secret'] }}" maxlength="64" autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="generateMobileApiCreds()"
                            style="white-space:nowrap"><i class="fas fa-dice"></i> Generate</button>
                    </div>
                    <p style="font-size:12px;color:var(--text3);margin-top:4px;">
                        Aplikasi tablet mengirim header <code>X-API-KEY</code> dan <code>X-API-SECRET</code> pada setiap request.
                        Jangan lupa klik <strong>Simpan</strong> setelah generate.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Tampilan Produk di Kasir</label>
                    <div style="display:flex;gap:12px;margin-top:4px">
                        <label style="flex:1;cursor:pointer">
                            <input type="radio" name="pos_product_display" value="image"
                                {{ ($settings['pos_product_display'] ?? 'image') === 'image' ? 'checked' : '' }}
                                style="display:none" class="prod-display-radio">
                            <div class="prod-display-option" style="border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                                <div style="font-size:28px;margin-bottom:6px">🖼️</div>
                                <div style="font-size:13px;font-weight:600">Gambar + Teks</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">Tampilkan foto produk</div>
                            </div>
                        </label>
                        <label style="flex:1;cursor:pointer">
                            <input type="radio" name="pos_product_display" value="text"
                                {{ ($settings['pos_product_display'] ?? 'image') === 'text' ? 'checked' : '' }}
                                style="display:none" class="prod-display-radio">
                            <div class="prod-display-option" style="border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                                <div style="font-size:28px;margin-bottom:6px">📝</div>
                                <div style="font-size:13px;font-weight:600">Teks Saja</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">Lebih banyak produk terlihat</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Cakupan Laporan Transaksi</label>
                    <div style="display:flex;gap:12px;margin-top:4px">
                        <label style="flex:1;cursor:pointer">
                            <input type="radio" name="report_scope" value="all"
                                {{ ($settings['report_scope'] ?: 'all') === 'all' ? 'checked' : '' }}
                                style="display:none" class="report-scope-radio">
                            <div class="report-scope-option" style="border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                                <div style="font-size:28px;margin-bottom:6px">🏪</div>
                                <div style="font-size:13px;font-weight:600">Semua Transaksi</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">Satu laporan toko, tanpa membedakan kasir/shift</div>
                            </div>
                        </label>
                        <label style="flex:1;cursor:pointer">
                            <input type="radio" name="report_scope" value="user"
                                {{ ($settings['report_scope'] ?: 'all') === 'user' ? 'checked' : '' }}
                                style="display:none" class="report-scope-radio">
                            <div class="report-scope-option" style="border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                                <div style="font-size:28px;margin-bottom:6px">👤</div>
                                <div style="font-size:13px;font-weight:600">Per Kasir</div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">Kasir hanya melihat transaksinya sendiri</div>
                            </div>
                        </label>
                    </div>
                    <p style="font-size:12px;color:var(--text3);margin-top:6px">
                        Manager &amp; admin selalu melihat seluruh transaksi. Pilihan ini menentukan apa yang dilihat kasir.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Rentang Tanggal Laporan</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
                        <div style="display:flex;align-items:center;gap:10px;border:2px solid var(--border);border-radius:10px;padding:12px 14px;opacity:.7">
                            <div style="font-size:20px">🛡️</div>
                            <div style="flex:1">
                                <div style="font-size:13px;font-weight:600">Admin</div>
                                <div style="font-size:11px;color:var(--text3)">Selalu bebas memilih rentang tanggal</div>
                            </div>
                            <span style="font-size:11px;font-weight:600;color:var(--text3)">Bebas</span>
                        </div>
                        @foreach($reportRoles as $role)
                            @php $locked = in_array($role->name, $lockedReportRoles, true); @endphp
                            <label style="display:flex;align-items:center;gap:10px;border:2px solid var(--border);border-radius:10px;padding:12px 14px;cursor:pointer">
                                <input type="checkbox" name="report_date_limit_roles[]" value="{{ $role->name }}"
                                    {{ $locked ? 'checked' : '' }} style="width:16px;height:16px;cursor:pointer">
                                <div style="flex:1">
                                    <div style="font-size:13px;font-weight:600">{{ $role->label ?: $role->name }}</div>
                                    <div style="font-size:11px;color:var(--text3)">Centang = laporan terkunci ke hari ini</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <p style="font-size:12px;color:var(--text3);margin-top:6px">
                        Berlaku untuk Riwayat Transaksi, Laporan Online, Rekap Metode Bayar, dan Laporan Delivery Order.
                        Role yang tidak dicentang bebas memilih rentang tanggal.
                    </p>
                </div>

                <div class="form-group">
                    <label class="form-label">Layout Kasir</label>
                    <input type="hidden" name="pos_layout" id="posLayoutInput" value="{{ $settings['pos_layout'] ?: 'index' }}">
                    <div style="display:flex;gap:12px;margin-top:4px">
                        <div class="pos-layout-opt" data-value="index" onclick="selectPosLayout(this)"
                            style="flex:1;cursor:pointer;border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                            <div style="font-size:28px;margin-bottom:6px">🖼️</div>
                            <div style="font-size:13px;font-weight:600">Kasir Klasik</div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px">Grid produk + gambar</div>
                        </div>
                        <div class="pos-layout-opt" data-value="quick" onclick="selectPosLayout(this)"
                            style="flex:1;cursor:pointer;border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                            <div style="font-size:28px;margin-bottom:6px">⚡</div>
                            <div style="font-size:13px;font-weight:600">Kasir Cepat</div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px">Cari + tabel pesanan</div>
                        </div>
                        <div class="pos-layout-opt" data-value="express" onclick="selectPosLayout(this)"
                            style="flex:1;cursor:pointer;border:2px solid var(--border);border-radius:10px;padding:14px;text-align:center;transition:all .2s">
                            <div style="font-size:28px;margin-bottom:6px">💰</div>
                            <div style="font-size:13px;font-weight:600">Kasir Express</div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px">Total besar, tanpa tipe pesanan</div>
                        </div>
                    </div>
                    <p style="font-size:12px;color:var(--text3);margin-top:6px">Menentukan tampilan yang dibuka saat kasir klik menu Kasir</p>
                </div>

                {{-- Filter Item Group per layar --}}
                <div style="background:var(--surface2);border-radius:12px;padding:16px;margin-bottom:16px">
                    <div style="font-weight:700;font-size:13px;color:var(--text2);margin-bottom:4px;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-filter" style="color:var(--blue)"></i>
                        Filter Item Group
                    </div>
                    <p style="font-size:11px;color:var(--text3);margin-bottom:12px">Pilih Item Group yang ditampilkan di tiap layar. Kosongkan = tampilkan semua.</p>

                    @php
                        $igScreens = [
                            'pos_item_groups'           => ['Kasir', 'fa-cash-register'],
                            'delivery_item_groups'      => ['Delivery Order', 'fa-truck'],
                            'stock_request_item_groups' => ['Permintaan FG', 'fa-clipboard-check'],
                            'slice_item_groups'         => ['Repack (Konversi)', 'fa-scissors'],
                        ];
                    @endphp

                    @foreach($igScreens as $igKey => $igMeta)
                        @php $igSelected = $settings[$igKey] ?? []; @endphp
                        <div style="margin-bottom:14px">
                            <input type="hidden" name="{{ $igKey }}" id="ig_{{ $igKey }}" value="{{ implode(',', $igSelected) }}">
                            <div style="font-weight:700;font-size:12px;margin-bottom:6px;display:flex;align-items:center;gap:6px">
                                <i class="fas {{ $igMeta[1] }}" style="color:var(--text3)"></i> {{ $igMeta[0] }}
                                <span style="font-size:11px;font-weight:500;color:var(--text3)" id="igcount_{{ $igKey }}"></span>
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px">
                                @forelse($itemGroups as $g)
                                    <label class="ig-chip{{ in_array($g->id, $igSelected) ? ' on' : '' }}" data-key="{{ $igKey }}" data-id="{{ $g->id }}">
                                        <input type="checkbox" style="display:none" {{ in_array($g->id, $igSelected) ? 'checked' : '' }}
                                            onchange="toggleItemGroup('{{ $igKey }}')">
                                        {{ $g->name }}
                                    </label>
                                @empty
                                    <span style="font-size:12px;color:var(--text3)">Belum ada Item Group. Sync produk dari ERP HPY dulu.</span>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Service Charge & PB1 --}}
                <div style="background:var(--surface2);border-radius:12px;padding:16px;margin-bottom:16px">
                    <div style="font-weight:700;font-size:13px;color:var(--text2);margin-bottom:12px;display:flex;align-items:center;gap:6px">
                        <i class="fas fa-receipt" style="color:var(--blue)"></i>
                        Biaya Tambahan Dine In
                        <span style="font-size:11px;font-weight:500;color:var(--text3)">(berlaku hanya untuk pesanan Dine In)</span>
                    </div>

                    {{-- Service Charge --}}
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface);border-radius:10px;margin-bottom:10px;border:1px solid var(--border)">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1">
                            <input type="checkbox" name="service_charge_enabled" value="1" id="scCheck"
                                {{ ($settings['service_charge_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                style="width:18px;height:18px;accent-color:var(--blue);cursor:pointer"
                                onchange="toggleChargeInput('scInput', this.checked)">
                            <div>
                                <div style="font-weight:700;font-size:13px">Service Charge</div>
                                <div style="font-size:11px;color:var(--text3)">Biaya pelayanan ditambahkan ke tagihan Dine In</div>
                            </div>
                        </label>
                        <div style="display:flex;align-items:center;gap:6px" id="scInput">
                            <input type="number" name="service_charge_pct" id="scPct"
                                value="{{ $settings['service_charge_pct'] ?? '0' }}"
                                min="0" max="100" step="0.1"
                                style="width:80px;padding:8px 10px;border:2px solid var(--border);border-radius:8px;font-size:15px;font-weight:800;text-align:right;font-family:'Roboto Mono',monospace;{{ ($settings['service_charge_enabled'] ?? '0') !== '1' ? 'opacity:.4;pointer-events:none' : '' }}"
                                placeholder="10">
                            <span style="font-weight:700;color:var(--text2);font-size:15px">%</span>
                        </div>
                    </div>

                    {{-- PB1 --}}
                    <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface);border-radius:10px;border:1px solid var(--border)">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;flex:1">
                            <input type="checkbox" name="pb1_enabled" value="1" id="pb1Check"
                                {{ ($settings['pb1_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                style="width:18px;height:18px;accent-color:var(--blue);cursor:pointer"
                                onchange="toggleChargeInput('pb1Input', this.checked)">
                            <div>
                                <div style="font-weight:700;font-size:13px">PB1 — Pajak Daerah</div>
                                <div style="font-size:11px;color:var(--text3)">Pajak Bangunan 1 / Pajak Restoran daerah (dikenakan pada Dine In)</div>
                            </div>
                        </label>
                        <div style="display:flex;align-items:center;gap:6px" id="pb1Input">
                            <input type="number" name="pb1_pct" id="pb1Pct"
                                value="{{ $settings['pb1_pct'] ?? '0' }}"
                                min="0" max="100" step="0.1"
                                style="width:80px;padding:8px 10px;border:2px solid var(--border);border-radius:8px;font-size:15px;font-weight:800;text-align:right;font-family:'Roboto Mono',monospace;{{ ($settings['pb1_enabled'] ?? '0') !== '1' ? 'opacity:.4;pointer-events:none' : '' }}"
                                placeholder="10">
                            <span style="font-weight:700;color:var(--text2);font-size:15px">%</span>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary" id="saveBtn" onclick="saveSettings()">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
            </form>
        </div>
    </div>

    {{-- Kolom Kanan: Logo + Preview --}}
    <div style="display:flex;flex-direction:column;gap:20px">

        {{-- Preview Struk --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-receipt text-blue" style="margin-right:6px;"></i>Preview Struk</span>
                <span style="font-size:12px;color:var(--text3);">Tampilan thermal printer</span>
            </div>
            <div class="card-body" style="display:flex;justify-content:center;">
                <div id="receiptPreview" style="font-family:'Courier New',monospace;font-size:12px;width:260px;border:1px dashed var(--border);padding:14px;background:#fff;border-radius:6px;line-height:1.6;">
                    {{-- diisi oleh JS --}}
                </div>
            </div>
        </div>

        {{-- Logo Struk --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-image text-blue" style="margin-right:6px;"></i>Logo Struk</span>
            </div>
            <div class="card-body">
                <div id="logoPreviewWrap" style="margin-bottom:12px">
                    @if($settings['store_logo'])
                    <div style="display:flex;align-items:center;gap:12px">
                        <img id="logoImg" src="{{ asset($settings['store_logo']) }}"
                            style="height:64px;max-width:160px;object-fit:contain;border:1px solid var(--border);border-radius:8px;padding:4px;background:#fff">
                        <button type="button" onclick="removeLogo()" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i> Hapus Logo
                        </button>
                    </div>
                    @else
                    <div id="noLogoMsg" style="color:var(--text3);font-size:13px;display:flex;align-items:center;gap:8px">
                        <i class="fas fa-image" style="font-size:32px;color:var(--border)"></i>
                        Belum ada logo
                    </div>
                    @endif
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <label for="logoFile" class="btn btn-outline btn-sm" style="cursor:pointer;margin:0">
                        <i class="fas fa-upload"></i> Pilih Gambar
                    </label>
                    <input type="file" id="logoFile" accept="image/jpeg,image/png,image/gif,image/webp"
                        style="display:none" onchange="uploadLogo(this)">
                    <span id="logoUploadStatus" class="text-sm text-muted"></span>
                </div>
                <p style="font-size:11px;color:var(--text3);margin-top:8px">
                    Format: JPG, PNG, GIF, WebP &mdash; Maks. 2 MB.
                    Akan tampil di bagian atas struk digital dan cetak.
                </p>
            </div>
        </div>
    </div>{{-- end kolom kanan --}}
</div>
@endsection

@push('scripts')
<script>
function generateMobileApiCreds() {
    const rand = (len) => {
        const bytes = new Uint8Array(len);
        crypto.getRandomValues(bytes);
        return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
    };
    document.getElementById('mobileApiKey').value    = rand(12);  // 24 hex chars
    document.getElementById('mobileApiSecret').value = rand(20);  // 40 hex chars
}
</script>
<script>
function toggleChargeInput(wrapperId, enabled) {
    const input = document.querySelector('#' + wrapperId + ' input[type="number"]');
    if (!input) return;
    input.style.opacity        = enabled ? '1' : '.4';
    input.style.pointerEvents  = enabled ? '' : 'none';
}

// Radio button visual untuk tampilan produk
function updateProdDisplayUI() {
    document.querySelectorAll('.prod-display-radio').forEach(radio => {
        const box = radio.nextElementSibling;
        box.style.borderColor    = radio.checked ? 'var(--blue)' : 'var(--border)';
        box.style.background     = radio.checked ? 'var(--blue-light, #E8F0FE)' : '';
        box.style.color          = radio.checked ? 'var(--blue)' : '';
    });
}
document.querySelectorAll('.prod-display-radio').forEach(r => {
    r.addEventListener('change', updateProdDisplayUI);
});

// Radio button visual untuk cakupan laporan transaksi
function updateReportScopeUI() {
    document.querySelectorAll('.report-scope-radio').forEach(radio => {
        const box = radio.nextElementSibling;
        box.style.borderColor = radio.checked ? 'var(--blue)' : 'var(--border)';
        box.style.background  = radio.checked ? 'var(--blue-light, #E8F0FE)' : '';
        box.style.color       = radio.checked ? 'var(--blue)' : '';
    });
}
document.querySelectorAll('.report-scope-radio').forEach(r => {
    r.addEventListener('change', updateReportScopeUI);
});
updateReportScopeUI();

// Layout Kasir selector
function selectPosLayout(el) {
    document.getElementById('posLayoutInput').value = el.dataset.value;
    document.querySelectorAll('.pos-layout-opt').forEach(o => {
        const active = o === el;
        o.style.borderColor = active ? 'var(--blue)' : 'var(--border)';
        o.style.background  = active ? 'var(--blue-light, #E8F0FE)' : '';
    });
}
(function initPosLayout() {
    const cur = document.getElementById('posLayoutInput').value;
    document.querySelectorAll('.pos-layout-opt').forEach(o => {
        if (o.dataset.value === cur) selectPosLayout(o);
    });
})();

function val(name) {
    return document.querySelector(`[name="${name}"]`)?.value?.trim() ?? '';
}

let currentLogoUrl = '{{ $settings['store_logo'] ? asset($settings['store_logo']) : '' }}';

async function uploadLogo(input) {
    if (!input.files[0]) return;
    const status = document.getElementById('logoUploadStatus');
    status.textContent = 'Mengunggah...';

    const fd = new FormData();
    fd.append('logo', input.files[0]);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    try {
        const res  = await fetch('{{ route("settings.logo.upload") }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: fd,
        });
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response from settings.logo.upload:', text.substring(0, 300));
            throw new Error('Server error (HTTP ' + res.status + '). Cek Laravel log.');
        }

        if (!json.success) {
            const msg = json.errors?.logo?.[0] || json.message || 'Gagal';
            throw new Error(msg);
        }

        currentLogoUrl = json.url;
        document.getElementById('logoPreviewWrap').innerHTML = `
            <div style="display:flex;align-items:center;gap:12px">
                <img id="logoImg" src="${json.url}"
                    style="height:64px;max-width:160px;object-fit:contain;border:1px solid var(--border);border-radius:8px;padding:4px;background:#fff">
                <button type="button" onclick="removeLogo()" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i> Hapus Logo
                </button>
            </div>`;
        status.textContent = '';
        updatePreview();
        toast('Logo berhasil disimpan!', 'success');
    } catch (e) {
        status.textContent = 'Gagal: ' + e.message;
    }
    input.value = '';
}

async function removeLogo() {
    if (!confirm('Hapus logo struk?')) return;
    const status = document.getElementById('logoUploadStatus');

    try {
        const res  = await fetch('{{ route("settings.logo.remove") }}', {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const text = await res.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response from settings.logo.remove:', text.substring(0, 300));
            throw new Error('Server error (HTTP ' + res.status + '). Cek Laravel log.');
        }
        if (!json.success) throw new Error('Gagal');

        currentLogoUrl = '';
        document.getElementById('logoPreviewWrap').innerHTML = `
            <div id="noLogoMsg" style="color:var(--text3);font-size:13px;display:flex;align-items:center;gap:8px">
                <i class="fas fa-image" style="font-size:32px;color:var(--border)"></i>
                Belum ada logo
            </div>`;
        status.textContent = '';
        updatePreview();
        toast('Logo dihapus', 'success');
    } catch (e) {
        toast('Gagal menghapus logo', 'error');
    }
}

function updatePreview() {
    const name    = val('store_name')     || '';
    const tagline = val('store_tagline')  || 'Point of Sale System';
    const address = val('store_address');
    const phone   = val('store_phone');
    const email   = val('store_email');
    const footer  = val('receipt_footer') || 'Terima kasih atas kunjungan Anda!';

    const divider = `<div style="border-top:1px dashed #000;margin:5px 0;"></div>`;

    let html = '';
    if (currentLogoUrl) {
        html += `<div style="text-align:center;margin-bottom:6px"><img src="${currentLogoUrl}" style="max-height:50px;max-width:200px;object-fit:contain"></div>`;
    }
    html += `<div style="text-align:center;font-weight:bold;font-size:15px;">${name}</div>`;
    if (tagline) html += `<div style="text-align:center;font-size:10px;">${tagline}</div>`;
    if (address) html += `<div style="text-align:center;font-size:10px;">${address.replace(/\n/g,'<br>')}</div>`;
    if (phone)   html += `<div style="text-align:center;font-size:10px;">Telp: ${phone}</div>`;
    if (email)   html += `<div style="text-align:center;font-size:10px;">${email}</div>`;

    html += divider;
    html += `<div style="display:flex;justify-content:space-between;"><span>Invoice</span><span>INV-YYYYMMDD-0001</span></div>`;
    html += `<div style="display:flex;justify-content:space-between;"><span>Tanggal</span><span>${new Date().toLocaleDateString('id-ID')}</span></div>`;
    html += `<div style="display:flex;justify-content:space-between;"><span>Kasir</span><span>Admin</span></div>`;
    html += divider;
    html += `<div>Produk Contoh</div><div style="display:flex;justify-content:space-between;padding-left:8px;"><span>1 x Rp 50.000</span><span>Rp 50.000</span></div>`;
    html += divider;
    const scEnabled  = document.getElementById('scCheck')?.checked;
    const scPct      = parseFloat(document.getElementById('scPct')?.value || 0);
    const pb1Enabled = document.getElementById('pb1Check')?.checked;
    const pb1Pct     = parseFloat(document.getElementById('pb1Pct')?.value || 0);
    const scAmt      = scEnabled  ? Math.round(50000 * scPct / 100)  : 0;
    const pb1Amt     = pb1Enabled ? Math.round((50000 + scAmt) * pb1Pct / 100) : 0;
    const grandTotal = 50000 + scAmt + pb1Amt;

    html += `<div style="display:flex;justify-content:space-between;"><span>Subtotal</span><span>Rp 50.000</span></div>`;
    if (scEnabled && scAmt > 0) html += `<div style="display:flex;justify-content:space-between;"><span>Service Charge (${scPct}%)</span><span>Rp ${scAmt.toLocaleString('id-ID')}</span></div>`;
    if (pb1Enabled && pb1Amt > 0) html += `<div style="display:flex;justify-content:space-between;"><span>PB1 (${pb1Pct}%)</span><span>Rp ${pb1Amt.toLocaleString('id-ID')}</span></div>`;
    html += divider;
    html += `<div style="display:flex;justify-content:space-between;font-weight:bold;font-size:14px;"><span>TOTAL</span><span>Rp ${grandTotal.toLocaleString('id-ID')}</span></div>`;
    html += `<div style="display:flex;justify-content:space-between;"><span>Bayar (CASH)</span><span>Rp 100.000</span></div>`;
    html += `<div style="display:flex;justify-content:space-between;"><span>Kembalian</span><span>Rp 50.000</span></div>`;
    html += divider;
    if (footer) html += `<div style="text-align:center;margin-top:6px;">${footer}</div>`;
    html += `<div style="text-align:center;font-size:10px;color:#aaa;margin-top:4px;">Powered by HPY Solution</div>`;

    document.getElementById('receiptPreview').innerHTML = html;
}

async function saveSettings() {
    const btn    = document.getElementById('saveBtn');
    const status = document.getElementById('saveStatus');
    const form   = document.getElementById('storeSettingsForm');
    const data   = {};

    new FormData(form).forEach((v, k) => { if (!k.endsWith('[]')) data[k] = v; });

    // Checkbox multi-nilai: kirim sebagai array (kosong = tidak ada role terkunci)
    data['report_date_limit_roles'] = [...form.querySelectorAll('input[name="report_date_limit_roles[]"]:checked')]
        .map(c => c.value);

    btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
    btn.disabled  = true;
    status.style.display = 'none';

    try {
        const res = await api.post('{{ route("settings.save") }}', data);

        if (res.success) {
            status.className     = 'badge badge-green';
            status.textContent   = '✓ Tersimpan';
            status.style.display = '';
            toast('Pengaturan berhasil disimpan!', 'success');
            setTimeout(() => status.style.display = 'none', 3000);
        } else {
            toast('Gagal menyimpan: ' + (res.message || 'Error'), 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }

    btn.innerHTML = '<i class="fas fa-save"></i> Simpan Pengaturan';
    btn.disabled  = false;
}

// Wire charge inputs to preview
['scCheck','pb1Check','scPct','pb1Pct'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', updatePreview);
    if (el) el.addEventListener('input', updatePreview);
});

// Item Group filter chips → sync hidden CSV input + visual state + count
function toggleItemGroup(key) {
    const chips = document.querySelectorAll('.ig-chip[data-key="' + key + '"]');
    const ids = [];
    chips.forEach(chip => {
        const on = chip.querySelector('input').checked;
        chip.classList.toggle('on', on);
        if (on) ids.push(chip.dataset.id);
    });
    document.getElementById('ig_' + key).value = ids.join(',');
    document.getElementById('igcount_' + key).textContent = ids.length ? '(' + ids.length + ' dipilih)' : '(semua tampil)';
}
['pos_item_groups', 'delivery_item_groups', 'stock_request_item_groups', 'slice_item_groups'].forEach(toggleItemGroup);

// Init preview on load
updatePreview();
updateProdDisplayUI();
</script>
@endpush
