<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kasir — HPYSync</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto+Mono:wght@400;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #4285F4;
            --primary-dark: #1967D2;
            --primary-light: #E8F0FE;
            --secondary: #FBBC05;
            --green: #34A853;
            --green-light: #E6F4EA;
            --red: #EA4335;
            --red-light: #FCE8E6;
            --bg: #F8F9FA;
            --surface: #FFFFFF;
            --surface2: #F1F3F4;
            --border: #DADCE0;
            --text: #202124;
            --text2: #5F6368;
            --text3: #80868B;
            --dark-bg: #202124;
            --shadow: 0 2px 6px 2px rgba(60,64,67,.15),0 1px 2px rgba(60,64,67,.2);
            --radius: 12px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* TOP BAR */
        .pos-topbar {
            height: 52px; background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; padding: 0 16px; gap: 16px;
            flex-shrink: 0; z-index: 10;
        }
        .pos-topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .pos-topbar-brand img { height: 30px; width: auto; object-fit: contain; }
        .topbar-divider { width: 1px; height: 24px; background: var(--border); }
        .topbar-info { display: flex; align-items: center; gap: 6px; color: var(--text3); font-size: 13px; }
        .topbar-info i { color: var(--primary); }
        .topbar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
        /* Total belanja di tengah topbar */
        .topbar-total {
            margin-left: auto; display: flex; align-items: baseline; gap: 10px;
            background: #E6F4EA; border: 1px solid #A8D5B5;
            padding: 5px 16px; border-radius: 999px; white-space: nowrap;
        }
        .topbar-total-label {
            font-size: 11px; font-weight: 800; color: #1E8E3E;
            text-transform: uppercase; letter-spacing: .6px;
        }
        .topbar-total-value {
            font-size: 22px; font-weight: 900; color: #0D652D;
            font-family: 'Roboto Mono', monospace; line-height: 1;
        }
        @media (max-width: 1200px) { .topbar-total-label { display: none; } }
        .topbar-btn {
            padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500;
            border: 1px solid var(--border); background: transparent;
            color: var(--text2); cursor: pointer; text-decoration: none;
            display: flex; align-items: center; gap: 6px; transition: all .2s;
            font-family: 'Roboto', sans-serif;
        }
        .topbar-btn:hover { background: var(--surface2); color: var(--text); }
        #clock { font-family: 'Roboto Mono', monospace; font-size: 13px; color: var(--text3); }

        /* 2-COLUMN LAYOUT */
        .pos-layout { display: flex; flex: 1; overflow: hidden; }

        /* ── TOP: Search bar ── */
        .pos-top {
            background: var(--surface);
            border-bottom: 2px solid var(--border);
            flex-shrink: 0; padding: 10px 14px;
        }
        .search-wrap { position: relative; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: 13px; pointer-events: none; }
        .search-input {
            width: 100%; padding: 10px 14px 10px 38px;
            border: 2px solid var(--border); border-radius: 24px;
            font-size: 14px; font-weight: 600; font-family: 'Roboto', sans-serif;
            background: var(--bg); color: var(--text); transition: all .2s;
        }
        .search-input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(66,133,244,.12); }

        /* Search dropdown */
        .search-dropdown {
            position: absolute; left: 14px; right: 14px; top: calc(100% - 2px);
            background: var(--surface); border: 2px solid var(--primary);
            border-top: none; border-radius: 0 0 16px 16px;
            max-height: 340px; overflow-y: auto; z-index: 200;
            display: none; box-shadow: 0 8px 24px rgba(60,64,67,.18);
        }
        .search-dropdown.show { display: block; }
        .search-dropdown::-webkit-scrollbar { width: 3px; }
        .search-dropdown::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .dd-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 16px; cursor: pointer; border-bottom: 1px solid var(--bg);
            transition: background .1s;
        }
        .dd-item:last-child { border-bottom: none; border-radius: 0 0 14px 14px; }
        .dd-item:hover { background: var(--primary-light); }
        .dd-item.oos { opacity: .45; cursor: not-allowed; }
        .dd-item.oos:hover { background: transparent; }
        .dd-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .dd-name { flex: 1; font-size: 13px; font-weight: 700; color: var(--text); min-width: 0; }
        .dd-meta { font-size: 10px; color: var(--text3); margin-top: 1px; }
        .dd-right { text-align: right; flex-shrink: 0; }
        .dd-price { font-size: 13px; font-weight: 900; color: var(--primary); }
        .dd-qty { font-size: 10px; font-weight: 800; color: var(--green); }
        .dd-empty { padding: 20px; text-align: center; color: var(--text3); font-size: 13px; font-weight: 700; }
        .dd-img {
            width: 48px; height: 48px; border-radius: 8px; flex-shrink: 0;
            background: var(--surface2); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; color: var(--text3);
        }
        .dd-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .badge-sm { display: inline-block; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 6px; }
        .badge-out { background: var(--red-light); color: var(--red); }
        .badge-low { background: #FFF3E0; color: #E65100; }

        /* ── CENTER: Cart Table ── */
        .pos-center {
            flex: 1; display: flex; flex-direction: column; overflow: hidden; background: var(--bg);
        }
        .cart-header {
            padding: 10px 16px; background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0;
        }
        .cart-header-title {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 900; color: var(--text2);
            text-transform: uppercase; letter-spacing: .5px;
        }
        .cart-count {
            font-size: 11px; font-weight: 700; color: var(--primary);
            background: var(--primary-light); padding: 2px 8px; border-radius: 10px;
        }
        .btn-clear-all {
            padding: 5px 14px; border-radius: 16px; font-size: 12px; font-weight: 700;
            border: 1.5px solid var(--red); color: var(--red); background: transparent;
            cursor: pointer; transition: all .15s; font-family: 'Roboto', sans-serif;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-clear-all:hover { background: var(--red-light); }
        .cart-table-wrap { flex: 1; overflow-y: auto; overflow-x: auto; }
        .cart-table-wrap::-webkit-scrollbar { width: 4px; height: 4px; }
        .cart-table-wrap::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        /* Empty state */
        .cart-empty {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 100%; padding: 60px 20px;
            color: var(--text3); gap: 12px; text-align: center;
        }
        .cart-empty-icon { font-size: 60px; opacity: .18; }
        .cart-empty-text { font-size: 14px; font-weight: 700; }

        /* Table */
        .cart-table { width: 100%; border-collapse: collapse; font-family: 'Roboto', sans-serif; }
        .cart-table th {
            position: sticky; top: 0; background: var(--surface);
            font-size: 10px; font-weight: 900; color: var(--text3);
            text-transform: uppercase; letter-spacing: .4px;
            padding: 10px 12px; text-align: left;
            border-bottom: 2px solid var(--border);
            white-space: nowrap; z-index: 2;
        }
        .cart-table td { padding: 8px 12px; border-bottom: 1px solid var(--surface2); vertical-align: middle; }
        .cart-table tbody tr:hover td { background: #FAFAFA; }
        .col-num { width: 44px; text-align: center; }
        .col-qty { width: 150px; }
        .col-price { width: 148px; }
        .col-disc { width: 118px; }
        .col-subtotal { width: 126px; text-align: right; }
        .col-actions { width: 70px; text-align: center; }
        .row-num {
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--primary); color: #fff;
            font-size: 11px; font-weight: 900;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .row-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.3; }
        .row-note { font-size: 11px; color: var(--text3); font-style: italic; margin-top: 2px; }
        .qty-wrap { display: flex; align-items: center; gap: 4px; }
        .qty-btn {
            width: 26px; height: 26px; border-radius: 50%;
            border: 2px solid var(--border); background: var(--surface);
            cursor: pointer; font-size: 13px; font-weight: 900;
            display: inline-flex; align-items: center; justify-content: center;
            transition: all .12s; color: var(--text); font-family: 'Roboto', sans-serif;
        }
        .qty-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .qty-input {
            width: 64px; text-align: center; border: 2px solid var(--border);
            border-radius: 8px; padding: 3px 4px; font-size: 13px; font-weight: 800;
            font-family: 'Roboto Mono', monospace;
        }
        .action-btn {
            width: 28px; height: 28px; border-radius: 8px;
            border: 1.5px solid var(--border); background: var(--surface2);
            cursor: pointer; font-size: 12px; color: var(--text3);
            display: inline-flex; align-items: center; justify-content: center;
            transition: all .12s;
        }
        .action-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .action-btn.has-note { border-color: var(--secondary); background: #FFF3E0; color: #E65100; }
        .action-btn.del-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-light); }

        /* ── RIGHT: Payment Panel ── */
        .pos-payment {
            width: 440px; flex-shrink: 0;
            background: var(--surface);
            border-left: 2px solid var(--border);
            display: flex; flex-direction: column;
            overflow-y: auto; overflow-x: hidden;
        }
        .pos-payment::-webkit-scrollbar { width: 3px; }
        .pos-payment::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .sec { padding: 10px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .sec-label { font-size: 10px; font-weight: 900; color: var(--text3); letter-spacing: .5px; text-transform: uppercase; margin-bottom: 6px; }

        /* Order type */
        .order-type-bar { display: flex; gap: 6px; }
        .order-type-btn {
            flex: 1; padding: 7px 4px; border-radius: 8px;
            border: 2px solid var(--border); background: var(--surface2);
            font-size: 11px; font-weight: 800; color: var(--text2);
            cursor: pointer; text-align: center; transition: all .15s;
            font-family: 'Roboto', sans-serif;
        }
        .order-type-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .table-row { display: flex; align-items: center; gap: 8px; margin-top: 7px; }
        .table-lbl { font-size: 11px; font-weight: 700; color: var(--text3); white-space: nowrap; }
        .table-input {
            flex: 1; padding: 6px 10px; border: 2px solid var(--border);
            border-radius: 8px; font-size: 13px; font-weight: 800;
            font-family: 'Roboto', sans-serif; text-align: center; color: var(--text); background: var(--bg);
        }
        .table-input:focus { outline: none; border-color: var(--primary); }

        /* Delivery platform */
        .sec.delivery-sec { display: none; }
        .sec.delivery-sec.show { display: block; }
        .platform-bar { display: flex; gap: 6px; }
        .platform-btn {
            flex: 1; padding: 6px 4px; border-radius: 8px; border: 2px solid var(--border);
            cursor: pointer; text-align: center; transition: all .15s;
            font-size: 11px; font-weight: 800; background: var(--surface);
            font-family: 'Roboto', sans-serif;
        }
        .platform-btn.gofood  { color: #00AE11; }
        .platform-btn.gofood.active  { background: #00AE11; color: #fff; border-color: #00AE11; }
        .platform-btn.grabfood  { color: #00B14F; }
        .platform-btn.grabfood.active { background: #00B14F; color: #fff; border-color: #00B14F; }
        .platform-btn.shopeefood { color: #EE4D2D; }
        .platform-btn.shopeefood.active { background: #EE4D2D; color: #fff; border-color: #EE4D2D; }

        /* Customer */
        .customer-btn {
            width: 100%; padding: 8px 12px; border: 2px dashed var(--border);
            border-radius: 10px; background: transparent; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; color: var(--text3);
            transition: all .15s; font-family: 'Roboto', sans-serif;
        }
        .customer-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .customer-btn.has-customer { border-style: solid; border-color: var(--green); color: var(--text); background: var(--green-light); }

        /* Discount */
        .disc-row { display: flex; gap: 8px; }
        .disc-input {
            flex: 1; padding: 7px 10px; border: 2px solid var(--border);
            border-radius: 8px; font-size: 13px; font-weight: 700;
            font-family: 'Roboto', sans-serif; color: var(--text); background: var(--bg);
        }
        .disc-input:focus { outline: none; border-color: var(--primary); background: #fff; }

        /* Summary */
        .summary-row {
            display: flex; justify-content: space-between;
            font-size: 12px; font-weight: 600; color: var(--text2); margin-bottom: 4px;
        }
        .summary-row.total {
            font-size: 19px; font-weight: 700; color: var(--text);
            border-top: 2px solid var(--border); padding-top: 8px; margin-top: 6px; margin-bottom: 0;
            font-family: 'Google Sans', sans-serif;
        }

        /* Payment */
        .payment-methods {
            display: flex; flex-wrap: nowrap; gap: 6px;
            overflow-x: auto; padding-bottom: 2px;
            scrollbar-width: thin; scrollbar-color: var(--border) transparent;
        }
        .payment-methods::-webkit-scrollbar { height: 3px; }
        .payment-methods::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .pay-btn {
            flex-shrink: 0; min-width: 74px; padding: 7px 4px;
            border: 2px solid var(--border); border-radius: 10px;
            background: var(--surface2); cursor: pointer; text-align: center;
            font-size: 10px; font-weight: 800; color: var(--text2); transition: all .15s;
            font-family: 'Roboto', sans-serif;
        }
        .pay-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .pay-btn.active { border-color: var(--primary); background: var(--primary); color: #fff; }
        .pay-icon { font-size: 16px; display: block; margin-bottom: 2px; }
        .paid-input {
            width: 100%; padding: 10px 14px; border: 2px solid var(--primary);
            border-radius: 10px; font-size: 20px; font-weight: 800;
            font-family: 'Roboto Mono', monospace; text-align: right;
            color: var(--text); margin-bottom: 7px;
        }
        .quick-amts { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 8px; }
        .quick-amt-btn {
            padding: 4px 10px; border: 1.5px solid var(--border); border-radius: 14px;
            font-size: 11px; cursor: pointer; background: var(--surface2);
            font-weight: 800; font-family: 'Roboto', sans-serif; color: var(--text2);
            transition: all .12s;
        }
        .quick-amt-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .change-box {
            background: var(--green-light); border-radius: 10px; padding: 8px 14px;
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .change-label { font-size: 12px; font-weight: 700; color: var(--green); }
        .change-value { font-size: 18px; font-weight: 900; font-family: 'Roboto Mono', monospace; color: var(--green); }

        /* Checkout */
        .btn-checkout {
            width: 100%; padding: 14px; background: var(--green); color: #fff;
            border: none; border-radius: var(--radius); font-size: 16px; font-weight: 700;
            font-family: 'Google Sans', sans-serif; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all .2s;
        }
        .btn-checkout:hover { background: #2D9247; box-shadow: 0 4px 12px rgba(52,168,83,.4); }
        .btn-checkout:disabled { background: var(--text3); cursor: not-allowed; box-shadow: none; }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: var(--surface); border-radius: 20px; box-shadow: 0 12px 48px rgba(0,0,0,.25); max-width: 480px; width: 90%; max-height: 90vh; overflow-y: auto; animation: modalIn .2s ease; }
        @keyframes modalIn { from{opacity:0;transform:scale(.94)}to{opacity:1;transform:none} }
        .modal-header { padding: 18px 22px; border-bottom: 2px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 17px; font-weight: 900; color: var(--text); }
        .modal-body { padding: 18px 22px; }
        .modal-footer { padding: 12px 22px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 22px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: all .2s; font-family: 'Google Sans', sans-serif; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--green); color: #fff; }
        .btn-ghost { background: transparent; color: var(--text2); border: 2px solid var(--border); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .form-control { width: 100%; padding: 9px 13px; border: 2px solid var(--border); border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--text); margin-bottom: 10px; font-family: 'Roboto', sans-serif; }
        .form-control:focus { outline: none; border-color: var(--primary); }

        /* Receipt */
        .receipt { font-family: 'Roboto Mono', monospace; font-size: 13px; max-width: 300px; margin: 0 auto; }
        .receipt-header { text-align: center; margin-bottom: 12px; }
        .receipt-title { font-size: 18px; font-weight: 700; }
        .receipt-divider { border: none; border-top: 1px dashed var(--border); margin: 8px 0; }
        .receipt-row { display: flex; justify-content: space-between; margin: 4px 0; }
        .receipt-total { font-size: 16px; font-weight: 700; }

        /* Toast */
        #toasts { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { background: var(--dark-bg); color: #fff; padding: 10px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; box-shadow: var(--shadow); display: flex; align-items: center; gap: 8px; animation: toastIn .3s ease; min-width: 240px; font-family: 'Roboto', sans-serif; }
        .toast.ok { background: var(--green); }
        .toast.err { background: var(--red); }
        .toast.warn { background: #E65100; }
        @keyframes toastIn { from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none} }
        .spinner { width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; display: inline-block; }
        @keyframes spin { to{transform:rotate(360deg)} }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="pos-topbar">
    <a href="{{ route('dashboard') }}" class="pos-topbar-brand">
        <img src="{{ asset('images/happypos.png') }}" alt="HPYSync">
    </a>
    <div class="topbar-divider"></div>
    <div class="topbar-info"><i class="fas fa-utensils"></i> {{ $storeSettings['pos_profile'] ?: $storeSettings['store_name'] }}</div>
    <div class="topbar-divider"></div>
    <div class="topbar-info"><i class="fas fa-user-circle"></i> {{ auth()->user()->name }}</div>
    <span id="clock"></span>
    <div class="topbar-total">
        <span class="topbar-total-label">Total Belanja</span>
        <span class="topbar-total-value" id="topbarTotalDisplay">Rp 0</span>
    </div>
    <div class="topbar-actions">
        @if(\App\Models\PosShift::featureEnabled())
        <button type="button" id="btnShift" class="topbar-btn" onclick="onShiftButton()">
            <i class="fas fa-cash-register"></i> <span id="shiftBtnLabel">Kasir</span>
        </button>
        @endif
        <a href="{{ route('pos.index') }}" class="topbar-btn"><i class="fas fa-th"></i> Mode Kartu</a>
        <a href="{{ route('transactions.index') }}" class="topbar-btn"><i class="fas fa-history"></i> Riwayat</a>
        <a href="{{ route('dashboard') }}" class="topbar-btn"><i class="fas fa-th-large"></i> Dashboard</a>
    </div>
</div>

<!-- 2-Column Layout -->
<div class="pos-layout">

    <!-- CENTER: Search + Products + Cart Table -->
    <div class="pos-center">

        <!-- TOP: Search + Dropdown -->
        <div class="pos-top" style="position:relative">
            <div class="search-wrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input"
                    placeholder="Cari menu... (F3)" autocomplete="off">
            </div>
            <div class="search-dropdown" id="searchDropdown"></div>
        </div>

        <!-- Cart Header: item count + total + clear -->
        <div class="cart-header">
            <div class="cart-header-title">
                <i class="fas fa-clipboard-list" style="color:var(--primary)"></i>
                Pesanan
                <span class="cart-count" id="orderCount">0 item</span>
            </div>
            <button class="btn-clear-all" onclick="clearCart()">
                <i class="fas fa-trash"></i> Hapus
            </button>
        </div>
        <div class="cart-table-wrap" id="cartWrap">
            <div class="cart-empty" id="cartEmpty">
                <div class="cart-empty-icon">🛒</div>
                <div class="cart-empty-text">
                    Keranjang masih kosong<br>
                    <small style="font-size:12px;font-weight:600;color:var(--text3)">Cari dan pilih menu di atas untuk menambahkan</small>
                </div>
            </div>
            <table class="cart-table" id="cartTable" style="display:none">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th>Nama Menu</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Harga Satuan</th>
                        <th class="col-disc">Diskon Item</th>
                        <th class="col-subtotal">Subtotal</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody id="cartBody"></tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT: Customer + Payment -->
    <div class="pos-payment">

        <!-- Total Besar -->
        <div style="padding:16px 16px 12px;border-bottom:2px solid var(--border);background:var(--surface);text-align:center;flex-shrink:0">
            <div style="font-size:11px;font-weight:800;color:var(--text3);letter-spacing:.5px;text-transform:uppercase;margin-bottom:2px">Total</div>
            <div id="totalDisplayTop" style="font-size:48px;font-weight:900;color:var(--green);font-family:'Roboto Mono',monospace;line-height:1;letter-spacing:-1px;white-space:nowrap">Rp 0</div>
        </div>

        <!-- Customer -->
        <div class="sec">
            <div class="sec-label">Customer</div>
            <button class="customer-btn" id="customerBtn" onclick="openCustomerModal()">
                <i class="fas fa-user-circle" style="font-size:15px;color:var(--primary)"></i>
                <span id="customerBtnText">Memuat...</span>
                <i class="fas fa-times" id="customerClearBtn" style="display:none;margin-left:auto;color:var(--red)" onclick="clearCustomer(event)"></i>
            </button>
        </div>

        <!-- Discount + Coupon -->
        <div class="sec">
            <div class="sec-label">Diskon & Kupon</div>
            <div class="disc-row" style="margin-bottom:8px;display:none">
                <div style="flex:1">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:3px;font-weight:700">Diskon (Rp)</div>
                    <input type="number" id="discountAmt" class="disc-input" placeholder="0" min="0" oninput="syncTxDiscount('amt')">
                </div>
                <div style="flex:1">
                    <div style="font-size:10px;color:var(--text3);margin-bottom:3px;font-weight:700">Diskon (%)</div>
                    <input type="number" id="discountPct" class="disc-input" placeholder="0" min="0" max="100" oninput="syncTxDiscount('pct')">
                </div>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
                <span style="font-size:10px;color:var(--text3);font-weight:700;white-space:nowrap;flex-shrink:0">
                    <i class="fas fa-ticket-alt"></i> Kupon
                </span>
                <input type="text" id="couponInput" class="disc-input" placeholder="Kode kupon" maxlength="50"
                    style="flex:1;min-width:0;text-transform:uppercase;margin:0"
                    oninput="this.value=this.value.toUpperCase()">
                <button onclick="applyCoupon()" id="couponApplyBtn"
                    style="padding:0 10px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:11px;cursor:pointer;font-weight:700;white-space:nowrap;height:34px;flex-shrink:0">
                    Terapkan
                </button>
            </div>
            <div id="couponMessage" style="font-size:11px;margin-top:3px;display:none;padding-left:2px"></div>
        </div>

        <!-- Summary -->
        <div class="sec">
            <div class="sec-label">Ringkasan</div>
            <div class="summary-row"><span>Total Qty</span><span id="totalQtyDisplay">0</span></div>
            <div class="summary-row"><span>Subtotal</span><span id="subtotalDisplay">Rp 0</span></div>
            <div class="summary-row" style="color:var(--red)"><span>Diskon</span><span id="discountDisplay">− Rp 0</span></div>
            <div class="summary-row" id="couponDiscountRow" style="display:none">
                <span>
                    Kupon <span id="couponCodeLabel" style="font-family:monospace;font-weight:700"></span>
                    <span onclick="removeCoupon()" style="cursor:pointer;color:var(--red);font-size:10px;font-weight:700;margin-left:2px">✕</span>
                </span>
                <span id="couponDiscountDisplay" style="color:var(--red)">− Rp 0</span>
            </div>
            <div class="summary-row"><span>Pajak</span><span id="taxDisplay">Rp 0</span></div>
            <div class="summary-row" id="scRow" style="display:none;color:#E65100">
                <span id="scLabel">Service Charge</span><span id="scDisplay">Rp 0</span>
            </div>
            <div class="summary-row" id="pb1Row" style="display:none;color:#E65100">
                <span id="pb1Label">PB1</span><span id="pb1Display">Rp 0</span>
            </div>
            <div class="summary-row total"><span>TOTAL</span><span id="totalDisplay">Rp 0</span></div>
        </div>

        <!-- Payment Method -->
        <div class="sec">
            <div class="sec-label">Metode Bayar</div>
            <div class="payment-methods">
                @if(!empty($posPaymentMethods))
                    @foreach($posPaymentMethods as $i => $pm)
                    <button class="pay-btn {{ $i === 0 ? 'active' : '' }}"
                        data-method="{{ $pm['mode_of_payment'] }}"
                        data-cash-type="{{ $pm['is_cash'] ? '1' : '0' }}"
                        onclick="selectPayment(this)">
                        <span class="pay-icon">{{ $pm['is_cash'] ? '💵' : (($pm['type'] ?? '') === 'Bank' ? '🏦' : '💳') }}</span>
                        {{ $pm['mode_of_payment'] }}
                    </button>
                    @endforeach
                @else
                    <button class="pay-btn active" data-method="cash" data-cash-type="1" onclick="selectPayment(this)"><span class="pay-icon">💵</span>Tunai</button>
                    <button class="pay-btn" data-method="card" data-cash-type="0" onclick="selectPayment(this)"><span class="pay-icon">💳</span>Kartu</button>
                    <button class="pay-btn" data-method="transfer" data-cash-type="0" onclick="selectPayment(this)"><span class="pay-icon">🏦</span>Transfer</button>
                    <button class="pay-btn" data-method="qris" data-cash-type="0" onclick="selectPayment(this)"><span class="pay-icon">📱</span>QRIS</button>
                @endif
            </div>
        </div>

        <!-- Cash input -->
@php $defaultIsCash = empty($posPaymentMethods) ? true : (bool)($posPaymentMethods[0]['is_cash'] ?? false); @endphp
        <div class="sec" id="cashSection" @if(!$defaultIsCash)style="display:none"@endif>
            <div class="sec-label">Nominal Bayar</div>
            <input type="number" id="paidAmount" class="paid-input" placeholder="0" oninput="calcChange()">
            <div class="quick-amts" id="quickAmounts"></div>
            <div class="change-box">
                <span class="change-label"><i class="fas fa-coins"></i> Kembalian</span>
                <span class="change-value" id="changeDisplay">Rp 0</span>
            </div>
        </div>

        <!-- Checkout -->
        <div class="sec" style="border-bottom:none">
            <button class="btn-checkout" id="checkoutBtn" onclick="processCheckout()" disabled>
                <i class="fas fa-check-circle"></i>
                <span id="checkoutBtnText">Proses Pembayaran</span>
            </button>
        </div>

    </div>
</div>

<!-- Customer Modal -->
<div class="modal-overlay" id="customerModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fas fa-users" style="color:var(--primary)"></i> Pilih Customer</div>
            <button onclick="closeModal('customerModal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text3)">&times;</button>
        </div>
        <div class="modal-body">
            <input type="text" id="customerSearch" class="form-control" placeholder="Cari nama, telp, atau kode customer..." oninput="searchCustomers(this.value)">
            <div id="customerList" style="max-height:280px;overflow-y:auto;margin-top:4px"></div>
            <hr style="margin:14px 0;border:none;border-top:2px dashed var(--border)">
            <div style="font-size:13px;color:var(--text3);margin-bottom:8px;font-weight:800"><i class="fas fa-plus-circle" style="color:var(--green)"></i> Customer Baru</div>
            <input type="text" id="newCustName" class="form-control" placeholder="Nama customer">
            <input type="text" id="newCustPhone" class="form-control" placeholder="Nomor telepon (opsional)">
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeModal('customerModal')">Batal</button>
            <button class="btn btn-success" onclick="addNewCustomer()"><i class="fas fa-user-plus"></i> Tambah</button>
        </div>
    </div>
</div>

<!-- Item Note Modal -->
<div class="modal-overlay" id="itemNoteModal">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <div class="modal-title" style="font-size:16px"><i class="fas fa-sticky-note" style="color:var(--secondary)"></i> Catatan Item</div>
            <button onclick="closeModal('itemNoteModal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text3)">&times;</button>
        </div>
        <div class="modal-body">
            <div style="font-size:13px;font-weight:800;color:var(--text2);margin-bottom:12px" id="noteItemName"></div>
            <textarea id="noteText" class="form-control" rows="3" placeholder="Contoh: tidak pedas, tanpa bawang..."></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="clearItemNote()"><i class="fas fa-times"></i> Hapus</button>
            <button class="btn btn-primary" onclick="applyItemNote()"><i class="fas fa-check"></i> Simpan</button>
        </div>
    </div>
</div>

<!-- Item Discount Modal -->
<div class="modal-overlay" id="itemDiscountModal">
    <div class="modal" style="max-width:360px">
        <div class="modal-header">
            <div class="modal-title" style="font-size:15px"><i class="fas fa-tag" style="color:var(--red)"></i> Diskon Item</div>
            <button onclick="closeModal('itemDiscountModal')" style="background:none;border:none;cursor:pointer;font-size:22px;color:var(--text3)">&times;</button>
        </div>
        <div class="modal-body">
            <div style="font-size:13px;font-weight:800;color:var(--text2);margin-bottom:14px" id="discItemName"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:800;color:var(--text2);margin-bottom:6px">Diskon (Rp)</label>
                    <input type="number" id="discItemAmt" min="0" placeholder="0" oninput="syncDiscModal('amt')"
                        style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:10px;font-size:15px;font-weight:800;font-family:'Roboto Mono',monospace">
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:800;color:var(--text2);margin-bottom:6px">Diskon (%)</label>
                    <input type="number" id="discItemPct" min="0" max="100" placeholder="0" oninput="syncDiscModal('pct')"
                        style="width:100%;padding:10px 12px;border:2px solid var(--border);border-radius:10px;font-size:15px;font-weight:800;font-family:'Roboto Mono',monospace">
                </div>
            </div>
            <div id="discItemPreview" style="margin-top:14px;padding:10px 14px;background:var(--surface2);border-radius:10px;font-size:13px;display:none;font-weight:600">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="color:var(--text3)">Harga × Qty</span><span id="discItemGross"></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="color:var(--red)">Diskon</span><span id="discItemDiscVal" style="color:var(--red)"></span>
                </div>
                <div style="display:flex;justify-content:space-between;border-top:1px dashed var(--border);padding-top:6px">
                    <span style="font-weight:900">Subtotal</span><span id="discItemNet" style="font-weight:900;color:var(--green)"></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="clearItemDiscount()"><i class="fas fa-times"></i> Hapus</button>
            <button class="btn btn-primary" onclick="applyItemDiscount()"><i class="fas fa-check"></i> Terapkan</button>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--green)"><i class="fas fa-check-circle"></i> Transaksi Berhasil!</div>
        </div>
        <div class="modal-body">
            <div class="receipt" id="receiptContent"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeReceiptAndReset()"><i class="fas fa-times"></i> Tutup</button>
            <button class="btn btn-primary" onclick="printReceipt()"><i class="fas fa-print"></i> Cetak Struk</button>
            <button class="btn btn-success" onclick="closeReceiptAndReset()"><i class="fas fa-plus"></i> Pesanan Baru</button>
        </div>
    </div>
</div>

<div id="toasts"></div>

<script>
// ============================================================
// STATE
// ============================================================
let cart = [];
let selectedCustomer = null;
// Ikuti tombol pertama dari HPYERP (yang tampil aktif tanpa diklik). Nilai tetap
// 'cash' pernah terkirim apa adanya saat kasir tidak mengklik tombol tunai —
// invoice lalu gagal sync karena Mode of Payment "Cash" tidak ada di HPYERP.
let selectedPayment = @json($posPaymentMethods[0]['mode_of_payment'] ?? 'cash');
let selectedPaymentIsCash = @json($defaultIsCash);
let selectedOrderType = 'dine_in';
let selectedDeliveryPlatform = null;
let allProducts = [];
let currentCategoryFilter = null;
let lastReceipt = null;
let noteItemId = null;
let discItemId = null;
let appliedCoupon = null;

const csrf = document.querySelector('meta[name="csrf-token"]').content;
const defaultPosClass   = @json($posClass);
const walkinCustomerName = @json($walkinCustomerName);
const appBaseUrl        = @json(url('/'));
const erpBaseUrl        = @json($erpBaseUrl);
const deliveryPrices    = @json($deliveryPrices);
const dineInCharges     = @json($dineInCharges);

// ============================================================
// CLOCK
// ============================================================
function updateClock() { document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID'); }
setInterval(updateClock, 1000); updateClock();

// ============================================================
// ORDER TYPE + DELIVERY PLATFORM
// ============================================================
function selectOrderType(btn) {
    document.querySelectorAll('.order-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedOrderType = btn.dataset.type;
    document.getElementById('tableRow').style.display = selectedOrderType === 'dine_in' ? 'flex' : 'none';

    const sec = document.getElementById('deliverySection');
    if (selectedOrderType === 'delivery') {
        sec.classList.add('show');
    } else {
        sec.classList.remove('show');
        if (selectedDeliveryPlatform) {
            selectedDeliveryPlatform = null;
            document.querySelectorAll('.platform-btn').forEach(b => b.classList.remove('active'));
            applyDeliveryPrices();
            renderCart();
        }
    }
    recalculate();
}

function selectDeliveryPlatform(btn) {
    const platform = btn.dataset.platform;
    if (selectedDeliveryPlatform === platform) {
        btn.classList.remove('active');
        selectedDeliveryPlatform = null;
    } else {
        document.querySelectorAll('.platform-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedDeliveryPlatform = platform;
    }
    applyDeliveryPrices();
    renderCart();
}

function getDeliveryPrice(erpItemCode) {
    if (!selectedDeliveryPlatform || !erpItemCode) return null;
    const map = deliveryPrices[selectedDeliveryPlatform] ?? {};
    return map[erpItemCode] !== undefined ? map[erpItemCode] : null;
}

function applyDeliveryPrices() {
    cart.forEach(item => {
        const dp = getDeliveryPrice(item.erpItemCode);
        item.price = dp !== null ? dp : item.basePrice;
        item.discount = 0;
        item.discountPct = 0;
    });
}

// ============================================================
// LOAD PRODUCTS (once on init, stored in allProducts)
// ============================================================
async function loadProducts() {
    try {
        const resp = await fetch('{{ route("pos.search-products") }}', { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        allProducts = await resp.json();
        if (!Array.isArray(allProducts)) allProducts = [];
    } catch(e) { console.error('Gagal memuat produk', e); }
}

// ============================================================
// SEARCH DROPDOWN
// ============================================================
const searchInput = document.getElementById('searchInput');
const dropdown    = document.getElementById('searchDropdown');

function imgUrl(p) {
    if (!p.image) return null;
    if (p.image.startsWith('http')) return p.image;
    if (p.image.startsWith('/images/')) return appBaseUrl + p.image;
    return erpBaseUrl + p.image;
}

function openDropdown(products) {
    const cartQtyMap = {};
    cart.forEach(i => { cartQtyMap[i.id] = i.qty; });
    const effectivePrice = p => {
        const dp = getDeliveryPrice(p.erp_item_code);
        return dp !== null ? dp : p.price;
    };
    const outOfStock = p => p.track_stock && p.stock <= 0;

    if (!products || products.length === 0) {
        dropdown.innerHTML = '<div class="dd-empty">Menu tidak ditemukan</div>';
    } else {
        const sorted = [...products].sort((a,b) => (outOfStock(a)?1:0)-(outOfStock(b)?1:0));
        dropdown.innerHTML = sorted.map(p => {
            const oos    = outOfStock(p);
            const ep     = effectivePrice(p);
            const inCart = cartQtyMap[p.id] > 0;
            const url    = imgUrl(p);
            const stockInfo = p.track_stock
                ? (oos ? '<span class="badge-sm badge-out">Habis</span>'
                       : (p.is_low_stock ? `<span class="badge-sm badge-low">Stok: ${p.stock}</span>` : `Stok: ${p.stock}`))
                : '∞ Tersedia';
            return `
            <div class="dd-item ${oos?'oos':''}"
                data-id="${p.id}" data-name="${p.name.replace(/'/g,"&#39;")}"
                data-price="${ep}" data-base-price="${p.price}"
                data-sku="${p.sku}" data-stock="${p.stock}"
                data-unit="${p.unit}" data-tax="${p.tax_rate}"
                data-track="${p.track_stock?1:0}" data-erp-code="${p.erp_item_code||''}"
                onmousedown="addFromDropdown(this)">
                <div class="dd-img">
                    ${url ? `<img src="${url}" alt="" loading="lazy" onerror="this.style.display='none';this.nextSibling.style.display=''"><span style="display:none">🍽️</span>` : '🍽️'}
                </div>
                <div style="flex:1;min-width:0">
                    <div class="dd-name">${p.name}</div>
                    <div class="dd-meta">${stockInfo}</div>
                </div>
                <div class="dd-right">
                    <div class="dd-price">Rp ${fmt(ep)}</div>
                    ${inCart ? `<div class="dd-qty">×${cartQtyMap[p.id]} di keranjang</div>` : ''}
                </div>
            </div>`;
        }).join('');
    }
    dropdown.classList.add('show');
}

function closeDropdown() {
    dropdown.classList.remove('show');
}

function addFromDropdown(el) {
    addToCart(el);
    searchInput.value = '';
    closeDropdown();
}

let searchTimeout;
// Cari ke server (bukan filter dari daftar terbatas), agar semua produk
// ikut tercari — bukan hanya 50 pertama — dan urutan kata bebas.
async function remoteSearch(term) {
    try {
        const resp = await fetch('{{ route("pos.search-products") }}?q=' + encodeURIComponent(term),
            { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        const results = await resp.json();
        openDropdown(Array.isArray(results) ? results : []);
    } catch(e) { openDropdown([]); }
}

searchInput.addEventListener('input', function () {
    const term = this.value.trim();
    if (!term) { closeDropdown(); return; }
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => remoteSearch(term), 200);
});

searchInput.addEventListener('focus', function () {
    const term = this.value.trim();
    if (term) remoteSearch(term);
});

document.addEventListener('click', e => {
    if (!e.target.closest('.pos-top')) closeDropdown();
});

document.addEventListener('keydown', e => {
    if (e.key === 'F3') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
    if (e.key === 'Escape') {
        closeDropdown();
        ['customerModal','receiptModal','itemNoteModal','itemDiscountModal'].forEach(id => closeModal(id));
    }
});

// ============================================================
// CART
// ============================================================
function addToCart(el) {
    const id = parseInt(el.dataset.id);
    const track = el.dataset.track === '1';
    const stock = parseInt(el.dataset.stock);
    if (track && stock <= 0) toast('⚠ Stok habis! Penjualan tetap diproses.', 'warn');

    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty++;
        const dp = getDeliveryPrice(existing.erpItemCode);
        if (dp !== null) existing.price = dp;
    } else {
        const basePrice = parseFloat(el.dataset.basePrice || el.dataset.price);
        if (basePrice === 0) toast('⚠ Harga produk ini Rp 0, harap periksa kembali.', 'warn');
        const erpCode = el.dataset.erpCode || '';
        const dp = getDeliveryPrice(erpCode);
        cart.push({
            id, name: el.dataset.name,
            price: dp !== null ? dp : basePrice,
            basePrice, erpItemCode: erpCode,
            sku: el.dataset.sku, stock, unit: el.dataset.unit,
            tax: parseFloat(el.dataset.tax), track,
            qty: 1, discount: 0, discountPct: 0, note: ''
        });
    }
    renderCart();
}

function removeFromCart(id) {
    cart = cart.filter(i => i.id !== id);
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const newQty = item.qty + delta;
    if (newQty <= 0) { removeFromCart(id); return; }
    if (item.track && newQty > item.stock) toast('⚠ Melebihi stok tersedia!', 'warn');
    item.qty = newQty;
    renderCart();
}

function setQty(id, val) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const qty = parseInt(val) || 1;
    if (item.track && qty > item.stock) toast('⚠ Melebihi stok tersedia!', 'warn');
    item.qty = Math.max(1, qty);
    renderCart();
}

function setPrice(id, val) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const price = parseFloat(val) || 0;
    if (price < 0) return;
    item.price = price;
    item.basePrice = price;
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    cart = [];
    document.getElementById('discountAmt').value = '';
    document.getElementById('discountPct').value = '';
    removeCoupon();
    renderCart();
}

function renderCart() {
    const tbody  = document.getElementById('cartBody');
    const empty  = document.getElementById('cartEmpty');
    const table  = document.getElementById('cartTable');
    const countEl = document.getElementById('orderCount');

    const totalItems = cart.reduce((s, i) => s + i.qty, 0);
    countEl.textContent = totalItems + ' item';
    const qtyEl = document.getElementById('totalQtyDisplay');
    if (qtyEl) qtyEl.textContent = totalItems;

    if (cart.length === 0) {
        empty.style.display = 'flex';
        table.style.display  = 'none';
        tbody.innerHTML = '';
        document.getElementById('checkoutBtn').disabled = true;
        recalculate();
            return;
    }

    empty.style.display = 'none';
    table.style.display  = 'table';
    document.getElementById('checkoutBtn').disabled = false;

    tbody.innerHTML = cart.map((item, idx) => {
        const gross    = item.price * item.qty;
        const subtotal = Math.max(0, gross - (item.discount || 0));
        const hasNote  = item.note && item.note.trim().length > 0;
        const hasDisc  = item.discount > 0;

        return `
        <tr>
            <td class="col-num"><span class="row-num">${idx + 1}</span></td>
            <td>
                <div class="row-name">${item.name}</div>
                ${hasNote ? `<div class="row-note"><i class="fas fa-sticky-note" style="color:var(--secondary);font-size:10px"></i> ${item.note}</div>` : ''}
                ${hasDisc ? `<div style="font-size:10px;font-weight:800;color:var(--red);margin-top:2px">− Rp ${fmt(item.discount)}</div>` : ''}
            </td>
            <td class="col-qty">
                <div class="qty-wrap">
                    <button class="qty-btn" onclick="updateQty(${item.id},-1)">−</button>
                    <input class="qty-input" type="number" value="${item.qty}" min="1" onchange="setQty(${item.id},this.value)">
                    <button class="qty-btn" onclick="updateQty(${item.id},1)">+</button>
                </div>
            </td>
            <td class="col-price">
                <div style="position:relative;display:inline-flex;align-items:center">
                    <span style="position:absolute;left:8px;font-size:10px;color:var(--text3);pointer-events:none">Rp</span>
                    <input type="number" min="0" value="${item.price}" onchange="setPrice(${item.id},this.value)" onclick="this.select()"
                        style="width:112px;padding:5px 8px 5px 26px;border:2px solid var(--primary);border-radius:8px;font-size:13px;font-weight:800;font-family:'Roboto Mono',monospace;color:var(--primary)">
                </div>
            </td>
            <td class="col-disc">
                <button onclick="openItemDiscount(${item.id})"
                    style="padding:4px 9px;border-radius:8px;font-size:11px;font-weight:800;border:1px solid ${hasDisc?'var(--red)':'var(--border)'};background:${hasDisc?'var(--red-light)':'var(--surface2)'};color:${hasDisc?'var(--red)':'var(--text2)'};cursor:pointer;white-space:nowrap;font-family:'Roboto',sans-serif">
                    ${hasDisc ? '−Rp '+fmt(item.discount) : '<i class="fas fa-tag"></i> Disc'}
                </button>
            </td>
            <td class="col-subtotal">
                ${hasDisc ? `<div style="font-size:10px;text-decoration:line-through;color:var(--text3)">Rp ${fmt(gross)}</div>` : ''}
                <div style="font-size:14px;font-weight:900;color:${hasDisc?'var(--red)':'var(--primary)'}">Rp ${fmt(subtotal)}</div>
            </td>
            <td class="col-actions" style="white-space:nowrap">
                <button onclick="openItemNote(${item.id})" class="action-btn ${hasNote?'has-note':''}" title="Catatan">
                    <i class="fas fa-sticky-note"></i>
                </button>
                <button onclick="removeFromCart(${item.id})" class="action-btn del-btn" title="Hapus" style="margin-left:4px">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    recalculate();
}

// ============================================================
// ITEM NOTE
// ============================================================
function openItemNote(id) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    noteItemId = id;
    document.getElementById('noteItemName').textContent = item.name;
    document.getElementById('noteText').value = item.note || '';
    document.getElementById('itemNoteModal').classList.add('show');
    setTimeout(() => document.getElementById('noteText').focus(), 100);
}
function applyItemNote() {
    const item = cart.find(i => i.id === noteItemId);
    if (item) item.note = document.getElementById('noteText').value.trim();
    closeModal('itemNoteModal');
    renderCart();
}
function clearItemNote() {
    const item = cart.find(i => i.id === noteItemId);
    if (item) item.note = '';
    closeModal('itemNoteModal');
    renderCart();
}

// ============================================================
// ITEM DISCOUNT
// ============================================================
function openItemDiscount(id) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    discItemId = id;
    document.getElementById('discItemName').textContent = item.name;
    document.getElementById('discItemAmt').value = item.discount > 0 ? item.discount : '';
    document.getElementById('discItemPct').value = item.discountPct > 0 ? item.discountPct : '';
    updateDiscPreview(item);
    document.getElementById('itemDiscountModal').classList.add('show');
    setTimeout(() => document.getElementById('discItemAmt').focus(), 100);
}
function syncDiscModal(mode) {
    const item = cart.find(i => i.id === discItemId);
    if (!item) return;
    const gross = item.price * item.qty;
    if (mode === 'amt') {
        const amt = parseFloat(document.getElementById('discItemAmt').value) || 0;
        document.getElementById('discItemPct').value = gross > 0 ? parseFloat(((amt/gross)*100).toFixed(2)) : '';
    } else {
        const pct = parseFloat(document.getElementById('discItemPct').value) || 0;
        document.getElementById('discItemAmt').value = parseFloat(((pct/100)*gross).toFixed(0));
    }
    updateDiscPreview(item);
}
function updateDiscPreview(item) {
    const gross = item.price * item.qty;
    const amt   = parseFloat(document.getElementById('discItemAmt').value) || 0;
    const net   = Math.max(0, gross - amt);
    const prev  = document.getElementById('discItemPreview');
    if (amt > 0) {
        prev.style.display = 'block';
        document.getElementById('discItemGross').textContent   = 'Rp ' + fmt(gross);
        document.getElementById('discItemDiscVal').textContent = '− Rp ' + fmt(amt);
        document.getElementById('discItemNet').textContent     = 'Rp ' + fmt(net);
    } else {
        prev.style.display = 'none';
    }
}
function applyItemDiscount() {
    const item  = cart.find(i => i.id === discItemId);
    if (!item) return;
    const gross = item.price * item.qty;
    const amt   = parseFloat(document.getElementById('discItemAmt').value) || 0;
    const pct   = parseFloat(document.getElementById('discItemPct').value) || 0;
    if (amt > gross) { toast('Diskon melebihi harga item!', 'err'); return; }
    item.discount = amt;
    item.discountPct = pct;
    closeModal('itemDiscountModal');
    renderCart();
    if (amt > 0) toast('Diskon item diterapkan', 'ok');
}
function clearItemDiscount() {
    const item = cart.find(i => i.id === discItemId);
    if (item) { item.discount = 0; item.discountPct = 0; }
    closeModal('itemDiscountModal');
    renderCart();
}

// ============================================================
// TOTALS
// ============================================================
function cartNetSubtotal() {
    return cart.reduce((s, i) => s + Math.max(0, (i.price * i.qty) - (i.discount || 0)), 0);
}
function syncTxDiscount(mode) {
    const subtotal = cartNetSubtotal();
    if (mode === 'pct') {
        const pct = parseFloat(document.getElementById('discountPct').value) || 0;
        const amt = subtotal * (pct / 100);
        document.getElementById('discountAmt').value = amt > 0 ? Math.round(amt) : '';
    } else {
        const amt = parseFloat(document.getElementById('discountAmt').value) || 0;
        const pct = subtotal > 0 ? (amt / subtotal) * 100 : 0;
        document.getElementById('discountPct').value = pct > 0 ? parseFloat(pct.toFixed(2)) : '';
    }
    recalculate();
}

// ============================================================
// COUPON
// ============================================================
async function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim().toUpperCase();
    if (!code) { showCouponMsg('Masukkan kode kupon', 'err'); return; }
    const subtotal = cartNetSubtotal();
    if (subtotal === 0) { showCouponMsg('Tambahkan item terlebih dahulu', 'err'); return; }
    const btn = document.getElementById('couponApplyBtn');
    btn.disabled = true; btn.textContent = '...';
    try {
        const resp = await fetch('{{ route("pos.validate-coupon") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify({ code, subtotal })
        });
        const data = await resp.json();
        if (data.valid) {
            appliedCoupon = data.coupon;
            document.getElementById('couponInput').disabled = true;
            btn.style.display = 'none';
            showCouponMsg(data.message, 'ok');
            recalculate();
        } else {
            showCouponMsg(data.message, 'err');
        }
    } catch(e) {
        showCouponMsg('Gagal menghubungi server', 'err');
    } finally {
        btn.disabled = false; btn.textContent = 'Terapkan';
    }
}
function removeCoupon() {
    appliedCoupon = null;
    const inp = document.getElementById('couponInput');
    inp.value = ''; inp.disabled = false;
    document.getElementById('couponApplyBtn').style.display = '';
    document.getElementById('couponMessage').style.display = 'none';
    document.getElementById('couponDiscountRow').style.display = 'none';
    recalculate();
}
function showCouponMsg(msg, type) {
    const el = document.getElementById('couponMessage');
    el.textContent = msg;
    el.style.color = type === 'ok' ? 'var(--green)' : 'var(--red)';
    el.style.display = '';
}

function recalculate() {
    const subtotal = cartNetSubtotal();
    const tax      = cart.reduce((s, i) => s + (Math.max(0, (i.price*i.qty)-(i.discount||0)) * (i.tax/100)), 0);
    let discAmt    = parseFloat(document.getElementById('discountAmt').value) || 0;
    const discPct  = parseFloat(document.getElementById('discountPct').value) || 0;
    if (discPct > 0) discAmt = subtotal * (discPct / 100);

    let couponDiscount = 0;
    if (appliedCoupon) {
        couponDiscount = appliedCoupon.discount_type === 'percent'
            ? Math.round(subtotal * appliedCoupon.discount_value / 100)
            : Math.min(appliedCoupon.discount_value, subtotal);
        appliedCoupon.calculated_discount = couponDiscount;
        document.getElementById('couponDiscountRow').style.display = '';
        document.getElementById('couponCodeLabel').textContent = appliedCoupon.code;
        document.getElementById('couponDiscountDisplay').textContent = '− Rp ' + fmt(couponDiscount);
    } else {
        document.getElementById('couponDiscountRow').style.display = 'none';
    }

    const base = subtotal - discAmt - couponDiscount;
    let scAmt = 0, pb1Amt = 0;
    if (selectedOrderType === 'dine_in') {
        if (dineInCharges.service_charge_enabled && dineInCharges.service_charge_pct > 0)
            scAmt = Math.round(base * dineInCharges.service_charge_pct / 100);
        if (dineInCharges.pb1_enabled && dineInCharges.pb1_pct > 0)
            pb1Amt = Math.round((base + scAmt) * dineInCharges.pb1_pct / 100);
    }
    const total = base + tax + scAmt + pb1Amt;

    document.getElementById('subtotalDisplay').textContent  = 'Rp ' + fmt(subtotal);
    document.getElementById('discountDisplay').textContent  = '− Rp ' + fmt(discAmt);
    document.getElementById('taxDisplay').textContent       = 'Rp ' + fmt(tax);
    document.getElementById('totalDisplay').textContent     = 'Rp ' + fmt(total);
    document.getElementById('totalDisplayTop').textContent  = 'Rp ' + fmt(total);
    document.getElementById('topbarTotalDisplay').textContent = 'Rp ' + fmt(total);

    const scRow = document.getElementById('scRow');
    if (scAmt > 0) {
        scRow.style.display = '';
        document.getElementById('scLabel').textContent   = `Service Charge (${dineInCharges.service_charge_pct}%)`;
        document.getElementById('scDisplay').textContent = 'Rp ' + fmt(scAmt);
    } else { scRow.style.display = 'none'; }

    const pb1Row = document.getElementById('pb1Row');
    if (pb1Amt > 0) {
        pb1Row.style.display = '';
        document.getElementById('pb1Label').textContent   = `PB1 (${dineInCharges.pb1_pct}%)`;
        document.getElementById('pb1Display').textContent = 'Rp ' + fmt(pb1Amt);
    } else { pb1Row.style.display = 'none'; }

    if (selectedPaymentIsCash) {
        const amounts = [total, Math.ceil(total/10000)*10000, Math.ceil(total/50000)*50000, Math.ceil(total/100000)*100000];
        const unique  = [...new Set(amounts)].slice(0, 4);
        document.getElementById('quickAmounts').innerHTML = unique.map(a =>
            `<button class="quick-amt-btn" onclick="setPaid(${a})">Rp ${fmt(a)}</button>`
        ).join('');
    }
    calcChange();
}

function calcChange() {
    const totalText = document.getElementById('totalDisplay').textContent;
    const total = parseFloat(totalText.replace(/[^0-9]/g,''));
    const paid  = parseFloat(document.getElementById('paidAmount').value) || 0;
    const change = paid - total;
    const el = document.getElementById('changeDisplay');
    el.textContent = 'Rp ' + fmt(Math.max(0, change));
    el.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
    const box = document.querySelector('.change-box');
    if (box) box.style.background = change >= 0 ? 'var(--green-light)' : 'var(--red-light)';
    const lbl = document.querySelector('.change-label');
    if (lbl) lbl.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
}
function setPaid(amount) {
    document.getElementById('paidAmount').value = amount;
    calcChange();
}

function selectPayment(btn) {
    document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedPayment = btn.dataset.method;
    selectedPaymentIsCash = btn.dataset.cashType === '1';
    document.getElementById('cashSection').style.display = selectedPaymentIsCash ? 'block' : 'none';
}

// ============================================================
// CUSTOMER
// ============================================================
const allCustomers = @json($customers);

function renderCustomerBtn() {
    const btn    = document.getElementById('customerBtn');
    const textEl = document.getElementById('customerBtnText');
    const clearEl = document.getElementById('customerClearBtn');
    if (!selectedCustomer || selectedCustomer.id === null) {
        btn.classList.remove('has-customer');
        textEl.textContent = '🚶 ' + walkinCustomerName;
        clearEl.style.display = 'none';
    } else {
        btn.classList.add('has-customer');
        textEl.textContent = '👤 ' + selectedCustomer.name + ' (' + selectedCustomer.code + ')';
        clearEl.style.display = 'block';
    }
}
function setWalkin() {
    selectedCustomer = { id: null, name: walkinCustomerName };
    renderCustomerBtn();
}
function openCustomerModal() {
    document.getElementById('customerModal').classList.add('show');
    document.getElementById('customerSearch').value = '';
    renderCustomerList(allCustomers);
    setTimeout(() => document.getElementById('customerSearch').focus(), 100);
}
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
function renderCustomerList(list) {
    const el = document.getElementById('customerList');
    const walkinRow = `
        <div onclick="selectWalkin()"
            style="padding:10px 12px;border-radius:10px;cursor:pointer;display:flex;align-items:center;gap:10px;background:${(!selectedCustomer||selectedCustomer.id===null)?'var(--primary-light)':''};transition:background .15s"
            onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background='${(!selectedCustomer||selectedCustomer.id===null)?'var(--primary-light)':''}'">
            <span style="font-size:20px">🚶</span>
            <div>
                <div style="font-weight:800;font-size:14px;color:var(--primary)">${walkinCustomerName}</div>
                <div style="font-size:12px;color:var(--text3)">Transaksi tanpa pelanggan terdaftar</div>
            </div>
            ${(!selectedCustomer||selectedCustomer.id===null)?'<i class="fas fa-check" style="margin-left:auto;color:var(--primary)"></i>':''}
        </div>`;
    if (list.length === 0) {
        el.innerHTML = walkinRow + '<div style="padding:20px;text-align:center;color:var(--text3);font-size:14px;font-weight:700">Tidak ditemukan</div>';
        return;
    }
    el.innerHTML = walkinRow + list.slice(0, 8).map(c => `
        <div onclick="selectCustomer(${c.id},'${c.name.replace(/'/g,"\\'")}','${c.code}')"
            style="padding:10px 12px;border-radius:10px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:background .15s"
            onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
            <div>
                <div style="font-weight:800;font-size:14px">${c.name}</div>
                <div style="font-size:12px;color:var(--text3)">${c.code} ${c.phone?'· '+c.phone:''}</div>
            </div>
            <div style="font-size:12px;color:var(--primary);font-weight:800">${c.loyalty_points > 0 ? '⭐ '+c.loyalty_points : ''}</div>
        </div>
    `).join('');
}
function searchCustomers(q) {
    const filtered = allCustomers.filter(c =>
        c.name.toLowerCase().includes(q.toLowerCase()) ||
        (c.phone && c.phone.includes(q)) ||
        c.code.toLowerCase().includes(q.toLowerCase())
    );
    renderCustomerList(filtered);
}
function selectWalkin() { setWalkin(); closeModal('customerModal'); }
function selectCustomer(id, name, code) {
    selectedCustomer = { id, name, code };
    renderCustomerBtn();
    closeModal('customerModal');
}
function clearCustomer(e) { if (e) e.stopPropagation(); setWalkin(); }

async function addNewCustomer() {
    const name = document.getElementById('newCustName').value.trim();
    if (!name) { toast('Nama customer wajib diisi!', 'err'); return; }
    const phone = document.getElementById('newCustPhone').value.trim();
    const resp = await fetch('{{ route("customers.store") }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body: JSON.stringify({ name, phone })
    });
    const data = await resp.json();
    if (data.success) {
        allCustomers.unshift(data.customer);
        selectCustomer(data.customer.id, data.customer.name, data.customer.code);
        toast('Customer berhasil ditambahkan!', 'ok');
    } else {
        toast('Gagal menambah customer', 'err');
    }
}

// ============================================================
// CHECKOUT
// ============================================================
// Kunci idempoten: satu nilai per isi keranjang, dipakai ulang oleh tiap percobaan
// kirim, baru diganti setelah checkout berhasil. Mencegah klik dobel / kirim ulang
// menjadi dua transaksi. Lihat PosController::checkout().
let checkoutKey = null;

function newCheckoutKey() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'ck-' + Date.now() + '-' + Math.random().toString(16).slice(2);
}

async function processCheckout() {
    if (cart.length === 0) return;
    if (selectedOrderType === 'delivery' && !selectedDeliveryPlatform) { toast('Pilih platform delivery (GoFood/GrabFood/ShopeeFood) terlebih dahulu!', 'err'); return; }
    if (!selectedCustomer) { toast('Pilih customer terlebih dahulu!', 'err'); openCustomerModal(); return; }
    const totalText = document.getElementById('totalDisplay').textContent;
    const total = parseFloat(totalText.replace(/[^0-9]/g,''));
    const paid  = selectedPaymentIsCash ? parseFloat(document.getElementById('paidAmount').value) || 0 : total;
    if (selectedPaymentIsCash && paid < total) { toast('Nominal bayar kurang!', 'err'); return; }

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    document.getElementById('checkoutBtnText').innerHTML = '<span class="spinner"></span> Memproses...';

    const discAmt = parseFloat(document.getElementById('discountAmt').value) || 0;
    const discPct = parseFloat(document.getElementById('discountPct').value) || 0;
    const tableNumber = document.getElementById('tableNumber').value.trim();

    if (!checkoutKey) checkoutKey = newCheckoutKey();

    const payload = {
        idempotency_key: checkoutKey,
        items: cart.map(i => ({ product_id: i.id, quantity: i.qty, price: i.price, discount_amount: i.discount, note: i.note || '' })),
        customer_id: selectedCustomer?.id || null,
        payment_method: selectedPayment,
        paid_amount: paid,
        discount_amount: discAmt,
        discount_percent: discPct,
        coupon_code: appliedCoupon?.code || null,
        pos_class: defaultPosClass || null,
        order_type: selectedOrderType,
        table_number: tableNumber || null,
        delivery_platform: selectedOrderType === 'delivery' ? selectedDeliveryPlatform : null,
    };

    try {
        const resp = await fetch('{{ route("pos.checkout") }}', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (data.success) {
            checkoutKey = null;
            lastReceipt = data.transaction;
            showReceipt(data.transaction);
            toast(data.duplicate
                ? 'Pesanan ini sudah tersimpan: ' + data.invoice_no
                : 'Pesanan berhasil: ' + data.invoice_no, 'ok');
        } else {
            toast('Gagal: ' + (data.error || 'Unknown error'), 'err');
        }
    } catch(e) {
        toast('Error koneksi: ' + e.message, 'err');
    } finally {
        btn.disabled = false;
        document.getElementById('checkoutBtnText').innerHTML = '<i class="fas fa-check-circle"></i> Proses Pembayaran';
    }
}

// ============================================================
// RECEIPT
// ============================================================
function showReceipt(tx) {
    const orderTypeLabel = { dine_in: 'Dine In', take_away: 'Take Away', delivery: 'Delivery' };
    const platformLabel  = { gofood: 'GoFood', grabfood: 'GrabFood', shopeefood: 'ShopeeFood' };
    const tableNumber = document.getElementById('tableNumber').value.trim();
    const items = tx.items.map(i =>
        `<div class="receipt-row"><span>${i.product_name} x${i.quantity}</span><span>Rp ${fmt(i.subtotal)}</span></div>`
    ).join('');
    const change = parseFloat(tx.paid_amount) - parseFloat(tx.total);
    document.getElementById('receiptContent').innerHTML = `
        <div class="receipt-header">
            <div class="receipt-title">{{ $storeSettings['store_name'] }}</div>
            @if($storeSettings['store_tagline'])
            <div style="font-size:10px;margin-top:2px">{{ $storeSettings['store_tagline'] }}</div>
            @endif
            @if($storeSettings['store_address'])
            <div style="font-size:10px">{{ $storeSettings['store_address'] }}</div>
            @endif
            @if($storeSettings['store_phone'])
            <div style="font-size:10px">Telp: {{ $storeSettings['store_phone'] }}</div>
            @endif
            <div style="font-size:11px;margin-top:4px">${new Date().toLocaleString('id-ID')}</div>
        </div>
        <hr class="receipt-divider">
        <div class="receipt-row"><span>No. Invoice</span><span><strong>${tx.invoice_no}</strong></span></div>
        <div class="receipt-row"><span>Kasir</span><span>${'{{ auth()->user()->name }}'}</span></div>
        <div class="receipt-row"><span>Tipe</span><span>${orderTypeLabel[selectedOrderType]||selectedOrderType}${selectedDeliveryPlatform?' via '+platformLabel[selectedDeliveryPlatform]:''}${tableNumber?' — Meja '+tableNumber:''}</span></div>
        ${tx.customer ? `<div class="receipt-row"><span>Customer</span><span>${tx.customer.name}</span></div>` : ''}
        <hr class="receipt-divider">
        ${items}
        <hr class="receipt-divider">
        <div class="receipt-row"><span>Subtotal</span><span>Rp ${fmt(tx.subtotal)}</span></div>
        ${parseFloat(tx.discount_amount) > 0 ? `<div class="receipt-row"><span>Diskon</span><span>− Rp ${fmt(tx.discount_amount)}</span></div>` : ''}
        ${tx.coupon_code && parseFloat(tx.coupon_discount||0) > 0 ? `<div class="receipt-row" style="color:var(--green)"><span>Kupon (${tx.coupon_code})</span><span>− Rp ${fmt(tx.coupon_discount)}</span></div>` : ''}
        ${parseFloat(tx.tax_amount) > 0 ? `<div class="receipt-row"><span>Pajak</span><span>Rp ${fmt(tx.tax_amount)}</span></div>` : ''}
        ${parseFloat(tx.service_charge_amount||0) > 0 ? `<div class="receipt-row" style="color:#E65100"><span>Service Charge (${tx.service_charge_pct}%)</span><span>Rp ${fmt(tx.service_charge_amount)}</span></div>` : ''}
        ${parseFloat(tx.pb1_amount||0) > 0 ? `<div class="receipt-row" style="color:#E65100"><span>PB1 (${tx.pb1_pct}%)</span><span>Rp ${fmt(tx.pb1_amount)}</span></div>` : ''}
        <hr class="receipt-divider">
        <div class="receipt-row receipt-total"><span>TOTAL</span><span>Rp ${fmt(tx.total)}</span></div>
        <div class="receipt-row"><span>Bayar (${tx.payment_method.toUpperCase()})</span><span>Rp ${fmt(tx.paid_amount)}</span></div>
        ${selectedPaymentIsCash && change > 0 ? `<div class="receipt-row"><span>Kembalian</span><span>Rp ${fmt(change)}</span></div>` : ''}
        <hr class="receipt-divider">
        <div style="text-align:center;font-size:11px;margin-top:8px">{{ $storeSettings['receipt_footer'] }}</div>
    `;
    document.getElementById('receiptModal').classList.add('show');
}

function printReceipt() {
    if (!lastReceipt) return;
    window.open(`{{ url('pos/print') }}/${lastReceipt.id}`, '_blank', 'width=400,height=600');
}

function closeReceiptAndReset() {
    closeModal('receiptModal');
    clearCart();
    setWalkin();
    document.getElementById('paidAmount').value = '';
    document.getElementById('discountAmt').value = '';
    document.getElementById('discountPct').value = '';
    selectedDeliveryPlatform = null;
    document.querySelectorAll('.platform-btn').forEach(b => b.classList.remove('active'));
}

// ============================================================
// UTILS
// ============================================================
function fmt(n) { return parseFloat(n||0).toLocaleString('id-ID',{minimumFractionDigits:0}); }

function toast(msg, type='ok') {
    const c = document.getElementById('toasts');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<i class="fas fa-${type==='ok'?'check-circle':'exclamation-circle'}"></i> ${msg}`;
    c.appendChild(t);
    setTimeout(() => { t.style.transition='.3s'; t.style.opacity='0'; setTimeout(()=>t.remove(), 300); }, 3000);
}

// Init
setWalkin();
renderCart();
loadProducts();
</script>

@include('pos._hold-orders')
@if(\App\Models\PosShift::featureEnabled())
@include('pos._shift')
@endif
</body>
</html>
