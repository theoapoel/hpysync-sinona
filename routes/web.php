<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DeliveryOrderController;
use App\Http\Controllers\DeliveryOrderPaymentController;
use App\Http\Controllers\DeliveryOrderReportController;
use App\Http\Controllers\ErpSyncController;
use App\Http\Controllers\FactoryResetController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\ModeOfPaymentReportController;
use App\Http\Controllers\OnlineReportController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosShiftController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PullingOrderController;
use App\Http\Controllers\RekapOrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SliceController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\StockRequestController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StockTransferReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Models\Setting;
use App\Models\User;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// ─── Setup Awal (tidak perlu auth, tidak perlu setup selesai) ────────────────
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'store'])->name('setup.store');
Route::post('/setup/test-connection', [SetupController::class, 'testConnection'])->name('setup.test');

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::get('/login', fn () => view('auth.login'))->name('login')->middleware('guest');
Route::post('/login', function (Request $req) {
    $req->validate(['email' => 'required|email']);
    $mode = $req->input('mode', 'online');

    // ── MODE OFFLINE: verifikasi PIN lokal ──────────────────────
    if ($mode === 'pin') {
        $req->validate(['pin' => 'required|digits:6']);

        $user = User::where('email', $req->email)
            ->where('is_active', true)
            ->first();

        if (! $user || $user->pin !== $req->pin) {
            return back()
                ->withInput($req->only('email', 'mode'))
                ->withErrors(['pin' => 'Email atau PIN tidak valid.']);
        }

        Auth::login($user, false);
        $req->session()->regenerate();
        $landing = match ($user->role) {
            'cashier' => route('pos.index'),
            'dapur' => route('kitchen.index'),
            default => route('dashboard'),
        };

        return redirect()->intended($landing);
    }

    // ── MODE ONLINE: verifikasi password ke ERP HPY ─────────────
    $req->validate(['password' => 'required']);

    $erp = new ErpNextService;
    $result = $erp->loginUser($req->email, $req->password);

    if (! $result['success']) {
        if (! empty($result['erp_unavailable'])) {
            return back()
                ->withInput($req->only('email'))
                ->withErrors(['email' => 'Server ERP HPY tidak dapat dihubungi. Gunakan login offline dengan PIN.']);
        }

        return back()
            ->withInput($req->only('email'))
            ->withErrors(['email' => $result['error']]);
    }

    $user = User::where('email', $req->email)->first();

    if (! $user) {
        // Auto-create: ambil detail role dari ERP HPY jika API key sudah dikonfigurasi
        $role = 'cashier';
        $apiKey = Setting::get('erpnext_api_key');
        if ($apiKey) {
            $info = $erp->getUserInfo($req->email);
            if ($info['success']) {
                $roleNames = $info['roles'] ?? [];
                if (in_array('System Manager', $roleNames)) {
                    $role = 'admin';
                } elseif (in_array('POS Manager', $roleNames)) {
                    $role = 'manager';
                }
            }
        } elseif (User::count() === 0) {
            // Tidak ada user sama sekali → user pertama jadi admin (bootstrap)
            $role = 'admin';
        }

        $user = User::create([
            'name' => $result['full_name'],
            'email' => $req->email,
            'password' => Hash::make(Str::random(32)),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    if (! $user->is_active) {
        return back()
            ->withInput($req->only('email'))
            ->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
    }

    Auth::login($user, $req->boolean('remember'));
    $req->session()->regenerate();
    $landing = match ($user->role) {
        'cashier' => route('pos.index'),
        'dapur' => route('kitchen.index'),
        default => route('dashboard'),
    };

    return redirect()->intended($landing);
})->name('login.post');

Route::post('/logout', function (Request $req) {
    Auth::logout();
    $req->session()->invalidate();
    $req->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

// ─── Protected ───────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard')
        ->name('dashboard');

    // POS
    Route::prefix('pos')->name('pos.')->middleware('permission:pos')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('/quick', [PosController::class, 'quickIndex'])->name('quick');
        Route::get('/express', [PosController::class, 'expressIndex'])->name('express');
        Route::get('/kasir', [PosController::class, 'kasirRedirect'])->name('kasir');
        Route::get('/search-products', [PosController::class, 'searchProducts'])->name('search-products');
        Route::post('/validate-coupon', [PosController::class, 'validateCoupon'])->name('validate-coupon');
        Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::get('/receipt/{transaction}', [PosController::class, 'receipt'])->name('receipt');
        Route::get('/print/{transaction}', [PosController::class, 'printReceipt'])->name('print');
        Route::get('/print-kitchen/{transaction}', [PosController::class, 'printKitchen'])->name('print-kitchen');
        Route::post('/print-kitchen-draft', [PosController::class, 'printKitchenDraft'])->name('print-kitchen-draft');
        Route::get('/direct-print/{transaction}', [PosController::class, 'directPrint'])->name('direct-print');
    });

    // Shift Kasir (Buka/Tutup Kasir) — POS Opening/Closing Entry
    Route::prefix('pos-shift')->name('pos-shift.')->middleware('permission:pos')->group(function () {
        Route::get('/current', [PosShiftController::class, 'current'])->name('current');
        Route::post('/open', [PosShiftController::class, 'open'])->name('open');
        Route::get('/reconcile', [PosShiftController::class, 'reconcile'])->name('reconcile');
        Route::post('/close', [PosShiftController::class, 'close'])->name('close');
        Route::get('/{shift}/receipt', [PosShiftController::class, 'receipt'])->name('receipt');
    });

    // Transaksi
    Route::middleware('permission:transactions')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');

        // Wewenang membatalkan ditentukan di controller lewat role Accounts Manager di
        // ERP HPY, bukan role lokal. Route tetap di dalam grup permission:transactions
        // supaya endpointnya tidak terbuka bagi yang halaman transaksinya saja tak boleh.
        Route::get('/transactions/{transaction}/cancel-check', [TransactionController::class, 'cancelCheck'])->name('transactions.cancel-check');
        Route::post('/transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->name('transactions.cancel');
    });

    // Produk
    Route::middleware('permission:products')->group(function () {
        Route::resource('products', ProductController::class)->except(['show']);
    });

    // Customer
    Route::middleware('permission:customers')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::resource('customers', CustomerController::class)->except(['show', 'create', 'edit']);
    });

    // Stock
    Route::middleware('permission:stock')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::post('/stock/sync-bin', [StockController::class, 'syncFromBin'])->name('stock.sync-bin');
        Route::post('/stock/sync-warehouse/{warehouse}', [StockController::class, 'syncWarehouse'])->name('stock.sync-warehouse');
    });
    Route::get('/stock/debug-bin', [StockController::class, 'debugBinEndpoint'])->name('stock.debug-bin');
    Route::get('/stock/debug-sync', [StockController::class, 'debugSync'])->name('stock.debug-sync');

    // Stock Opname
    Route::prefix('stock-opname')->name('stock-opname.')->middleware('permission:stock_opname')->group(function () {
        Route::get('/', [StockOpnameController::class, 'index'])->name('index');
        Route::get('/create', [StockOpnameController::class, 'create'])->name('create');
        Route::post('/', [StockOpnameController::class, 'store'])->name('store');
        Route::get('/{stockOpname}', [StockOpnameController::class, 'show'])->name('show');
        Route::post('/{stockOpname}/items', [StockOpnameController::class, 'updateItems'])->name('update-items');
        Route::post('/{stockOpname}/submit', [StockOpnameController::class, 'submit'])->name('submit');
        Route::post('/{stockOpname}/cancel', [StockOpnameController::class, 'cancel'])->name('cancel');
    });

    // Stock Transfer
    Route::prefix('stock-transfer')->name('stock-transfer.')->middleware('permission:stock_transfer')->group(function () {
        Route::get('/', [StockTransferController::class, 'index'])->name('index');
        Route::get('/report', [StockTransferReportController::class, 'index'])->name('report');
        Route::get('/send', [StockTransferController::class, 'createSend'])->name('send.create');
        Route::post('/send', [StockTransferController::class, 'storeSend'])->name('send.store');
        Route::get('/receive', [StockTransferController::class, 'createReceive'])->name('receive.create');
        Route::post('/receive', [StockTransferController::class, 'storeReceive'])->name('receive.store');
        Route::post('/load-items', [StockTransferController::class, 'loadEntryItems'])->name('load-items');
        Route::get('/{stockTransfer}', [StockTransferController::class, 'show'])->name('show');
        Route::get('/{stockTransfer}/surat-jalan', [StockTransferController::class, 'suratJalan'])->name('surat-jalan');
        Route::post('/{stockTransfer}/retry', [StockTransferController::class, 'retry'])->name('retry');
    });

    // Delivery Order
    Route::prefix('delivery-orders')->name('delivery-orders.')->middleware('permission:delivery')->group(function () {
        Route::get('/', [DeliveryOrderController::class, 'index'])->name('index');
        Route::get('/create', [DeliveryOrderController::class, 'create'])->name('create');
        Route::post('/', [DeliveryOrderController::class, 'store'])->name('store');
        Route::get('/{deliveryOrder}', [DeliveryOrderController::class, 'show'])->name('show');
        Route::post('/{deliveryOrder}/confirm', [DeliveryOrderController::class, 'confirm'])->name('confirm');
        Route::post('/{deliveryOrder}/cancel', [DeliveryOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{deliveryOrder}/sync-so', [DeliveryOrderController::class, 'syncSalesOrder'])->name('sync-so');

        // Payment
        Route::post('/{deliveryOrder}/payments', [DeliveryOrderPaymentController::class, 'store'])->name('payments.store');
        Route::delete('/{deliveryOrder}/payments/{payment}', [DeliveryOrderPaymentController::class, 'destroy'])->name('payments.destroy');
        Route::post('/{deliveryOrder}/payments/{payment}/sync', [DeliveryOrderPaymentController::class, 'sync'])->name('payments.sync');

        // Jadwal Produksi
        Route::post('/{deliveryOrder}/schedule', [DeliveryOrderController::class, 'schedule'])->name('schedule');

        // Print slip (2 lembar: Gudang + QC)
        Route::get('/{deliveryOrder}/print-slip', [DeliveryOrderController::class, 'printSlip'])->name('print-slip');
        Route::get('/{deliveryOrder}/direct-print-slip', [DeliveryOrderController::class, 'directPrintSlip'])->name('direct-print-slip');

        // Print Proforma Invoice & Invoice (struk 58mm, browser + thermal langsung)
        Route::get('/{deliveryOrder}/proforma', [DeliveryOrderController::class, 'printInvoice'])->defaults('type', 'proforma')->name('proforma');
        Route::get('/{deliveryOrder}/invoice', [DeliveryOrderController::class, 'printInvoice'])->defaults('type', 'invoice')->name('invoice');
        Route::get('/{deliveryOrder}/direct-print-proforma', [DeliveryOrderController::class, 'directPrintInvoice'])->defaults('type', 'proforma')->name('direct-print-proforma');
        Route::get('/{deliveryOrder}/direct-print-invoice', [DeliveryOrderController::class, 'directPrintInvoice'])->defaults('type', 'invoice')->name('direct-print-invoice');
    });

    // Delivery Notes (Shipments)
    Route::prefix('delivery-notes')->name('delivery-notes.')->middleware('permission:delivery_notes')->group(function () {
        Route::get('/', [DeliveryNoteController::class, 'index'])->name('index');
        Route::post('/{shipment}/mark-delivered', [DeliveryNoteController::class, 'markDelivered'])->name('mark-delivered');
        Route::post('/{shipment}/sync-dn', [DeliveryNoteController::class, 'syncDeliveryNote'])->name('sync-dn');
    });

    // Permintaan Bahan
    Route::prefix('stock-requests')->name('stock-requests.')->middleware('permission:stock_request')->group(function () {
        Route::get('/', [StockRequestController::class, 'index'])->name('index');
        Route::get('/create', [StockRequestController::class, 'create'])->name('create');
        Route::post('/', [StockRequestController::class, 'store'])->name('store');
        Route::get('/{stockRequest}', [StockRequestController::class, 'show'])->name('show');
        Route::post('/{stockRequest}/submit', [StockRequestController::class, 'submit'])->name('submit');
        Route::post('/{stockRequest}/cancel', [StockRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{stockRequest}/sync-erp', [StockRequestController::class, 'syncErp'])->name('sync-erp');
        Route::post('/{stockRequest}/kitchen-status', [StockRequestController::class, 'updateKitchenStatus'])->name('kitchen-status');
    });

    // Slice — konversi qty item (Repack) ke ERP HPY
    Route::prefix('slices')->name('slices.')->middleware('permission:slice')->group(function () {
        Route::get('/', [SliceController::class, 'index'])->name('index');
        Route::get('/create', [SliceController::class, 'create'])->name('create');
        Route::post('/', [SliceController::class, 'store'])->name('store');
        Route::post('/refresh-status', [SliceController::class, 'refreshAllStatus'])->name('refresh-all');
        Route::get('/{slice}', [SliceController::class, 'show'])->name('show');
        Route::post('/{slice}/submit', [SliceController::class, 'submit'])->name('submit');
        Route::post('/{slice}/cancel', [SliceController::class, 'cancel'])->name('cancel');
        Route::post('/{slice}/sync-erp', [SliceController::class, 'syncErp'])->name('sync-erp');
        Route::post('/{slice}/refresh-status', [SliceController::class, 'refreshStatus'])->name('refresh-status');
    });

    // Rekap Order
    Route::prefix('rekap-order')->name('rekap-order.')->middleware('permission:rekap_order')->group(function () {
        Route::get('/', [RekapOrderController::class, 'index'])->name('index');
    });

    // Pulling Order — edit pembayaran & jadwal produksi lintas Delivery Order + Permintaan FG
    Route::prefix('pulling-order')->name('pulling-order.')->middleware('permission:pulling_order')->group(function () {
        Route::get('/', [PullingOrderController::class, 'index'])->name('index');
        Route::post('/delivery-orders/{deliveryOrder}/schedule', [PullingOrderController::class, 'scheduleDeliveryOrder'])->name('delivery.schedule');
        Route::post('/delivery-orders/{deliveryOrder}/confirm-kitchen', [PullingOrderController::class, 'confirmKitchen'])->name('delivery.confirm-kitchen');
        Route::post('/delivery-orders/{deliveryOrder}/payments', [PullingOrderController::class, 'storePayment'])->name('delivery.payment');
        Route::post('/stock-requests/{stockRequest}/schedule', [PullingOrderController::class, 'scheduleStockRequest'])->name('sr.schedule');
    });

    // Kitchen Monitor
    Route::prefix('kitchen')->name('kitchen.')->middleware('permission:kitchen')->group(function () {
        Route::get('/', [KitchenController::class, 'index'])->name('index');
        Route::get('/poll', [KitchenController::class, 'poll'])->name('poll');
        Route::get('/calendar', [KitchenController::class, 'calendar'])->name('calendar');
        Route::post('/{order}/status', [KitchenController::class, 'updateStatus'])->name('update-status');
    });

    // Laporan Online — data historis langsung dari ERPNext
    Route::prefix('online-report')->name('online-report.')->middleware('permission:online_report')->group(function () {
        Route::get('/', [OnlineReportController::class, 'index'])->name('index');
        Route::post('/fetch', [OnlineReportController::class, 'fetch'])->name('fetch');
        Route::get('/detail/{name}', [OnlineReportController::class, 'detail'])->name('detail')
            ->where('name', '.+');
    });

    // Laporan Mode of Payment — matriks tanggal × metode pembayaran dari ERPNext
    Route::prefix('mop-report')->name('mop-report.')->middleware('permission:mop_report')->group(function () {
        Route::get('/', [ModeOfPaymentReportController::class, 'index'])->name('index');
        Route::post('/fetch', [ModeOfPaymentReportController::class, 'fetch'])->name('fetch');
    });

    // Laporan DO — penjualan Delivery Order dari data lokal + verifikasi SI/DN ke HPY
    Route::prefix('do-report')->name('do-report.')->middleware('permission:do_report')->group(function () {
        Route::get('/', [DeliveryOrderReportController::class, 'index'])->name('index');
        Route::get('/{deliveryOrder}/check-erp', [DeliveryOrderReportController::class, 'checkErp'])->name('check-erp');
    });

    // Sync HPY — jalankan (permission:sync), konfigurasi (admin only)
    Route::prefix('sync')->name('sync.')->group(function () {
        Route::get('/ping', [ErpSyncController::class, 'pingErp'])->name('ping');
        Route::middleware('permission:sync')->group(function () {
            Route::get('/', [ErpSyncController::class, 'index'])->name('index');
            Route::post('/all', [ErpSyncController::class, 'syncAll'])->name('all');
            Route::post('/transaction/{transaction}', [ErpSyncController::class, 'syncSingle'])->name('single');
            Route::post('/retry-failed', [ErpSyncController::class, 'retryFailed'])->name('retry');
            Route::post('/pull-products', [ErpSyncController::class, 'pullProducts'])->name('pull-products');
            Route::post('/pull-payment-methods', [ErpSyncController::class, 'pullPaymentMethods'])->name('pull-payment-methods');
            Route::post('/pull-users', [ErpSyncController::class, 'pullUsers'])->name('pull-users');
            Route::post('/pull-delivery-prices', [ErpSyncController::class, 'pullDeliveryPrices'])->name('pull-delivery-prices');
            Route::post('/pull-coupons', [ErpSyncController::class, 'pullCoupons'])->name('pull-coupons');
            Route::post('/push-customer/{customer}', [ErpSyncController::class, 'pushCustomer'])->name('push-customer');
            Route::post('/reconcile-customer/{customer}', [ErpSyncController::class, 'reconcileCustomer'])->name('reconcile-customer');
        });
        Route::middleware('role:admin')->group(function () {
            Route::post('/test-connection', [ErpSyncController::class, 'testConnection'])->name('test');
            Route::post('/settings', [ErpSyncController::class, 'saveSettings'])->name('settings');
            Route::get('/logs', [ErpSyncController::class, 'logs'])->name('logs');
        });
    });

    // ─── Sistem (dulu admin-only; kini per-modul di Hak Akses, admin selalu lolos) ─

    // Kupon
    Route::middleware('permission:coupons')->group(function () {
        Route::resource('coupons', CouponController::class)->only(['index', 'destroy']);
    });

    // Pengaturan Toko
    Route::middleware('permission:settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'save'])->name('settings.save');
        Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
        Route::delete('/settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
    });

    // Hak Akses
    Route::middleware('permission:permissions')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'save'])->name('permissions.save');
    });

    // Backup & Restore
    Route::prefix('backup')->name('backup.')->middleware('permission:backup')->group(function () {
        Route::get('/download', [BackupController::class, 'download'])->name('download');
        Route::get('/restore', [BackupController::class, 'restorePage'])->name('restore');
        Route::post('/restore', [BackupController::class, 'restore'])->name('restore.post');
    });

    // Update Sistem
    Route::prefix('update')->name('update.')->middleware('permission:update')->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/check', [UpdateController::class, 'checkLatest'])->name('check');
        Route::post('/run', [UpdateController::class, 'run'])->name('run');
    });

    // Factory Reset
    Route::prefix('factory-reset')->name('factory-reset.')->middleware('permission:factory_reset')->group(function () {
        Route::get('/', [FactoryResetController::class, 'index'])->name('index');
        Route::get('/counts', [FactoryResetController::class, 'counts'])->name('counts');
        Route::post('/verify', [FactoryResetController::class, 'verify'])->name('verify');
        Route::post('/execute', [FactoryResetController::class, 'execute'])->name('execute');
    });

    // Manajemen User
    Route::middleware('permission:users')->group(function () {
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::resource('users', UserController::class)->except(['show']);
    });

    // Manajemen Role
    Route::middleware('permission:roles')->group(function () {
        Route::post('/roles/slug', [RoleController::class, 'generateSlug'])->name('roles.slug');
        Route::resource('roles', RoleController::class)->except(['show', 'create', 'edit']);
    });

    // Warehouse Assignment
    Route::prefix('warehouses')->name('warehouses.')->middleware('permission:warehouses')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/pull', [WarehouseController::class, 'pull'])->name('pull');
        Route::post('/{warehouse}/toggle', [WarehouseController::class, 'toggle'])->name('toggle');
        Route::post('/{warehouse}/set-default', [WarehouseController::class, 'setDefault'])->name('set-default');
        Route::post('/{warehouse}/set-transit', [WarehouseController::class, 'setTransit'])->name('set-transit');
        Route::post('/{warehouse}/clear-flag', [WarehouseController::class, 'clearFlag'])->name('clear-flag');
    });
});
