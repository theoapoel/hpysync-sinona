<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HPYSync')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --blue: #4285F4;
            --red: #EA4335;
            --yellow: #FBBC05;
            --green: #34A853;
            --blue-dark: #1967D2;
            --blue-light: #E8F0FE;
            --bg: #F8F9FA;
            --surface: #FFFFFF;
            --surface2: #F1F3F4;
            --border: #DADCE0;
            --text: #202124;
            --text2: #5F6368;
            --text3: #80868B;
            --shadow-sm: 0 1px 3px rgba(60,64,67,.15),0 1px 2px rgba(60,64,67,.1);
            --shadow: 0 2px 6px 2px rgba(60,64,67,.15),0 1px 2px rgba(60,64,67,.2);
            --shadow-lg: 0 4px 12px 3px rgba(60,64,67,.15),0 2px 4px rgba(60,64,67,.2);
            --radius: 8px;
            --radius-lg: 12px;
            --nav-w: 240px;
            --header-h: 60px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Roboto', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        /* HEADER */
        .header {
            position: fixed; top: 0; left: 0; right: 0; height: var(--header-h);
            background: var(--surface); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 16px; z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        .header-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; width: var(--nav-w); }
        .brand-logo { width: 32px; height: 32px; display: flex; }
        .brand-text { font-family: 'Google Sans', sans-serif; font-size: 18px; font-weight: 700; }
        .brand-text span:nth-child(1) { color: #4285F4; }
        .brand-text span:nth-child(2) { color: #EA4335; }
        .brand-text span:nth-child(3) { color: #FBBC05; }
        .brand-text span:nth-child(4) { color: #4285F4; }
        .brand-text span:nth-child(5) { color: #34A853; }
        .brand-text span:nth-child(6) { color: #EA4335; }
        .brand-text span:nth-child(7) { color: #4285F4; }
        .header-right { margin-left: auto; display: flex; align-items: center; gap: 16px; }
        .sync-badge { background: var(--blue-light); color: var(--blue); font-size: 12px; padding: 4px 10px; border-radius: 12px; font-weight: 500; cursor: pointer; }
        .sync-badge.warn { background: #FEF3E2; color: #E37400; }
        /* ERP connectivity indicator */
        #erpStatus { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:500; padding:4px 10px; border-radius:12px; transition:background .3s,color .3s; white-space:nowrap; }
        #erpStatus.st-online   { background:#E6F4EA; color:#1E6E3B; }
        #erpStatus.st-offline  { background:#FCE8E6; color:#B31412; }
        #erpStatus.st-checking { background:var(--surface2); color:var(--text3); }
        #erpStatus.st-hidden   { display:none; }
        .erp-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .st-online  .erp-dot { background:#34A853; }
        .st-offline .erp-dot { background:#EA4335; animation:blink 1.4s ease-in-out infinite; }
        .st-checking .erp-dot { background:#BDBDBD; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        .user-menu { display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 6px 10px; border-radius: 20px; transition: background .2s; }
        .user-menu:hover { background: var(--surface2); }
        .user-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--blue); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .user-name { font-size: 14px; font-weight: 500; }

        /* SIDEBAR */
        .sidebar {
            position: fixed; top: var(--header-h); left: 0; width: var(--nav-w);
            height: calc(100vh - var(--header-h)); background: var(--surface);
            border-right: 1px solid var(--border); padding: 8px 0 12px; overflow-y: auto; z-index: 90;
            transition: width .22s ease, transform .22s ease;
            overflow-x: hidden;
        }
        .nav-section { padding: 16px 12px 4px; font-size: 11px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .8px; white-space: nowrap; transition: opacity .15s; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; text-decoration: none; color: var(--text2); font-size: 14px; font-weight: 500; border-radius: 0 24px 24px 0; margin-right: 8px; transition: background .2s, color .2s; white-space: nowrap; position: relative; }
        .nav-item:hover { background: var(--surface2); color: var(--text); }
        .nav-item.active { background: var(--blue-light); color: var(--blue); }
        .nav-item.active .nav-icon { color: var(--blue); }
        .nav-icon { width: 20px; text-align: center; font-size: 16px; flex-shrink: 0; }
        .nav-label { transition: opacity .15s; }
        .nav-badge { margin-left: auto; background: var(--red); color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 10px; font-weight: 700; flex-shrink: 0; }
        .nav-tooltip {
            display: none; position: absolute; left: calc(100% + 10px); top: 50%;
            transform: translateY(-50%); background: rgba(32,33,36,.92); color: #fff;
            padding: 5px 12px; border-radius: 6px; font-size: 13px; white-space: nowrap;
            pointer-events: none; opacity: 0; transition: opacity .15s; z-index: 200;
        }

        /* Sidebar toggle button */
        .sidebar-toggle {
            width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center;
            justify-content: center; cursor: pointer; border: none; background: transparent;
            color: var(--text2); font-size: 18px; margin-right: 4px; transition: background .2s; flex-shrink: 0;
        }
        .sidebar-toggle:hover { background: var(--surface2); }

        /* Mobile backdrop */
        .sidebar-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.45); z-index: 88;
        }

        /* DESKTOP collapsed state */
        body.nav-collapsed .sidebar { width: 64px; }
        body.nav-collapsed .main { margin-left: 64px; }
        body.nav-collapsed .header-brand { width: 64px; }
        body.nav-collapsed .brand-text { opacity: 0; width: 0; overflow: hidden; }
        body.nav-collapsed .nav-section { opacity: 0; height: 0; padding: 0; overflow: hidden; }
        body.nav-collapsed .nav-item { justify-content: center; padding: 10px 0; margin-right: 0; border-radius: 8px; margin: 2px 8px; }
        body.nav-collapsed .nav-label { opacity: 0; width: 0; overflow: hidden; }
        body.nav-collapsed .nav-badge { position: absolute; top: 4px; right: 6px; width: 8px; height: 8px; padding: 0; border-radius: 50%; font-size: 0; }
        body.nav-collapsed .nav-item .nav-tooltip { display: block; }
        body.nav-collapsed .nav-item:hover .nav-tooltip { opacity: 1; }

        /* MAIN CONTENT */
        .main { margin-left: var(--nav-w); margin-top: var(--header-h); padding: 24px; min-height: calc(100vh - var(--header-h)); transition: margin-left .22s ease; }
        .header-brand { transition: width .22s ease; overflow: hidden; }

        /* MOBILE */
        @media (max-width: 767px) {
            .sidebar { transform: translateX(-100%); width: var(--nav-w) !important; }
            body.nav-open .sidebar { transform: translateX(0); }
            body.nav-open .sidebar-backdrop { display: block; }
            .main { margin-left: 0 !important; }
            .header-brand { width: auto !important; }
            body.nav-collapsed .nav-item { justify-content: flex-start; padding: 10px 16px; margin: 0 8px 0 0; border-radius: 0 24px 24px 0; }
            body.nav-collapsed .nav-label { opacity: 1; width: auto; }
            body.nav-collapsed .nav-section { opacity: 1; height: auto; padding: 16px 12px 4px; }
            body.nav-collapsed .nav-item .nav-tooltip { display: none !important; }
        }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .page-title { font-family: 'Google Sans', sans-serif; font-size: 24px; font-weight: 700; color: var(--text); }
        .page-subtitle { font-size: 13px; color: var(--text3); margin-top: 2px; }

        /* CARDS */
        .card { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border); }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-family: 'Google Sans', sans-serif; font-size: 15px; font-weight: 700; }
        .card-body { padding: 20px; }

        /* STAT CARDS */
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: var(--shadow-sm); transition: box-shadow .2s; }
        .stat-card:hover { box-shadow: var(--shadow); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-icon.blue { background: var(--blue-light); color: var(--blue); }
        .stat-icon.green { background: #E6F4EA; color: var(--green); }
        .stat-icon.yellow { background: #FEF3E2; color: #E37400; }
        .stat-icon.red { background: #FCE8E6; color: var(--red); }
        .stat-value { font-family: 'Google Sans', sans-serif; font-size: 22px; font-weight: 700; line-height: 1; }
        .stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; font-weight: 500; }

        /* BUTTONS */
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all .2s; font-family: 'Google Sans', sans-serif; }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: var(--blue-dark); box-shadow: var(--shadow-sm); }
        .btn-success { background: var(--green); color: #fff; }
        .btn-success:hover { background: #2D9247; }
        .btn-danger { background: var(--red); color: #fff; }
        .btn-danger:hover { background: #C5221F; }
        .btn-warning { background: #F9AB00; color: #fff; }
        .btn-warning:hover { background: #E69500; }
        .btn-outline { background: transparent; color: var(--blue); border: 1px solid var(--blue); }
        .btn-outline:hover { background: var(--blue-light); }
        .btn-ghost { background: transparent; color: var(--text2); }
        .btn-ghost:hover { background: var(--surface2); }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        .btn-lg { padding: 12px 24px; font-size: 15px; border-radius: 24px; }

        /* TABLES */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead tr { border-bottom: 2px solid var(--border); }
        th { padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border); color: var(--text); }
        tbody tr:hover { background: #F8F9FA; }
        tbody tr:last-child td { border-bottom: none; }

        /* BADGES */
        .badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge-blue { background: var(--blue-light); color: var(--blue); }
        .badge-green { background: #E6F4EA; color: var(--green); }
        .badge-red { background: #FCE8E6; color: var(--red); }
        .badge-yellow { background: #FEF3E2; color: #E37400; }
        .badge-gray { background: var(--surface2); color: var(--text2); }

        /* FORMS */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text2); margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--text); background: var(--surface); transition: border-color .2s, box-shadow .2s; font-family: 'Roboto', sans-serif; }
        .form-control:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(66,133,244,.15); }
        .form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%235F6368'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 20px; padding-right: 36px; }

        /* ALERTS */
        .alert { padding: 12px 16px; border-radius: var(--radius); margin-bottom: 16px; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #E6F4EA; color: #137333; border-left: 4px solid var(--green); }
        .alert-danger { background: #FCE8E6; color: #C5221F; border-left: 4px solid var(--red); }
        .alert-warning { background: #FEF3E2; color: #B06000; border-left: 4px solid var(--yellow); }
        .alert-info { background: var(--blue-light); color: var(--blue-dark); border-left: 4px solid var(--blue); }

        /* MODAL */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); max-width: 480px; width: 90%; max-height: 90vh; overflow-y: auto; animation: modalIn .2s ease; }
        @keyframes modalIn { from { opacity:0; transform:translateY(-20px) scale(.97); } to { opacity:1; transform:none; } }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-family:'Google Sans',sans-serif; font-size: 18px; font-weight: 700; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }

        /* TOASTS */
        #toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { background: var(--text); color: #fff; padding: 14px 20px; border-radius: var(--radius); font-size: 14px; box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: 10px; animation: toastIn .3s ease; min-width: 280px; }
        .toast.success { background: var(--green); }
        .toast.error { background: var(--red); }
        .toast.warning { background: #E37400; }
        @keyframes toastIn { from { opacity:0; transform:translateX(40px); } to { opacity:1; transform:none; } }

        /* UTILITIES */
        .text-blue { color: var(--blue); }
        .text-green { color: var(--green); }
        .text-red { color: var(--red); }
        .text-muted { color: var(--text3); }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 500; }
        .text-sm { font-size: 13px; }
        .text-xs { font-size: 11px; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .p-4 { padding: 16px; }
        .rounded { border-radius: var(--radius); }
        .w-full { width: 100%; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; display: inline-block; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
        .money { font-family: 'Google Sans', sans-serif; font-weight: 700; }

        /* Pagination — dipakai semua halaman berdaftar */
        .pagination-wrap { padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 12px; }
        .pagination-info { font-size: 13px; color: var(--text3); }
        .pagination { display: flex; align-items: center; gap: 4px; list-style: none; margin: 0; padding: 0; flex-wrap: wrap; }
        .pagination li a, .pagination li span {
            display: flex; align-items: center; justify-content: center;
            min-width: 36px; height: 36px; border-radius: 8px; padding: 0 10px;
            font-size: 13px; font-weight: 600; text-decoration: none;
            color: var(--text2); border: 1px solid var(--border);
            background: var(--surface); transition: all .2s; gap: 5px;
        }
        .pagination li a:hover { background: var(--blue-light); color: var(--blue); border-color: var(--blue); }
        .pagination li.active span { background: var(--blue); color: #fff; border-color: var(--blue); }
        .pagination li.disabled span { opacity: .4; cursor: not-allowed; }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar backdrop (mobile) -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

    <!-- Header -->
    <header class="header">
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <a href="{{ route('dashboard') }}" class="header-brand">
            <img src="{{ asset('images/happypos.png') }}" alt="HPYSync"
                style="height:52px;width:auto;object-fit:contain;">
        </a>
        <div class="header-right">
            <div id="erpStatus" class="st-checking" title="Status koneksi ERP HPY">
                <span class="erp-dot"></span>
                <span id="erpStatusText">ERP…</span>
            </div>
            @php $pendingSync = \App\Models\Transaction::where('erp_sync_status','pending')->where('status','completed')->count(); @endphp
            @if($pendingSync > 0)
            <a href="{{ route('sync.index') }}" class="sync-badge warn">
                <i class="fas fa-sync-alt"></i> {{ $pendingSync }} Pending Sync
            </a>
            @endif
            <div class="user-menu">
                <div class="user-avatar">{{ substr(auth()->user()->name, 0, 1) }}</div>
                <span class="user-name">{{ auth()->user()->name }}</span>
                <i class="fas fa-chevron-down text-xs text-muted"></i>
            </div>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-sign-out-alt"></i></button>
            </form>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        @php
            $u         = auth()->user();
            $role      = $u?->role;
            $roleModel = \App\Models\Role::where('name', $role)->first();
            $roleColor = $roleModel?->color ?? '#4285F4';
            $canDashboard     = $u->hasPermission('dashboard');
            $canPos           = $u->hasPermission('pos');
            $canTransactions  = $u->hasPermission('transactions');
            $canProducts      = $u->hasPermission('products');
            $canCustomers     = $u->hasPermission('customers');
            $canStockTransfer = $u->hasPermission('stock_transfer');
            $canStock         = $u->hasPermission('stock');
            $canSync          = $u->hasPermission('sync');
            $canDelivery      = $u->hasPermission('delivery');
            $canKitchen       = $u->hasPermission('kitchen');
            $canStockRequest  = $u->hasPermission('stock_request');
            $canSlice         = $u->hasPermission('slice');
            $canRekapOrder    = $u->hasPermission('rekap_order');
            $canPullingOrder  = $u->hasPermission('pulling_order');
            $canStockOpname   = $u->hasPermission('stock_opname');
            $canDeliveryNotes = $u->hasPermission('delivery_notes');
            $canOnlineReport  = $u->hasPermission('online_report');
            $canMopReport     = $u->hasPermission('mop_report');
            $canDoReport      = $u->hasPermission('do_report');
            $canCoupons       = $u->hasPermission('coupons');
            $canUsers         = $u->hasPermission('users');
            $canRoles         = $u->hasPermission('roles');
            $canPermissions   = $u->hasPermission('permissions');
            $canWarehouses    = $u->hasPermission('warehouses');
            $canSettings      = $u->hasPermission('settings');
            $canBackup        = $u->hasPermission('backup');
            $canUpdate        = $u->hasPermission('update');
            $canFactoryReset  = $u->hasPermission('factory_reset');
            $showManajemen    = $canProducts || $canCustomers || $canStockTransfer || $canStock || $canStockOpname || $canSlice;
            $showSistem       = $canCoupons || $canUsers || $canRoles || $canPermissions || $canWarehouses || $canSettings || $canBackup || $canUpdate || $canFactoryReset;
        @endphp

        @php
        $navItem = fn(string $href, string $icon, string $label, bool $active) =>
            "<a href=\"{$href}\" class=\"nav-item" . ($active ? ' active' : '') . "\">"
            . "<i class=\"{$icon} nav-icon\"></i>"
            . "<span class=\"nav-label\">{$label}</span>"
            . "<span class=\"nav-tooltip\">{$label}</span>"
            . "</a>";
        @endphp

        <div class="nav-section">Menu</div>
        @if($canDashboard)
        {!! $navItem(route('dashboard'), 'fas fa-th-large', 'Dashboard', request()->routeIs('dashboard')) !!}
        @endif
        @if($canPos)
        {!! $navItem(route('pos.kasir'), 'fas fa-cash-register', 'Kasir', request()->routeIs('pos.index') || request()->routeIs('pos.quick') || request()->routeIs('pos.express')) !!}
        @endif
        @if($canTransactions)
        {!! $navItem(route('transactions.index'), 'fas fa-receipt', 'Transaksi', request()->routeIs('transactions.*')) !!}
        @endif

        @if($showManajemen)
        <div class="nav-section">Manajemen</div>
        @if($canProducts)
        {!! $navItem(route('products.index'), 'fas fa-box', 'Produk', request()->routeIs('products.*')) !!}
        @endif
        @if($canCustomers)
        {!! $navItem(route('customers.index'), 'fas fa-users', 'Customer', request()->routeIs('customers.*')) !!}
        @endif
        @if($canStock)
        {!! $navItem(route('stock.index'), 'fas fa-boxes', 'Stok Barang', request()->routeIs('stock.index') || request()->routeIs('stock.debug*') || request()->routeIs('stock.sync*')) !!}
        @endif
        @if($canStockOpname)
        {!! $navItem(route('stock-opname.index'), 'fas fa-clipboard-list', 'Stock Opname', request()->routeIs('stock-opname.*')) !!}
        @endif
        @if($canSlice)
        {!! $navItem(route('slices.index'), 'fas fa-scissors', 'Repack', request()->routeIs('slices.*')) !!}
        @endif
        @if($canStockTransfer)
        {!! $navItem(route('stock-transfer.index'), 'fas fa-truck-loading', 'Transfer Barang', request()->routeIs('stock-transfer.*')) !!}
        @endif
        @endif

        @if($canDelivery || $canDeliveryNotes || $canKitchen || $canStockRequest || $canRekapOrder || $canPullingOrder)
        <div class="nav-section">Delivery & Dapur</div>
        @if($canDelivery)
        {!! $navItem(route('delivery-orders.index'), 'fas fa-truck', 'Delivery Order', request()->routeIs('delivery-orders.*')) !!}
        @endif
        @if($canDeliveryNotes)
        {!! $navItem(route('delivery-notes.index'), 'fas fa-map-marker-alt', 'Delivery Notes', request()->routeIs('delivery-notes.*')) !!}
        @endif
        @if($canStockRequest)
        {!! $navItem(route('stock-requests.index'), 'fas fa-clipboard-check', 'Permintaan FG', request()->routeIs('stock-requests.*')) !!}
        @endif
        @if($canPullingOrder)
        {!! $navItem(route('pulling-order.index'), 'fas fa-list-check', 'Pulling Order', request()->routeIs('pulling-order.*')) !!}
        @endif
        @if($canRekapOrder)
        {!! $navItem(route('rekap-order.index'), 'fas fa-layer-group', 'Rekap Order', request()->routeIs('rekap-order.*')) !!}
        @endif
        @if($canKitchen)
        {!! $navItem(route('kitchen.index'), 'fas fa-utensils', 'Kitchen Monitor', request()->routeIs('kitchen.*')) !!}
        @endif
        @endif

        @if($canSync || $canOnlineReport || $canMopReport || $canDoReport)
        <div class="nav-section">Integrasi</div>
        @if($canSync)
        <a href="{{ route('sync.index') }}" class="nav-item {{ request()->routeIs('sync.*') ? 'active' : '' }}">
            <i class="fas fa-sync-alt nav-icon"></i>
            <span class="nav-label">Sync HPY</span>
            @if($pendingSync > 0)<span class="nav-badge">{{ $pendingSync }}</span>@endif
            <span class="nav-tooltip">Sync HPY</span>
        </a>
        @endif
        @if($canOnlineReport)
        {!! $navItem(route('online-report.index'), 'fas fa-cloud-download-alt', 'Laporan Online', request()->routeIs('online-report.*')) !!}
        @endif
        @if($canMopReport)
        {!! $navItem(route('mop-report.index'), 'fas fa-wallet', 'Laporan Pembayaran', request()->routeIs('mop-report.*')) !!}
        @endif
        @if($canDoReport)
        {!! $navItem(route('do-report.index'), 'fas fa-file-invoice-dollar', 'Laporan DO', request()->routeIs('do-report.*')) !!}
        @endif
        @endif

        @if($showSistem)
        <div class="nav-section">Sistem</div>
        @if($canCoupons)
        {!! $navItem(route('coupons.index'),     'fas fa-ticket-alt',      'Kupon',            request()->routeIs('coupons.*')) !!}
        @endif
        @if($canUsers)
        {!! $navItem(route('users.index'),       'fas fa-users-cog',       'Manajemen User',   request()->routeIs('users.*')) !!}
        @endif
        @if($canRoles)
        {!! $navItem(route('roles.index'),       'fas fa-user-shield',     'Manajemen Role',   request()->routeIs('roles.*')) !!}
        @endif
        @if($canPermissions)
        {!! $navItem(route('permissions.index'), 'fas fa-shield-alt',      'Hak Akses',        request()->routeIs('permissions.*')) !!}
        @endif
        @if($canWarehouses)
        {!! $navItem(route('warehouses.index'),  'fas fa-warehouse',       'Warehouse',        request()->routeIs('warehouses.*')) !!}
        @endif
        @if($canSettings)
        {!! $navItem(route('settings.index'),    'fas fa-store',           'Pengaturan Toko',  request()->routeIs('settings.*')) !!}
        @endif
        @if($canBackup)
        {!! $navItem(route('backup.restore'),    'fas fa-upload',          'Restore Backup',   request()->routeIs('backup.*')) !!}
        @endif
        @if($canUpdate)
        {!! $navItem(route('update.index'),      'fas fa-download',        'Update Sistem',    request()->routeIs('update.*')) !!}
        @endif
        @if($canFactoryReset)
        <a href="{{ route('factory-reset.index') }}"
            class="nav-item {{ request()->routeIs('factory-reset.*') ? 'active' : '' }}"
            style="color:#EA4335">
            <i class="fas fa-trash-alt nav-icon" style="color:#EA4335"></i>
            <span class="nav-label">Factory Reset</span>
            <span class="nav-tooltip">Factory Reset</span>
        </a>
        @endif
        @endif

    </nav>

    <!-- Main -->
    <main class="main">
        @if(session('success'))
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @yield('content')
    </main>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <script>
    // Global helpers
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const api = {
        async post(url, data={}) {
            const r = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
                body: JSON.stringify(data)
            });
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                // Server returned non-JSON (HTML error page, redirect, etc.)
                const preview = text.substring(0, 300);
                console.error('Non-JSON response from', url, ':', preview);
                throw new Error('Server error (HTTP ' + r.status + '). Cek Laravel log.');
            }
        },
        async get(url) {
            const r = await fetch(url, { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch(e) {
                console.error('Non-JSON response from', url, ':', text.substring(0, 300));
                throw new Error('Server error (HTTP ' + r.status + ')');
            }
        }
    };

    function toast(msg, type='success', dur=3500) {
        const c = document.getElementById('toast-container');
        const t = document.createElement('div');
        t.className = `toast ${type}`;
        const icons = {success:'check-circle', error:'exclamation-circle', warning:'exclamation-triangle'};
        t.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i> ${msg}`;
        c.appendChild(t);
        setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(40px)'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); }, dur);
    }

    function formatMoney(n) {
        return 'Rp ' + parseFloat(n||0).toLocaleString('id-ID',{minimumFractionDigits:0});
    }

    // ── Sidebar toggle ────────────────────────────────────────────
    function isMobile() { return window.innerWidth <= 767; }

    function toggleSidebar() {
        if (isMobile()) {
            document.body.classList.toggle('nav-open');
        } else {
            const collapsed = document.body.classList.toggle('nav-collapsed');
            localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0');
        }
    }

    function closeSidebar() {
        document.body.classList.remove('nav-open');
    }

    // Restore desktop collapsed state
    if (!isMobile() && localStorage.getItem('sidebarCollapsed') === '1') {
        document.body.classList.add('nav-collapsed');
    }

    // Close mobile sidebar when a nav item is clicked
    document.querySelectorAll('.nav-item').forEach(function(item) {
        item.addEventListener('click', function() {
            if (isMobile()) closeSidebar();
        });
    });

    // Close on resize to desktop
    window.addEventListener('resize', function() {
        if (!isMobile()) document.body.classList.remove('nav-open');
    });

    // ── ERP connectivity indicator ────────────────────────────
    (function() {
        const pingUrl  = '{{ route("sync.ping") }}';
        const el       = document.getElementById('erpStatus');
        const txtEl    = document.getElementById('erpStatusText');
        let lastOnline = null;

        function setStatus(state, label) {
            el.className = state;
            txtEl.textContent = label;
        }

        async function checkErp() {
            if (!navigator.onLine) {
                setStatus('st-offline', 'Offline');
                lastOnline = false;
                return;
            }
            try {
                const res  = await fetch(pingUrl, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, signal: AbortSignal.timeout(6000) });
                const json = await res.json();
                if (json.reason === 'not_configured') {
                    setStatus('st-hidden', '');
                } else if (json.reachable) {
                    setStatus('st-online', 'ERP Online');
                    lastOnline = true;
                } else {
                    setStatus('st-offline', 'ERP Offline');
                    lastOnline = false;
                }
            } catch(e) {
                setStatus('st-offline', 'ERP Offline');
                lastOnline = false;
            }
        }

        checkErp();
        setInterval(checkErp, 30000);

        window.addEventListener('online',  () => checkErp());
        window.addEventListener('offline', () => setStatus('st-offline', 'Offline'));
    })();
    </script>
    @stack('scripts')
</body>
</html>
