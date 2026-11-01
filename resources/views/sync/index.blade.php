@extends('layouts.app')
@section('title', 'Sinkronisasi HPY')

@section('content')
<div class="page-header">
    <div>
        <div class="page-title"><i class="fas fa-sync-alt text-blue"></i> Sinkronisasi ERP HPY</div>
        <div class="page-subtitle">Kelola koneksi dan sinkronisasi data ke HPY</div>
    </div>
</div>

{{-- Info bar koneksi aktif --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:8px;">
        @if($settings['erpnext_url'])
            <span style="width:8px;height:8px;border-radius:50%;background:var(--green);display:inline-block;"></span>
            <span style="font-size:13px;font-weight:600;color:var(--text2);">URL:</span>
            <span style="font-size:13px;color:var(--blue);">{{ $settings['erpnext_url'] }}</span>
        @else
            <span style="width:8px;height:8px;border-radius:50%;background:var(--text3);display:inline-block;"></span>
            <span style="font-size:13px;color:var(--text3);">Belum dikonfigurasi</span>
        @endif
    </div>
    @if($settings['erpnext_company'])
    <div style="display:flex;align-items:center;gap:6px;">
        <i class="fas fa-building" style="font-size:12px;color:var(--text3);"></i>
        <span style="font-size:13px;font-weight:600;color:var(--text2);">Company:</span>
        <span style="font-size:13px;color:var(--text);">{{ $settings['erpnext_company'] }}</span>
    </div>
    @endif
    @if($settings['erpnext_pos_profile'])
    <div style="display:flex;align-items:center;gap:6px;">
        <i class="fas fa-cash-register" style="font-size:12px;color:var(--text3);"></i>
        <span style="font-size:13px;font-weight:600;color:var(--text2);">POS Profile:</span>
        <span class="badge badge-blue" style="font-size:12px;">{{ $settings['erpnext_pos_profile'] }}</span>
    </div>
    @else
    <div style="display:flex;align-items:center;gap:6px;">
        <i class="fas fa-cash-register" style="font-size:12px;color:var(--text3);"></i>
        <span style="font-size:13px;color:var(--text3);font-style:italic;">POS Profile belum diset</span>
    </div>
    @endif
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="stat-card">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div>
            <div class="stat-value" style="color:#E37400">{{ $stats['pending'] }}</div>
            <div class="stat-label">Menunggu Sync</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div>
            <div class="stat-value text-green">{{ $stats['synced'] }}</div>
            <div class="stat-label">Berhasil Disync</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div>
            <div class="stat-value text-red">{{ $stats['failed'] }}</div>
            <div class="stat-label">Gagal Sync</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
    <!-- Konfigurasi HPY -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-plug text-blue"></i> Konfigurasi HPY</div>
            <div id="connStatus" class="badge badge-gray"><i class="fas fa-circle" style="font-size:8px"></i> Belum dites</div>
        </div>
        <div class="card-body">
            <form id="settingsForm">
                <div class="form-group">
                    <label class="form-label">HPY URL</label>
                    <input type="url" name="erpnext_url" class="form-control" placeholder="http://your.hpy.co.id" value="{{ $settings['erpnext_url'] ?? '' }}">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="text" name="erpnext_api_key" class="form-control" value="{{ $settings['erpnext_api_key'] ?? '' }}" placeholder="API Key">
                    </div>
                    <div class="form-group">
                        <label class="form-label">API Secret</label>
                        <input type="password" name="erpnext_api_secret" class="form-control" value="{{ $settings['erpnext_api_secret'] ?? '' }}" placeholder="API Secret">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <input type="text" name="erpnext_company" class="form-control" value="{{ $settings['erpnext_company'] ?? '' }}" placeholder="Nama perusahaan">
                    </div>
                    <div class="form-group">
                        <label class="form-label">POS Profile</label>
                        <input type="text" name="erpnext_pos_profile" class="form-control" value="{{ $settings['erpnext_pos_profile'] ?? '' }}" placeholder="POS Profile name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Naming Series</label>
                        <input type="text" name="erpnext_naming_series" class="form-control" value="{{ $settings['erpnext_naming_series'] ?? 'ACC-PSINV-.YYYY.-' }}" placeholder="ACC-PSINV-.YYYY.-">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Walk-in Customer</label>
                        <input type="text" name="erpnext_walkin_customer" class="form-control" value="{{ $settings['erpnext_walkin_customer'] ?? '' }}" placeholder="Walk-in Customer">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price List</label>
                        <input type="text" name="erpnext_price_list" class="form-control" value="{{ $settings['erpnext_price_list'] ?? '' }}" placeholder="Standard Selling">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Currency</label>
                        <input type="text" name="erpnext_currency" class="form-control" value="{{ $settings['erpnext_currency'] ?? 'IDR' }}" placeholder="IDR">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Naming Series SO</label>
                        <input type="text" name="erp_so_naming_series" class="form-control" value="{{ $settings['erp_so_naming_series'] ?? 'SAL-ORD-.YYYY.-' }}" placeholder="SAL-ORD-.YYYY.-">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Naming Series DN</label>
                        <input type="text" name="erp_dn_naming_series" class="form-control" value="{{ $settings['erp_dn_naming_series'] ?? 'MAT-DN-.YYYY.-' }}" placeholder="MAT-DN-.YYYY.-">
                    </div>
                </div>

                {{-- Delivery Platform Price Lists --}}
                <div style="background:var(--surface2);border-radius:10px;padding:12px;margin-bottom:16px">
                    <div style="font-weight:700;font-size:12px;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                        <span style="font-size:15px">🛵</span> Price List Delivery Platform
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                        <div class="form-group" style="margin:0">
                            <label class="form-label" style="display:flex;align-items:center;gap:5px">
                                <span style="width:9px;height:9px;border-radius:50%;background:#00AE11;display:inline-block"></span> GoFood
                            </label>
                            <input type="text" name="delivery_pricelist_gofood" class="form-control" value="{{ $settings['delivery_pricelist_gofood'] ?? '' }}" placeholder="Price list GoFood">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label class="form-label" style="display:flex;align-items:center;gap:5px">
                                <span style="width:9px;height:9px;border-radius:50%;background:#00B14F;display:inline-block"></span> GrabFood
                            </label>
                            <input type="text" name="delivery_pricelist_grabfood" class="form-control" value="{{ $settings['delivery_pricelist_grabfood'] ?? '' }}" placeholder="Price list GrabFood">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label class="form-label" style="display:flex;align-items:center;gap:5px">
                                <span style="width:9px;height:9px;border-radius:50%;background:#EE4D2D;display:inline-block"></span> ShopeeFood
                            </label>
                            <input type="text" name="delivery_pricelist_shopeefood" class="form-control" value="{{ $settings['delivery_pricelist_shopeefood'] ?? '' }}" placeholder="Price list ShopeeFood">
                        </div>
                    </div>
                </div>

                {{-- Auto Sync Toggle --}}
                <div style="background:var(--surface2);border-radius:10px;padding:14px;margin-bottom:16px;border:2px solid {{ $settings['erp_auto_sync'] === '1' ? 'var(--green)' : 'var(--border)' }}" id="autoSyncBox">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-weight:700;font-size:14px;display:flex;align-items:center;gap:6px">
                                <i class="fas fa-bolt" style="color:{{ $settings['erp_auto_sync'] === '1' ? 'var(--green)' : 'var(--text3)' }}" id="autoSyncIcon"></i>
                                Auto Sync ke ERP HPY
                            </div>
                            <div style="font-size:12px;color:var(--text3);margin-top:2px">
                                Setiap transaksi checkout langsung dikirim ke ERP HPY secara otomatis
                            </div>
                        </div>
                        <label class="toggle-switch" style="flex-shrink:0;margin-left:16px">
                            <input type="checkbox" id="autoSyncToggle" name="erp_auto_sync" value="1"
                                {{ $settings['erp_auto_sync'] === '1' ? 'checked' : '' }}
                                onchange="onAutoSyncChange(this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div id="autoSyncNote" style="margin-top:8px;font-size:12px;{{ $settings['erp_auto_sync'] !== '1' ? '' : 'display:none' }};color:#E37400">
                        <i class="fas fa-info-circle"></i>
                        Auto Sync nonaktif — transaksi akan tersimpan sebagai <strong>pending</strong> dan bisa di-sync manual.
                    </div>
                </div>

                {{-- Auto Sync Stok Toggle --}}
                <div style="background:var(--surface2);border-radius:10px;padding:14px;margin-bottom:16px;border:2px solid {{ ($settings['stock_auto_sync'] ?? '0') === '1' ? 'var(--green)' : 'var(--border)' }}" id="stockAutoSyncBox">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <div style="font-weight:700;font-size:14px;display:flex;align-items:center;gap:6px">
                                <i class="fas fa-boxes" style="color:{{ ($settings['stock_auto_sync'] ?? '0') === '1' ? 'var(--green)' : 'var(--text3)' }}" id="stockAutoSyncIcon"></i>
                                Auto Sync Stok dari ERP HPY
                            </div>
                            <div style="font-size:12px;color:var(--text3);margin-top:2px">
                                Tarik level stok (qty) dari ERP HPY otomatis tiap jam untuk semua warehouse aktif
                            </div>
                        </div>
                        <label class="toggle-switch" style="flex-shrink:0;margin-left:16px">
                            <input type="checkbox" id="stockAutoSyncToggle" name="stock_auto_sync" value="1"
                                {{ ($settings['stock_auto_sync'] ?? '0') === '1' ? 'checked' : '' }}
                                onchange="onStockAutoSyncChange(this)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div id="stockAutoSyncNote" style="margin-top:8px;font-size:12px;{{ ($settings['stock_auto_sync'] ?? '0') !== '1' ? '' : 'display:none' }};color:#E37400">
                        <i class="fas fa-info-circle"></i>
                        Auto Sync Stok nonaktif — stok hanya ter-update saat <strong>sync manual</strong> di menu Stok.
                    </div>
                    <div style="margin-top:8px;font-size:11px;color:var(--text3)">
                        <i class="fas fa-clock"></i>
                        Butuh Laravel scheduler aktif (Task Scheduler menjalankan <code>php artisan schedule:run</code> tiap menit).
                    </div>
                </div>

                <style>
                .toggle-switch { position:relative;display:inline-block;width:44px;height:24px; }
                .toggle-switch input { opacity:0;width:0;height:0; }
                .toggle-slider { position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s; }
                .toggle-slider:before { position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s; }
                .toggle-switch input:checked + .toggle-slider { background:var(--green); }
                .toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
                </style>

                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn-outline" onclick="testConnection()" id="testBtn">
                        <i class="fas fa-wifi"></i> Test
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveSettings()" id="saveBtn">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sales Taxes & Charges -->
    @php $chargeTypes = ['On Net Total','Actual','On Previous Row Total','On Previous Row Amount','On Item Qty']; @endphp
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fas fa-percentage text-blue"></i> Sales Taxes &amp; Charges</div>
        </div>
        <div class="card-body">
            {{-- Product Tax --}}
            <div style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:10px;background:var(--surface2)">
                <div style="font-size:12px;font-weight:800;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:5px">
                    <span style="width:8px;height:8px;border-radius:50%;background:#34A853;display:inline-block"></span>
                    Pajak Produk (Tax per Item)
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Account Head <span style="font-weight:400;color:var(--text3)">(Chart of Accounts)</span></label>
                    <input type="text" name="erpnext_tax_account" form="settingsForm" class="form-control"
                        value="{{ $settings['erpnext_tax_account'] ?? '' }}"
                        placeholder="Contoh: Tax Collected - HPY">
                    <p style="font-size:11px;color:var(--text3);margin-top:4px">Kosongkan jika pajak sudah diatur di POS Profile.</p>
                </div>
            </div>

            {{-- Service Charge --}}
            <div style="border:1px solid var(--border);border-radius:8px;padding:12px;margin-bottom:10px;background:var(--surface2)">
                <div style="font-size:12px;font-weight:800;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:5px">
                    <span style="width:8px;height:8px;border-radius:50%;background:#4285F4;display:inline-block"></span>
                    Service Charge
                </div>
                <div class="form-group" style="margin:0 0 8px">
                    <label class="form-label">Charge Type</label>
                    <select name="service_charge_erp_type" form="settingsForm" class="form-control">
                        @foreach($chargeTypes as $ct)
                        <option value="{{ $ct }}" {{ ($settings['service_charge_erp_type'] ?? 'On Net Total') === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Account Head</label>
                    <input type="text" name="service_charge_erp_account" form="settingsForm" class="form-control"
                        value="{{ $settings['service_charge_erp_account'] ?? '' }}"
                        placeholder="Contoh: Service Charge - HPY">
                </div>
            </div>

            {{-- PB1 --}}
            <div style="border:1px solid var(--border);border-radius:8px;padding:12px;background:var(--surface2)">
                <div style="font-size:12px;font-weight:800;color:var(--text2);margin-bottom:8px;display:flex;align-items:center;gap:5px">
                    <span style="width:8px;height:8px;border-radius:50%;background:#EA4335;display:inline-block"></span>
                    PB1 — Pajak Daerah
                </div>
                <div class="form-group" style="margin:0 0 8px">
                    <label class="form-label">Charge Type</label>
                    <select name="pb1_erp_type" form="settingsForm" class="form-control">
                        @foreach($chargeTypes as $ct)
                        <option value="{{ $ct }}" {{ ($settings['pb1_erp_type'] ?? 'On Net Total') === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">Account Head</label>
                    <input type="text" name="pb1_erp_account" form="settingsForm" class="form-control"
                        value="{{ $settings['pb1_erp_account'] ?? '' }}"
                        placeholder="Contoh: PB1 - HPY">
                </div>
                <p style="font-size:11px;color:var(--text3);margin-top:6px">Tanpa Service Charge → <em>On Net Total</em>. Ada Service Charge → <em>On Previous Row Total</em>.</p>
            </div>
        </div>
    </div>


</div>

<!-- Aksi Sinkronisasi -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-exchange-alt" style="color:#34A853"></i> Aksi Sinkronisasi</div>
    </div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:12px">
            <!-- Push Transactions -->
            <div style="background:var(--surface2);border-radius:10px;padding:16px">
                <div style="font-weight:700;font-size:14px;margin-bottom:4px"><i class="fas fa-arrow-up text-blue"></i> Push ke HPY</div>
                <div style="font-size:13px;color:var(--text3);margin-bottom:12px">Kirim transaksi pending ke HPY sebagai POS Invoice</div>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-primary" onclick="syncAll()" id="syncAllBtn" {{ $stats['pending'] == 0 ? 'disabled' : '' }}>
                        <i class="fas fa-sync-alt"></i> Sync {{ $stats['pending'] }} Pending
                    </button>
                    @if($stats['failed'] > 0)
                    <button class="btn btn-outline" onclick="retryFailed()" id="retryBtn" style="color:#E37400;border-color:#E37400">
                        <i class="fas fa-redo"></i> Retry {{ $stats['failed'] }} Gagal
                    </button>
                    @endif
                </div>
            </div>

            <!-- Pull Data -->
            <div style="background:var(--surface2);border-radius:10px;padding:16px">
                <div style="font-weight:700;font-size:14px;margin-bottom:4px"><i class="fas fa-arrow-down text-green"></i> Pull dari HPY</div>
                <div style="font-size:13px;color:var(--text3);margin-bottom:12px">Ambil data produk dan user kasir dari HPY ke lokal</div>
                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text2);margin-bottom:10px;cursor:pointer">
                    <input type="checkbox" id="resetProductsCheckbox">
                    Reset & sinkron ulang dari nol (nonaktifkan produk yang sudah tidak ada di ERP, bersihkan kategori kosong)
                </label>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button class="btn btn-success" onclick="pullProducts()" id="pullProductsBtn">
                        <i class="fas fa-box"></i> Pull Produk
                    </button>
                    <button class="btn btn-outline" onclick="pullPaymentMethods()" id="pullPaymentBtn" style="color:#34A853;border-color:#34A853">
                        <i class="fas fa-credit-card"></i> Pull Payment Methods
                    </button>
                    <button class="btn btn-outline" onclick="pullUsers()" id="pullUsersBtn" style="color:#4285F4;border-color:#4285F4">
                        <i class="fas fa-users"></i> Pull User dari POS Profile
                    </button>
                    <button class="btn btn-outline" onclick="pullDeliveryPrices()" id="pullDeliveryBtn" style="color:#E65100;border-color:#E65100">
                        <i class="fas fa-motorcycle"></i> Pull Harga Delivery
                    </button>
                </div>
                <p style="font-size:12px;color:var(--text3);margin-top:10px">
                    <i class="fas fa-info-circle"></i>
                    Pull User mengambil daftar kasir dari tab <strong>Applicable for Users</strong> pada POS Profile di ERP HPY.
                    User baru akan ditambahkan otomatis; user lama hanya diperbarui namanya.
                    Pull Harga Delivery mengambil Item Price dari price list GoFood / GrabFood / ShopeeFood yang sudah dikonfigurasi di atas.
                </p>
            </div>
        </div>

        <!-- Result box -->
        <div id="syncResult" style="display:none;background:var(--surface2);border-radius:10px;padding:14px;font-size:13px"></div>
    </div>
</div>

<!-- Failed Transactions -->
@if($failedTransactions->count() > 0)
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-exclamation-triangle" style="color:#EA4335"></i> Transaksi Gagal Sync</div>
        <button class="btn btn-outline btn-sm" style="color:#E37400;border-color:#E37400" onclick="retryFailed()">
            <i class="fas fa-redo"></i> Retry Semua
        </button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>Invoice</th><th>Kasir</th><th>Total</th><th>Error</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            @foreach($failedTransactions as $tx)
            <tr>
                <td class="font-medium text-blue">{{ $tx->invoice_no }}</td>
                <td>{{ $tx->user->name }}</td>
                <td class="money">Rp {{ number_format($tx->total, 0, ',', '.') }}</td>
                <td class="text-sm" style="color:#EA4335;max-width:300px">
                    <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px" title="{{ $tx->erp_sync_error }}">
                        {{ Str::limit($tx->erp_sync_error, 80) }}
                    </div>
                </td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick="syncSingle({{ $tx->id }}, this)">
                        <i class="fas fa-sync-alt"></i> Retry
                    </button>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Sync Logs -->
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-list-alt text-blue"></i> Log Sinkronisasi</div>
        <button class="btn btn-ghost btn-sm" onclick="loadLogs()"><i class="fas fa-refresh"></i> Refresh</button>
    </div>
    <div class="table-wrap">
        <table id="logsTable">
            <thead><tr>
                <th>Waktu</th><th>Tipe</th><th>Referensi</th><th>Status</th><th>HPY Doc</th><th>Keterangan</th>
            </tr></thead>
            <tbody>
            @foreach($recentLogs as $log)
            <tr>
                <td class="text-sm text-muted">{{ local_dt($log->created_at, 'd/m/Y H:i:s') }}</td>
                <td><span class="badge badge-blue">{{ strtoupper($log->type) }}</span></td>
                <td class="font-medium">{{ $log->reference_no ?? '#'.$log->reference_id }}</td>
                <td>
                    <span class="badge {{ $log->status === 'success' ? 'badge-green' : ($log->status === 'failed' ? 'badge-red' : 'badge-yellow') }}">
                        {{ strtoupper($log->status) }}
                    </span>
                </td>
                <td class="text-sm">{{ $log->erp_docname ?? '-' }}</td>
                <td class="text-sm" style="color:var(--text3);max-width:250px">
                    <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;max-width:230px">
                        {{ $log->error_message ? Str::limit($log->error_message, 60) : '✓ OK' }}
                    </span>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function testConnection() {
    const btn    = document.getElementById('testBtn');
    const status = document.getElementById('connStatus');
    btn.innerHTML = '<span class="spinner"></span> Mengetes...';
    btn.disabled  = true;

    // Ambil nilai dari form (belum tersimpan)
    const form      = document.getElementById('settingsForm');
    const formData  = new FormData(form);
    const url       = formData.get('erpnext_url')       || '';
    const apiKey    = formData.get('erpnext_api_key')    || '';
    const apiSecret = formData.get('erpnext_api_secret') || '';

    const resp = await fetch('{{ route("sync.test") }}', {
        method:  'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json'},
        body:    JSON.stringify({ url, api_key: apiKey, api_secret: apiSecret }),
    });
    const data = await resp.json();

    if (data.success) {
        if (data.mode === 'url_only') {
            status.className = 'badge badge-yellow';
            status.innerHTML = '<i class="fas fa-circle" style="font-size:8px"></i> URL terjangkau — belum verifikasi API Key';
            toast(data.message, 'warning');
        } else {
            status.className = 'badge badge-green';
            status.innerHTML = '<i class="fas fa-circle" style="font-size:8px"></i> Terhubung: ' + data.user;
            toast('Koneksi berhasil! Service account: ' + data.user, 'success');
        }
    } else {
        status.className = 'badge badge-red';
        status.innerHTML = '<i class="fas fa-circle" style="font-size:8px"></i> Gagal';
        toast('Koneksi gagal: ' + (data.error || 'Error'), 'error');
    }

    btn.innerHTML = '<i class="fas fa-wifi"></i> Test Koneksi';
    btn.disabled = false;
}

async function saveSettings() {
    const btn = document.getElementById('saveBtn');
    const form = document.getElementById('settingsForm');
    const data = {};
    new FormData(form).forEach((v, k) => data[k] = v);

    // Checkbox tidak dikirim FormData kalau tidak tercentang — kirim eksplisit
    data['erp_auto_sync'] = document.getElementById('autoSyncToggle').checked ? '1' : '0';
    data['stock_auto_sync'] = document.getElementById('stockAutoSyncToggle').checked ? '1' : '0';

    btn.innerHTML = '<span class="spinner"></span> Menyimpan...';
    btn.disabled = true;

    const resp = await fetch('{{ route("sync.settings") }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
        body: JSON.stringify(data)
    });
    const result = await resp.json();
    toast(result.success ? 'Pengaturan berhasil disimpan!' : 'Gagal menyimpan', result.success ? 'success' : 'error');
    btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
    btn.disabled = false;
}

function onAutoSyncChange(el) {
    const box  = document.getElementById('autoSyncBox');
    const icon = document.getElementById('autoSyncIcon');
    const note = document.getElementById('autoSyncNote');
    if (el.checked) {
        box.style.borderColor  = 'var(--green)';
        icon.style.color       = 'var(--green)';
        note.style.display     = 'none';
    } else {
        box.style.borderColor  = 'var(--border)';
        icon.style.color       = 'var(--text3)';
        note.style.display     = '';
    }
}

function onStockAutoSyncChange(el) {
    const box  = document.getElementById('stockAutoSyncBox');
    const icon = document.getElementById('stockAutoSyncIcon');
    const note = document.getElementById('stockAutoSyncNote');
    if (el.checked) {
        box.style.borderColor  = 'var(--green)';
        icon.style.color       = 'var(--green)';
        note.style.display     = 'none';
    } else {
        box.style.borderColor  = 'var(--border)';
        icon.style.color       = 'var(--text3)';
        note.style.display     = '';
    }
}

async function syncAll() {
    const btn = document.getElementById('syncAllBtn');
    btn.innerHTML = '<span class="spinner"></span> Mensync...';
    btn.disabled = true;

    const resp = await fetch('{{ route("sync.all") }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
    });
    const data = await resp.json();

    // Sync lain sedang berjalan (tab/admin lain) — jangan tampilkan sebagai hasil.
    if (data.locked) {
        toast(data.message, 'error');
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Coba Lagi';
        btn.disabled = false;
        return;
    }

    showSyncResult(data);
    toast(`Sync selesai: ${data.success} berhasil, ${data.failed} gagal`);
    setTimeout(() => location.reload(), 1500);
}

async function retryFailed() {
    const resp = await fetch('{{ route("sync.retry") }}', {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
    });
    const data = await resp.json();
    if (data.success) {
        toast(`${data.reset} transaksi direset ke pending`, 'success');
        setTimeout(() => location.reload(), 1000);
    }
}

async function syncSingle(id, btn) {
    btn.innerHTML = '<span class="spinner"></span>';
    btn.disabled = true;
    const resp = await fetch(`{{ url('sync/transaction') }}/${id}`, {
        method:'POST',
        headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
    });
    const data = await resp.json();
    toast(data.success ? 'Berhasil disync: ' + data.docname : 'Gagal: ' + data.error, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 1000);
    else { btn.innerHTML = '<i class="fas fa-sync-alt"></i> Retry'; btn.disabled = false; }
}

async function pullPaymentMethods() {
    const btn = document.getElementById('pullPaymentBtn');
    btn.innerHTML = '<span class="spinner"></span> Menarik...';
    btn.disabled = true;
    try {
        const resp = await fetch('{{ route("sync.pull-payment-methods") }}', {
            method:'POST',
            headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
        });
        const data = await resp.json();
        if (data.success) {
            const names = data.methods.map(m => m.mode_of_payment).join(', ');
            const cust  = data.customer ? ` | Customer: ${data.customer}` : '';
            toast(`Payment methods disimpan: ${names}${cust}`, 'success');
        } else {
            toast('Gagal: ' + (data.error || 'Error'), 'error');
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
    }
    btn.innerHTML = '<i class="fas fa-credit-card"></i> Pull Payment Methods';
    btn.disabled = false;
}

async function pullProducts() {
    const btn    = document.getElementById('pullProductsBtn');
    const reset  = document.getElementById('resetProductsCheckbox').checked;

    if (reset && !confirm('Reset & sinkron ulang dari nol akan menonaktifkan produk lokal yang sudah tidak ada di ERP, dan menghapus kategori yang tidak dipakai produk manapun. Lanjutkan?')) {
        return;
    }

    btn.innerHTML = '<span class="spinner"></span> Menarik semua produk...';
    btn.disabled = true;

    // Tampilkan info bahwa ini mungkin butuh waktu untuk data besar
    const resultEl = document.getElementById('syncResult');
    resultEl.style.display = '';
    resultEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--blue)"></i> Menarik produk dari HPY, harap tunggu... (data besar mungkin butuh beberapa menit)';

    try {
        const resp = await fetch('{{ route("sync.pull-products") }}' + (reset ? '?reset=1' : ''), {
            method:'POST',
            headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}
        });
        const data = await resp.json();
        if (data.success) {
            showSyncResult({...data, label: 'Produk'});
            let msg = `Produk: ${data.imported} baru, ${data.updated} diupdate — total ${data.total} record`;
            if (data.disabled_deactivated) msg += `, ${data.disabled_deactivated} dinonaktifkan (disabled/template ERP)`;
            if (reset) msg += `, ${data.deactivated} dinonaktifkan, ${data.categories_pruned} kategori dibersihkan`;
            toast(msg, 'success');
        } else {
            resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Gagal: ${data.error}`;
            toast('Gagal pull produk: ' + (data.error||'Error'), 'error');
        }
    } catch(e) {
        resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Error: ${e.message}`;
        toast('Error: ' + e.message, 'error');
    }

    btn.innerHTML = '<i class="fas fa-box"></i> Pull Produk';
    btn.disabled = false;
}

async function pullUsers() {
    const btn = document.getElementById('pullUsersBtn');
    btn.innerHTML = '<span class="spinner"></span> Menarik user...';
    btn.disabled = true;

    const resultEl = document.getElementById('syncResult');
    resultEl.style.display = '';
    resultEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--blue)"></i> Mengambil user dari POS Profile...';

    try {
        const resp = await fetch('{{ route("sync.pull-users") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
        });
        const data = await resp.json();
        if (data.success) {
            resultEl.innerHTML = `<i class="fas fa-check-circle" style="color:var(--green)"></i>
                <strong>User:</strong> ${data.created} user baru ditambahkan, ${data.updated} user diperbarui
                <span style="color:var(--text3)">— total ${data.total} user dari POS Profile</span>`;
            toast(`${data.created} user baru, ${data.updated} diperbarui dari POS Profile`, 'success');
        } else {
            resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Gagal: ${data.error}`;
            toast('Gagal pull user: ' + (data.error || 'Error'), 'error');
        }
    } catch(e) {
        resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Error: ${e.message}`;
        toast('Error: ' + e.message, 'error');
    }

    btn.innerHTML = '<i class="fas fa-users"></i> Pull User dari POS Profile';
    btn.disabled = false;
}

async function pullDeliveryPrices() {
    const btn = document.getElementById('pullDeliveryBtn');
    btn.innerHTML = '<span class="spinner"></span> Menarik harga...';
    btn.disabled = true;

    const resultEl = document.getElementById('syncResult');
    resultEl.style.display = '';
    resultEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:var(--blue)"></i> Mengambil harga dari price list delivery platform...';

    try {
        const resp = await fetch('{{ route("sync.pull-delivery-prices") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
        });
        const data = await resp.json();

        if (data.success || Object.keys(data.results ?? {}).length > 0) {
            const lines = Object.entries(data.results ?? {}).map(([k, v]) =>
                `<strong>${k.charAt(0).toUpperCase()+k.slice(1)}</strong>: ${v} harga`
            ).join(' &nbsp;|&nbsp; ');
            const errLine = data.errors?.length
                ? `<div style="color:var(--red);margin-top:4px;font-size:12px"><i class="fas fa-exclamation-circle"></i> ${data.errors.join('; ')}</div>`
                : '';
            resultEl.innerHTML = `<i class="fas fa-check-circle" style="color:var(--green)"></i> Harga delivery berhasil diambil: ${lines}${errLine}`;
            toast('Harga delivery berhasil diperbarui', 'success');
        } else {
            const errMsg = data.errors?.join('; ') || 'Semua price list belum dikonfigurasi';
            resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Gagal: ${errMsg}`;
            toast('Gagal pull harga delivery: ' + errMsg, 'error');
        }
    } catch(e) {
        resultEl.innerHTML = `<i class="fas fa-exclamation-circle" style="color:var(--red)"></i> Error: ${e.message}`;
        toast('Error: ' + e.message, 'error');
    }

    btn.innerHTML = '<i class="fas fa-motorcycle"></i> Pull Harga Delivery';
    btn.disabled = false;
}

function showSyncResult(data) {
    const el = document.getElementById('syncResult');
    el.style.display = 'block';
    if (data.label) {
        el.innerHTML = `<i class="fas fa-check-circle" style="color:#34A853"></i> <strong>${data.label}:</strong> `
            + `<span style="color:var(--green);font-weight:600;">${data.imported ?? 0} baru</span>, `
            + `<span style="color:var(--blue);font-weight:600;">${data.updated ?? 0} diupdate</span> `
            + `— total <strong>${data.total ?? 0}</strong> record diproses`
            + (data.disabled_deactivated ? `, <span style="color:var(--red);font-weight:600;">${data.disabled_deactivated} dinonaktifkan (disabled/template ERP)</span>` : '')
            + (data.deactivated ? `, <span style="color:var(--red);font-weight:600;">${data.deactivated} dinonaktifkan</span>` : '')
            + (data.categories_pruned ? `, <span style="color:var(--text3);">${data.categories_pruned} kategori dibersihkan</span>` : '');
    } else {
        el.innerHTML = `<i class="fas fa-sync-alt" style="color:#4285F4"></i> <strong>Sync selesai:</strong> ${data.success} berhasil, ${data.failed} gagal dari ${data.total} transaksi`;
    }
}
</script>
@endpush
