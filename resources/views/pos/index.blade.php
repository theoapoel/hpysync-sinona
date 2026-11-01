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
            --dark-surface: #303134;
            --shadow-sm: 0 1px 3px rgba(60,64,67,.15),0 1px 2px rgba(60,64,67,.1);
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

        /* ── TOP BAR ── */
        .pos-topbar {
            height: 52px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 16px;
            flex-shrink: 0;
        }
        .pos-topbar-brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none;
        }
        .pos-topbar-brand img { height: 34px; }
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

        /* ── MAIN LAYOUT ── */
        .pos-layout { display: flex; flex: 1; overflow: hidden; }

        /* ── LEFT: Menu Panel ── */
        .pos-products {
            flex: 1; display: flex; flex-direction: column;
            overflow: hidden; background: var(--bg);
        }

        /* Search */
        .pos-search-bar {
            padding: 12px 16px; background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex; gap: 10px; align-items: center;
        }
        .search-input-wrap { flex: 1; position: relative; }
        .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: 13px; }
        .search-input {
            width: 100%; padding: 10px 14px 10px 40px;
            border: 2px solid var(--border); border-radius: 24px;
            font-size: 14px; font-weight: 600; font-family: 'Roboto', sans-serif;
            background: var(--bg); color: var(--text); transition: all .2s;
        }
        .search-input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 3px rgba(230,92,0,.12); }

        /* Categories */
        .categories-wrap {
            position: relative; background: var(--surface);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0; display: flex; align-items: center;
        }
        .categories-bar { display: flex; gap: 6px; padding: 10px 10px; overflow-x: auto; flex: 1; scroll-behavior: smooth; }
        .categories-bar::-webkit-scrollbar { display: none; }
        .cat-arrow {
            flex-shrink: 0; width: 30px; height: 100%;
            display: flex; align-items: center; justify-content: center;
            background: var(--surface); border: none; cursor: pointer;
            color: var(--text3); font-size: 13px; transition: all .2s;
        }
        .cat-arrow:hover { color: var(--primary); }
        .cat-arrow.hidden { opacity: 0; pointer-events: none; }
        .cat-btn {
            padding: 6px 16px; border-radius: 20px;
            font-size: 13px; font-weight: 600; border: 1px solid var(--border);
            cursor: pointer; white-space: nowrap; transition: all .2s;
            font-family: 'Google Sans', sans-serif;
            background: var(--surface2); color: var(--text2);
        }
        .cat-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .cat-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* Product Grid */
        .products-grid {
            flex: 1; overflow-y: auto; padding: 12px;
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 10px; align-content: start;
        }
        .products-grid.text-mode { grid-template-columns: repeat(2, 1fr); gap: 6px; padding: 10px; }

        /* IMAGE MODE card */
        .product-card {
            background: var(--surface); border-radius: var(--radius);
            border: 2px solid var(--border); cursor: pointer;
            transition: all .2s; overflow: hidden; position: relative;
            display: flex; flex-direction: column;
        }
        .product-card:hover { box-shadow: var(--shadow); border-color: var(--primary); transform: translateY(-2px); }
        .product-card.out-of-stock { opacity: .45; cursor: not-allowed; }
        .product-card.out-of-stock:hover { transform: none; box-shadow: none; border-color: var(--border); }

        .product-img {
            width: 100%; aspect-ratio: 4/3;
            background: var(--surface2); position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; font-size: 40px;
        }
        .product-img img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .product-cat-dot { position: absolute; top: 8px; right: 8px; background: var(--primary); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
        .low-stock-badge { position: absolute; top: 8px; left: 8px; background: #FF6F00; color: #fff; font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 8px; }
        .out-badge { position: absolute; top: 8px; left: 8px; background: var(--red); color: #fff; font-size: 9px; font-weight: 800; padding: 2px 6px; border-radius: 8px; }

        .product-info { padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 4px; }
        .product-name { font-size: 13px; font-weight: 800; line-height: 1.3; color: var(--text); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .product-price { font-size: 15px; font-weight: 900; color: var(--primary); }
        .product-stock { font-size: 11px; color: var(--text3); font-weight: 600; }
        .stock-low { color: #E65100; }

        /* TEXT MODE card */
        .product-card.text-card {
            flex-direction: row; align-items: center;
            padding: 12px 14px; gap: 10px; min-height: unset;
        }

        /* ── RIGHT: Order Panel ── */
        .pos-cart {
            width: 480px; background: var(--surface);
            border-left: 2px solid var(--border);
            display: flex; flex-direction: column; flex-shrink: 0;
        }

        /* Order type + table */
        .order-type-bar {
            padding: 10px 14px; border-bottom: 1px solid var(--border);
            display: flex; gap: 8px; align-items: center; flex-shrink: 0; flex-wrap: wrap;
        }
        .order-type-btn {
            flex: 1; padding: 8px 6px; border-radius: 10px;
            border: 2px solid var(--border); background: var(--surface2);
            font-size: 12px; font-weight: 800; color: var(--text2);
            cursor: pointer; text-align: center; transition: all .2s;
            font-family: 'Roboto', sans-serif;
        }
        .order-type-btn.active {
            background: var(--primary); color: #fff; border-color: var(--primary);
        }
        .table-input-wrap { display: flex; align-items: center; gap: 6px; margin-left: 4px; }
        .table-input {
            width: 80px; padding: 7px 10px; border: 2px solid var(--border);
            border-radius: 10px; font-size: 13px; font-weight: 800;
            text-align: center; font-family: 'Roboto', sans-serif;
            color: var(--text); background: var(--bg); transition: all .2s;
        }
        .table-input:focus { outline: none; border-color: var(--primary); }
        .table-label { font-size: 11px; font-weight: 700; color: var(--text3); white-space: nowrap; }

        /* Delivery platform picker */
        .delivery-platform-bar {
            padding: 8px 14px 10px; border-bottom: 1px solid var(--border);
            display: none; gap: 8px; align-items: center; flex-shrink: 0; background: #FFF8F0;
        }
        .delivery-platform-bar.show { display: flex; }
        .delivery-platform-label { font-size: 11px; font-weight: 800; color: var(--text3); white-space: nowrap; }
        .platform-btn {
            flex: 1; padding: 8px 6px; border-radius: 10px; border: 2px solid var(--border);
            cursor: pointer; text-align: center; transition: all .2s;
            font-size: 12px; font-weight: 800; font-family: 'Roboto', sans-serif;
            display: flex; flex-direction: column; align-items: center; gap: 3px; background: var(--surface);
        }
        .platform-btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .platform-btn.gofood       { color: #00AE11; }
        .platform-btn.gofood.active{ background: #00AE11; color: #fff; border-color: #00AE11; }
        .platform-btn.grabfood       { color: #00B14F; }
        .platform-btn.grabfood.active{ background: #00B14F; color: #fff; border-color: #00B14F; }
        .platform-btn.shopeefood       { color: #EE4D2D; }
        .platform-btn.shopeefood.active{ background: #EE4D2D; color: #fff; border-color: #EE4D2D; }
        .platform-logo { font-size: 20px; line-height: 1; }
        .delivery-price-badge {
            display: inline-block; font-size: 10px; font-weight: 800; padding: 1px 6px;
            border-radius: 8px; background: #FFF3E0; color: #E65100; margin-left: 4px;
        }

        /* Customer */
        .cart-customer { padding: 10px 14px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .customer-select-btn {
            width: 100%; padding: 8px 12px; border: 2px dashed var(--border);
            border-radius: 10px; background: transparent; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 700; color: var(--text3);
            transition: all .2s; font-family: 'Roboto', sans-serif;
        }
        .customer-select-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .customer-select-btn.has-customer { border-style: solid; border-color: var(--green); color: var(--text); background: var(--green-light); }

        /* Order header */
        .order-header {
            padding: 10px 14px 6px;
            display: flex; align-items: center; justify-content: space-between;
            flex-shrink: 0;
        }
        .order-title { font-size: 12px; font-weight: 800; color: var(--text3); letter-spacing: .5px; text-transform: uppercase; }
        .order-count { font-size: 11px; font-weight: 700; color: var(--primary); background: var(--primary-light); padding: 2px 8px; border-radius: 10px; }

        /* Cart Items */
        .cart-items { flex: 1; overflow-y: auto; min-height: 120px; }
        .cart-empty {
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; height: 100%; color: var(--text3); gap: 12px;
        }
        .cart-empty-icon { font-size: 56px; opacity: .25; }
        .cart-empty-text { font-size: 14px; font-weight: 700; text-align: center; color: var(--text3); }

        .cart-item {
            padding: 10px 14px; display: flex; gap: 10px;
            align-items: flex-start; border-bottom: 1px solid var(--border);
            transition: background .15s;
        }
        .cart-item:hover { background: var(--surface2); }
        .cart-item-num {
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--primary); color: #fff;
            font-size: 11px; font-weight: 900; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px;
        }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-name { font-size: 13px; font-weight: 800; line-height: 1.3; color: var(--text); }
        .cart-item-note { font-size: 11px; color: var(--text3); font-style: italic; margin-top: 2px; }
        .cart-item-controls { display: flex; align-items: center; gap: 5px; margin-top: 6px; flex-wrap: wrap; }
        .qty-btn {
            width: 28px; height: 28px; border-radius: 50%;
            border: 2px solid var(--border); background: var(--surface);
            cursor: pointer; font-size: 14px; font-weight: 900;
            display: flex; align-items: center; justify-content: center;
            transition: all .15s; color: var(--text); font-family: 'Roboto', sans-serif;
        }
        .qty-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .qty-input {
            width: 36px; text-align: center; border: 2px solid var(--border);
            border-radius: 8px; padding: 2px 4px; font-size: 14px; font-weight: 800;
            font-family: 'Roboto Mono', monospace;
        }
        .cart-item-subtotal { font-size: 14px; font-weight: 900; color: var(--primary); white-space: nowrap; }
        .cart-item-remove { color: var(--text3); cursor: pointer; font-size: 13px; padding: 4px; transition: color .15s; }
        .cart-item-remove:hover { color: var(--red); }

        /* Note btn */
        .note-btn {
            padding: 3px 8px; border-radius: 8px; font-size: 11px; font-weight: 700;
            border: 1px solid var(--border); background: var(--surface2);
            color: var(--text3); cursor: pointer; transition: all .15s;
            font-family: 'Roboto', sans-serif;
        }
        .note-btn.has-note { border-color: var(--secondary); background: #FFF3E0; color: #E65100; }
        .note-btn:hover { border-color: var(--primary); color: var(--primary); }

        /* Cart Summary */
        .cart-summary { border-top: 2px solid var(--border); padding: 8px 14px; flex-shrink: 0; }
        .summary-row { display: flex; justify-content: space-between; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--text2); }
        .summary-row.total {
            font-family: 'Google Sans', sans-serif; font-size: 18px; font-weight: 700; color: var(--text);
            border-top: 1px solid var(--border); padding-top: 8px; margin-top: 4px; margin-bottom: 0;
        }
        .discount-row { display: flex; gap: 8px; margin-bottom: 6px; }
        .discount-input {
            flex: 1; padding: 7px 10px; border: 2px solid var(--border);
            border-radius: 8px; font-size: 13px; font-weight: 700;
            font-family: 'Roboto', sans-serif; color: var(--text);
        }
        .discount-input:focus { outline: none; border-color: var(--primary); }

        /* Payment */
        .cart-payment { padding: 10px 14px; border-top: 1px solid var(--border); flex-shrink: 0; }
        /* Horizontal scroll — satu baris, tidak pernah wrap ke bawah */
        .payment-methods {
            display: flex; flex-wrap: nowrap; gap: 6px;
            margin-bottom: 10px; overflow-x: auto; padding-bottom: 2px;
            scrollbar-width: thin; scrollbar-color: var(--border) transparent;
        }
        .payment-methods::-webkit-scrollbar { height: 3px; }
        .payment-methods::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        .pay-btn {
            flex-shrink: 0; min-width: 82px;
            padding: 8px 4px; border: 2px solid var(--border); border-radius: 10px;
            background: var(--surface2); cursor: pointer; text-align: center;
            font-size: 11px; font-weight: 800; color: var(--text2); transition: all .2s;
            font-family: 'Roboto', sans-serif;
        }
        .pay-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .pay-btn.active { border-color: var(--primary); background: var(--primary); color: #fff; }
        .pay-icon { font-size: 18px; display: block; margin-bottom: 3px; }
        .paid-row { display: flex; gap: 8px; margin-bottom: 8px; }
        .paid-input {
            flex: 1; padding: 10px 14px; border: 2px solid var(--primary);
            border-radius: 10px; font-size: 18px; font-weight: 800;
            font-family: 'Roboto Mono', monospace; text-align: right; color: var(--text);
        }
        .change-display {
            background: var(--green-light); border-radius: 10px; padding: 10px 14px;
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .change-label { font-size: 13px; font-weight: 700; color: var(--green); }
        .change-value { font-size: 18px; font-weight: 900; color: var(--green); font-family: 'Roboto', sans-serif; }
        .btn-checkout {
            width: 100%; padding: 14px; background: var(--green); color: #fff;
            border: none; border-radius: var(--radius); font-size: 16px; font-weight: 700;
            font-family: 'Google Sans', sans-serif; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all .2s;
        }
        .btn-checkout:hover { background: #2D9247; box-shadow: 0 4px 12px rgba(52,168,83,.4); }
        .btn-checkout:disabled { background: var(--text3); cursor: not-allowed; box-shadow: none; }
        .btn-clear {
            width: 100%; padding: 8px; background: transparent; color: var(--red);
            border: 2px solid var(--red); border-radius: 10px;
            font-size: 13px; font-weight: 800; cursor: pointer;
            margin-bottom: 8px; transition: all .2s; font-family: 'Roboto', sans-serif;
        }
        .btn-clear:hover { background: var(--red-light); }

        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal {
            background: var(--surface); border-radius: 20px;
            box-shadow: 0 12px 48px rgba(0,0,0,.25); max-width: 480px;
            width: 90%; max-height: 90vh; overflow-y: auto;
            animation: modalIn .2s ease;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(.94)}to{opacity:1;transform:none} }
        .modal-header {
            padding: 20px 24px; border-bottom: 2px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-title { font-size: 18px; font-weight: 900; color: var(--text); }
        .modal-body { padding: 20px 24px; }
        .modal-footer {
            padding: 14px 24px; border-top: 1px solid var(--border);
            display: flex; gap: 8px; justify-content: flex-end;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px; border-radius: 24px; font-size: 14px; font-weight: 600;
            cursor: pointer; border: none; transition: all .2s; font-family: 'Google Sans', sans-serif;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--green); color: #fff; }
        .btn-warning { background: #F9AB00; color: #fff; }
        .btn-warning:hover { background: #E69500; }
        .btn-ghost { background: transparent; color: var(--text2); border: 2px solid var(--border); }
        .btn-ghost:hover { border-color: var(--primary); color: var(--primary); }
        .form-control {
            width: 100%; padding: 10px 14px; border: 2px solid var(--border);
            border-radius: 10px; font-size: 14px; font-weight: 600;
            color: var(--text); margin-bottom: 10px; font-family: 'Roboto', sans-serif;
        }
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
        .toast {
            background: var(--dark-bg); color: #fff; padding: 12px 18px; border-radius: 10px;
            font-size: 14px; font-weight: 700; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 8px;
            animation: toastIn .3s ease; min-width: 260px; font-family: 'Roboto', sans-serif;
        }
        .toast.ok { background: var(--green); }
        .toast.err { background: var(--red); }
        .toast.warn { background: #E65100; }
        @keyframes toastIn { from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:none} }

        .spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; display: inline-block; }
        @keyframes spin { to{transform:rotate(360deg)} }

        /* Scrollbar */
        .cart-items::-webkit-scrollbar, .products-grid::-webkit-scrollbar { width: 4px; }
        .cart-items::-webkit-scrollbar-thumb, .products-grid::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
    </style>
</head>
<body>

<!-- Top Bar -->
<div class="pos-topbar">
    <a href="{{ route('dashboard') }}" class="pos-topbar-brand">
        <img src="{{ asset('images/happypos.png') }}" alt="HPYSync" style="height:30px;width:auto;object-fit:contain;">
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
        <a href="{{ route('transactions.index') }}" class="topbar-btn"><i class="fas fa-history"></i> Riwayat</a>
        <a href="{{ route('dashboard') }}" class="topbar-btn"><i class="fas fa-th-large"></i> Dashboard</a>
    </div>
</div>

<!-- Main POS Layout -->
<div class="pos-layout">

    <!-- LEFT: Menu -->
    <div class="pos-products">
        <div class="pos-search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input"
                    placeholder="Cari menu atau scan barcode... (F3)" autocomplete="off">
            </div>
        </div>

        <div class="categories-wrap">
            <button class="cat-arrow cat-arrow-left hidden" id="catArrowLeft" onclick="scrollCats(-1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="categories-bar" id="categoriesBar">
                <button class="cat-btn active" onclick="filterCategory(null, this)">🍽 Semua</button>
                @foreach($categories as $cat)
                <button class="cat-btn" onclick="filterCategory({{ $cat->id }}, this)" data-id="{{ $cat->id }}">
                    {{ $cat->name }}
                </button>
                @endforeach
            </div>
            <button class="cat-arrow cat-arrow-right hidden" id="catArrowRight" onclick="scrollCats(1)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>

        <div class="products-grid" id="productsGrid">
            <div id="loadingProducts" style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)">
                <div style="font-size:36px;margin-bottom:8px">⏳</div>
                <div style="font-size:14px;font-weight:700">Memuat menu...</div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Order Panel -->
    <div class="pos-cart">

        <!-- Order Type + Table -->
        <div class="order-type-bar">
            <button class="order-type-btn active" data-type="dine_in" onclick="selectOrderType(this)">
                🍽 Dine In
            </button>
            <button class="order-type-btn" data-type="take_away" onclick="selectOrderType(this)">
                🥡 Take Away
            </button>
            <button class="order-type-btn" data-type="delivery" onclick="selectOrderType(this)">
                🛵 Delivery
            </button>
            <div class="table-input-wrap" id="tableWrap">
                <div class="table-label"><i class="fas fa-chair"></i></div>
                <input type="text" id="tableNumber" class="table-input" placeholder="Meja" maxlength="6">
            </div>
        </div>

        <!-- Delivery Platform Picker (visible only when Delivery selected) -->
        <div class="delivery-platform-bar" id="deliveryPlatformBar">
            <span class="delivery-platform-label">📦 Via:</span>
            <button class="platform-btn gofood" data-platform="gofood" onclick="selectDeliveryPlatform(this)">
                <span class="platform-logo">🟢</span>
                <span>GoFood</span>
            </button>
            <button class="platform-btn grabfood" data-platform="grabfood" onclick="selectDeliveryPlatform(this)">
                <span class="platform-logo">🟩</span>
                <span>GrabFood</span>
            </button>
            <button class="platform-btn shopeefood" data-platform="shopeefood" onclick="selectDeliveryPlatform(this)">
                <span class="platform-logo">🟠</span>
                <span>ShopeeFood</span>
            </button>
        </div>

        <!-- Customer -->
        <div class="cart-customer">
            <button class="customer-select-btn" id="customerBtn" onclick="openCustomerModal()">
                <i class="fas fa-user-circle" id="customerIcon" style="font-size:16px;color:var(--primary)"></i>
                <span id="customerBtnText">Memuat...</span>
                <i class="fas fa-times" id="customerClearBtn" style="display:none;margin-left:auto;color:var(--red)" onclick="clearCustomer(event)"></i>
            </button>
        </div>

        <!-- Order header -->
        <div class="order-header">
            <span class="order-title"><i class="fas fa-clipboard-list"></i> Pesanan</span>
            <span class="order-count" id="orderCount">0 item</span>
        </div>

        <!-- Order Items -->
        <div class="cart-items" id="cartItems">
            <div class="cart-empty" id="cartEmpty">
                <div class="cart-empty-icon">🍽️</div>
                <div class="cart-empty-text">Belum ada pesanan<br><small style="font-size:12px;font-weight:600">Pilih menu untuk menambahkan</small></div>
            </div>
        </div>

        <!-- Summary -->
        <div class="cart-summary">
            <div class="summary-row">
                <span>Total Qty</span>
                <span id="totalQtyDisplay">0</span>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <span id="subtotalDisplay">Rp 0</span>
            </div>
            <div class="discount-row" style="display:none">
                <div style="flex:1">
                    <div style="font-size:11px;color:var(--text3);margin-bottom:3px;font-weight:700">Diskon (Rp)</div>
                    <input type="number" id="discountAmt" class="discount-input" placeholder="0" min="0" oninput="syncTxDiscount('amt')">
                </div>
                <div style="flex:1">
                    <div style="font-size:11px;color:var(--text3);margin-bottom:3px;font-weight:700">Diskon (%)</div>
                    <input type="number" id="discountPct" class="discount-input" placeholder="0" min="0" max="100" oninput="syncTxDiscount('pct')">
                </div>
            </div>
            <div class="summary-row">
                <span>Diskon</span>
                <span id="discountDisplay" style="color:var(--red)">- Rp 0</span>
            </div>
            <!-- Coupon -->
            <div id="couponInputRow" style="margin-bottom:6px">
                <div style="display:flex;gap:6px;align-items:center">
                    <span style="font-size:11px;color:var(--text3);font-weight:700;white-space:nowrap;flex-shrink:0">
                        <i class="fas fa-ticket-alt"></i> Kupon
                    </span>
                    <input type="text" id="couponInput" class="discount-input" placeholder="Kode kupon" maxlength="50"
                        style="flex:1;min-width:0;text-transform:uppercase;margin:0"
                        oninput="this.value=this.value.toUpperCase()">
                    <button onclick="applyCoupon()" id="couponApplyBtn"
                        style="padding:0 10px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:11px;cursor:pointer;font-weight:700;white-space:nowrap;height:34px;flex-shrink:0">
                        Terapkan
                    </button>
                </div>
                <div id="couponMessage" style="font-size:11px;margin-top:2px;display:none;padding-left:2px"></div>
            </div>
            <div class="summary-row" id="couponDiscountRow" style="display:none">
                <span>
                    Kupon <span id="couponCodeLabel" style="font-family:monospace;font-weight:700"></span>
                    <span onclick="removeCoupon()" title="Hapus kupon"
                        style="cursor:pointer;color:var(--red);font-size:11px;margin-left:4px;font-weight:700">✕</span>
                </span>
                <span id="couponDiscountDisplay" style="color:var(--red)">- Rp 0</span>
            </div>
            <div class="summary-row">
                <span>Pajak</span>
                <span id="taxDisplay">Rp 0</span>
            </div>
            <div class="summary-row" id="scRow" style="display:none;color:#E65100">
                <span id="scLabel">Service Charge</span>
                <span id="scDisplay">Rp 0</span>
            </div>
            <div class="summary-row" id="pb1Row" style="display:none;color:#E65100">
                <span id="pb1Label">PB1</span>
                <span id="pb1Display">Rp 0</span>
            </div>
            <div class="summary-row total">
                <span>TOTAL</span>
                <span id="totalDisplay">Rp 0</span>
            </div>
        </div>

        <!-- Payment -->
        <div class="cart-payment">
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
                    <button class="pay-btn active" data-method="cash" data-cash-type="1" onclick="selectPayment(this)">
                        <span class="pay-icon">💵</span>Tunai
                    </button>
                    <button class="pay-btn" data-method="card" data-cash-type="0" onclick="selectPayment(this)">
                        <span class="pay-icon">💳</span>Kartu
                    </button>
                    <button class="pay-btn" data-method="transfer" data-cash-type="0" onclick="selectPayment(this)">
                        <span class="pay-icon">🏦</span>Transfer
                    </button>
                    <button class="pay-btn" data-method="qris" data-cash-type="0" onclick="selectPayment(this)">
                        <span class="pay-icon">📱</span>QRIS
                    </button>
                @endif
            </div>

@php $defaultIsCash = empty($posPaymentMethods) ? true : (bool)($posPaymentMethods[0]['is_cash'] ?? false); @endphp
            <div id="cashSection" @if(!$defaultIsCash)style="display:none"@endif>
                <div style="font-size:11px;color:var(--text3);margin-bottom:4px;font-weight:800;letter-spacing:.5px">NOMINAL BAYAR</div>
                <div class="paid-row">
                    <input type="number" id="paidAmount" class="paid-input" placeholder="0" oninput="calcChange()">
                </div>
                <div id="quickAmounts" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px"></div>
                <div class="change-display">
                    <span class="change-label"><i class="fas fa-coins"></i> Kembalian</span>
                    <span class="change-value" id="changeDisplay">Rp 0</span>
                </div>
            </div>

            <button class="btn-clear" onclick="clearCart()"><i class="fas fa-trash"></i> Hapus Semua</button>
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
            <input type="text" id="customerSearch" class="form-control" placeholder="Cari nama, telp, atau kode customer..." oninput="searchCustomers(this.value)" autofocus>
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
            <textarea id="noteText" class="form-control" rows="3" placeholder="Contoh: tidak pedas, tanpa bawang, tambah saus..."></textarea>
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
                    <span style="color:var(--text3)">Harga × Qty</span>
                    <span id="discItemGross"></span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="color:var(--red)">Diskon</span>
                    <span id="discItemDiscVal" style="color:var(--red)"></span>
                </div>
                <div style="display:flex;justify-content:space-between;border-top:1px dashed var(--border);padding-top:6px">
                    <span style="font-weight:900">Subtotal</span>
                    <span id="discItemNet" style="font-weight:900;color:var(--green)"></span>
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
            <button class="btn btn-warning" onclick="printKitchen()"><i class="fas fa-utensils"></i> Cetak Kitchen</button>
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
const defaultPosClass = @json($posClass);
const walkinCustomerName = @json($walkinCustomerName);
const posProductDisplay = @json($posProductDisplay);
const erpBaseUrl = @json($erpBaseUrl);
const appBaseUrl = @json(url('/'));
const deliveryPrices  = @json($deliveryPrices);
const dineInCharges   = @json($dineInCharges);

// ============================================================
// CLOCK
// ============================================================
function updateClock() {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString('id-ID');
}
setInterval(updateClock, 1000); updateClock();

// ============================================================
// ORDER TYPE + DELIVERY PLATFORM
// ============================================================
function selectOrderType(btn) {
    document.querySelectorAll('.order-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedOrderType = btn.dataset.type;
    document.getElementById('tableWrap').style.display = selectedOrderType === 'dine_in' ? 'flex' : 'none';

    const bar = document.getElementById('deliveryPlatformBar');
    if (selectedOrderType === 'delivery') {
        bar.classList.add('show');
    } else {
        bar.classList.remove('show');
        // Reset platform & restore normal prices when leaving Delivery
        if (selectedDeliveryPlatform) {
            selectedDeliveryPlatform = null;
            document.querySelectorAll('.platform-btn').forEach(b => b.classList.remove('active'));
            applyDeliveryPrices();
            renderCart();
        }
    }
    // Recalculate to show/hide Service Charge & PB1 rows
    recalculate();
}

function selectDeliveryPlatform(btn) {
    const platform = btn.dataset.platform;
    if (selectedDeliveryPlatform === platform) {
        // Toggle off
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
    // Update cart item prices to reflect active delivery platform
    cart.forEach(item => {
        const dp = getDeliveryPrice(item.erpItemCode);
        item.price = dp !== null ? dp : item.basePrice;
        // Reset item discount when price changes
        item.discount = 0;
        item.discountPct = 0;
    });
    // Re-render product grid with new prices
    renderProducts(allProducts);
}

// ============================================================
// LOAD & RENDER PRODUCTS
// ============================================================
async function loadProducts(categoryId = null) {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)"><div style="font-size:36px;margin-bottom:8px">⏳</div><div style="font-size:14px;font-weight:700">Memuat menu...</div></div>';
    const url = '{{ route("pos.search-products") }}' + '?q=' + (categoryId ? '&category_id=' + categoryId : '');
    try {
        const resp = await fetch(url, { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        const products = await resp.json();
        allProducts = Array.isArray(products) ? products : [];
        renderProducts(allProducts);
    } catch(e) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--red)"><div style="font-size:36px;margin-bottom:8px">⚠️</div><div style="font-size:14px;font-weight:700">Gagal memuat menu</div></div>';
    }
}

function renderProducts(products) {
    const grid = document.getElementById('productsGrid');
    if (products.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text3)"><div style="font-size:36px;margin-bottom:8px">🍽️</div><div style="font-size:14px;font-weight:700">Menu tidak ditemukan</div></div>';
        return;
    }
    const outOfStock = p => p.track_stock && p.stock <= 0;
    products = [...products].sort((a, b) => (outOfStock(a)?1:0) - (outOfStock(b)?1:0));

    const imgUrl = p => {
        if (!p.image) return null;
        if (p.image.startsWith('http')) return p.image;
        if (p.image.startsWith('/images/')) return appBaseUrl + p.image;
        return erpBaseUrl + p.image;
    };
    const effectivePrice = p => {
        const dp = getDeliveryPrice(p.erp_item_code);
        return dp !== null ? dp : p.price;
    };

    const cardAttrs = (p, extra = '') => `
        class="product-card ${extra} ${outOfStock(p) ? 'out-of-stock' : ''}"
        data-id="${p.id}" data-name="${p.name.replace(/'/g,"&#39;")}"
        data-price="${effectivePrice(p)}" data-base-price="${p.price}"
        data-sku="${p.sku}" data-stock="${p.stock}"
        data-unit="${p.unit}" data-tax="${p.tax_rate}"
        data-track="${p.track_stock ? 1 : 0}" data-category="${p.category_id || ''}"
        data-erp-code="${p.erp_item_code || ''}"
        onclick="addToCart(this)"`;

    if (posProductDisplay === 'text') {
        grid.classList.add('text-mode');
        grid.innerHTML = products.map(p => {
            const ep = effectivePrice(p);
            const hasDeliveryPrice = ep !== p.price;
            const stockInfo = p.track_stock
                ? (p.stock <= 0
                    ? `<span style="margin-left:6px;font-size:10px;font-weight:800;background:var(--red-light);color:var(--red);padding:1px 6px;border-radius:8px">Habis</span>`
                    : (p.is_low_stock ? `<span style="margin-left:6px;font-size:10px;font-weight:800;background:#FFF3E0;color:#E65100;padding:1px 6px;border-radius:8px">Stok: ${p.stock}</span>` : ''))
                : '';
            return `
            <div ${cardAttrs(p, 'text-card')}>
                <span style="width:8px;min-width:8px;height:8px;border-radius:50%;background:${p.category_color||'var(--primary)'}"></span>
                <div style="flex:1;min-width:0;display:flex;align-items:center;gap:0;overflow:hidden">
                    <span style="font-size:14px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:${outOfStock(p)?'var(--text3)':'var(--text)'}">${p.name}</span>
                    ${stockInfo}
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:14px;font-weight:900;color:${outOfStock(p)?'var(--text3)':'var(--primary)'}">Rp ${fmt(ep)}</div>
                    ${hasDeliveryPrice ? `<div style="font-size:10px;text-decoration:line-through;color:var(--text3)">Rp ${fmt(p.price)}</div>` : ''}
                </div>
            </div>`;
        }).join('');
    } else {
        grid.classList.remove('text-mode');
        grid.innerHTML = products.map(p => {
            const ep = effectivePrice(p);
            const hasDeliveryPrice = ep !== p.price;
            return `
            <div ${cardAttrs(p)}>
                <div class="product-img">
                    ${imgUrl(p)
                        ? `<img src="${imgUrl(p)}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling.style.display=''">`
                        : ''}
                    <span style="font-size:38px;${imgUrl(p)?'display:none':''}">🍽️</span>
                    ${p.category ? `<span class="product-cat-dot">${p.category}</span>` : ''}
                    ${p.is_low_stock && !outOfStock(p) ? '<span class="low-stock-badge">LOW</span>' : ''}
                    ${outOfStock(p) ? '<span class="out-badge">HABIS</span>' : ''}
                </div>
                <div class="product-info">
                    <div class="product-name">${p.name}</div>
                    <div class="product-price">
                        Rp ${fmt(ep)}
                        ${hasDeliveryPrice ? `<span style="font-size:10px;text-decoration:line-through;color:var(--text3);font-weight:600;margin-left:4px">Rp ${fmt(p.price)}</span>` : ''}
                    </div>
                    <div class="product-stock ${p.is_low_stock?'stock-low':''}">
                        ${p.track_stock ? (p.stock<=0 ? '⚠ Stok Habis' : 'Stok: '+p.stock+' '+p.unit) : '∞ Tersedia'}
                    </div>
                </div>
            </div>`;
        }).join('');
    }
}

// ============================================================
// FILTER & SEARCH
// ============================================================
function filterCategory(catId, btn) {
    currentCategoryFilter = catId;
    document.querySelectorAll('.cat-btn').forEach(b => { b.classList.remove('active'); b.style.background = ''; b.style.color = ''; });
    btn.classList.add('active');
    loadProducts(catId);
}

let searchTimeout;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        const term = this.value.trim();
        const url = '{{ route("pos.search-products") }}' + '?q=' + encodeURIComponent(term) + (currentCategoryFilter ? '&category_id=' + currentCategoryFilter : '');
        try {
            const resp = await fetch(url, { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
            const products = await resp.json();
            allProducts = Array.isArray(products) ? products : [];
            renderProducts(allProducts);
        } catch(e) { console.error(e); }
    }, 300);
});

// Scanner barcode mengetik cepat lalu mengirim Enter. Kalau yang diketik cocok persis
// dengan barcode/SKU satu produk, langsung masuk keranjang dan kolom dikosongkan supaya
// siap untuk scan berikutnya. Selain itu perlakukan seperti pencarian biasa.
document.getElementById('searchInput').addEventListener('keydown', async function(e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();

    const term = this.value.trim();
    if (!term) return;

    clearTimeout(searchTimeout);

    const url = '{{ route("pos.search-products") }}' + '?q=' + encodeURIComponent(term);
    let products = [];
    try {
        const resp = await fetch(url, { headers: {'Accept':'application/json','X-CSRF-TOKEN':csrf} });
        products = await resp.json();
        if (!Array.isArray(products)) products = [];
    } catch(err) { console.error(err); return; }

    const key = term.toLowerCase();
    const exact = products.filter(p =>
        (p.barcode || '').toLowerCase() === key || (p.sku || '').toLowerCase() === key
    );

    if (exact.length === 1) {
        addProductToCart(exact[0]);
        this.value = '';
        loadProducts(currentCategoryFilter);
        return;
    }

    // Tidak ada yang cocok persis (atau ambigu) — tampilkan hasilnya biar kasir yang pilih.
    allProducts = products;
    renderProducts(products);
    if (products.length === 0) toast('⚠ Produk "' + term + '" tidak ditemukan.', 'warn');
});

// Jembatan dari objek produk (hasil scan) ke addToCart yang membaca data-* kartu produk.
function addProductToCart(p) {
    addToCart({ dataset: {
        id:         p.id,
        name:       p.name,
        price:      p.price,
        basePrice:  p.price,
        sku:        p.sku || '',
        stock:      p.stock,
        unit:       p.unit || '',
        tax:        p.tax_rate,
        track:      p.track_stock ? '1' : '0',
        erpCode:    p.erp_item_code || '',
    }});
}

document.addEventListener('keydown', e => {
    if (e.key === 'F3') { e.preventDefault(); document.getElementById('searchInput').focus(); document.getElementById('searchInput').select(); }
    if (e.key === 'Escape') { ['customerModal','receiptModal','itemNoteModal','itemDiscountModal'].forEach(id => closeModal(id)); }
});

// ============================================================
// CART
// ============================================================
function addToCart(el) {
    const id = parseInt(el.dataset.id);
    const track = el.dataset.track === '1';
    const stock = parseInt(el.dataset.stock);
    if (track && stock <= 0) { toast('⚠ Stok habis! Penjualan tetap diproses.', 'warn'); }
    const existing = cart.find(i => i.id === id);
    if (existing) {
        existing.qty++;
        // Update price if delivery platform is active (in case platform changed after adding)
        const dp = getDeliveryPrice(existing.erpItemCode);
        if (dp !== null) existing.price = dp;
    } else {
        const basePrice  = parseFloat(el.dataset.basePrice || el.dataset.price);
        if (basePrice === 0) { toast('⚠ Harga produk ini Rp 0, harap periksa kembali.', 'warn'); }
        const erpCode    = el.dataset.erpCode || '';
        const dp         = getDeliveryPrice(erpCode);
        // Item baru diletakkan paling atas keranjang (memudahkan lihat hasil scan terbaru)
        cart.unshift({
            id, name: el.dataset.name,
            price: dp !== null ? dp : basePrice,
            basePrice,
            erpItemCode: erpCode,
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
    if (item.track && newQty > item.stock) { toast('⚠ Melebihi stok tersedia!', 'warn'); }
    item.qty = newQty;
    renderCart();
}

function setQty(id, val) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const qty = parseInt(val) || 1;
    if (item.track && qty > item.stock) { toast('⚠ Melebihi stok tersedia!', 'warn'); }
    item.qty = Math.max(1, qty);
    renderCart();
}

function setPrice(id, val) {
    const item = cart.find(i => i.id === id);
    if (!item) return;
    const price = parseFloat(val) || 0;
    if (price < 0) return;
    item.price = price;
    item.basePrice = price; // manual override becomes new base
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
    const container = document.getElementById('cartItems');
    const empty = document.getElementById('cartEmpty');
    const countEl = document.getElementById('orderCount');

    const totalItems = cart.reduce((s, i) => s + i.qty, 0);
    countEl.textContent = totalItems + ' item';
    const qtyEl = document.getElementById('totalQtyDisplay');
    if (qtyEl) qtyEl.textContent = totalItems;

    if (cart.length === 0) {
        container.innerHTML = '';
        container.appendChild(empty);
        empty.style.display = 'flex';
        document.getElementById('checkoutBtn').disabled = true;
        recalculate();
        return;
    }

    empty.style.display = 'none';
    let html = '';
    cart.forEach((item, idx) => {
        const gross    = item.price * item.qty;
        const subtotal = Math.max(0, gross - (item.discount || 0));
        const hasDisc  = item.discount > 0;
        const hasNote  = item.note && item.note.trim().length > 0;
        html += `
        <div class="cart-item">
            <div class="cart-item-num">${idx+1}</div>
            <div class="cart-item-info">
                <div style="display:flex;align-items:center;gap:6px">
                    <div class="cart-item-name" style="flex:1">${item.name}</div>
                    ${hasDisc ? `<span style="font-size:10px;font-weight:800;background:var(--red-light);color:var(--red);padding:2px 6px;border-radius:10px;white-space:nowrap">-Rp ${fmt(item.discount)}</span>` : ''}
                </div>
                ${hasNote ? `<div class="cart-item-note"><i class="fas fa-sticky-note" style="color:var(--secondary);font-size:10px"></i> ${item.note}</div>` : ''}
                <div class="cart-item-controls">
                    <button class="qty-btn" onclick="updateQty(${item.id},-1)">−</button>
                    <input class="qty-input" type="number" value="${item.qty}" min="1" onchange="setQty(${item.id},this.value)">
                    <button class="qty-btn" onclick="updateQty(${item.id},1)">+</button>
                    <span style="font-size:11px;color:var(--text3)">×</span>
                    <div style="position:relative;display:inline-flex;align-items:center">
                        <span style="position:absolute;left:9px;font-size:11px;color:var(--text3);pointer-events:none">Rp</span>
                        <input type="number" min="0" value="${item.price}" onchange="setPrice(${item.id},this.value)" onclick="this.select()"
                            style="width:110px;padding:4px 8px 4px 28px;border:2px solid var(--primary);border-radius:8px;font-size:14px;font-weight:800;font-family:'Roboto Mono',monospace;color:var(--primary)">
                    </div>
                    <button onclick="openItemNote(${item.id})" class="note-btn ${hasNote?'has-note':''}">
                        <i class="fas fa-sticky-note"></i> ${hasNote ? 'Catatan' : 'Catat'}
                    </button>
                    <button onclick="openItemDiscount(${item.id})"
                        style="padding:3px 8px;border-radius:8px;font-size:11px;font-weight:800;border:1px solid ${hasDisc?'var(--red)':'var(--border)'};background:${hasDisc?'var(--red-light)':'var(--surface2)'};color:${hasDisc?'var(--red)':'var(--text2)'};cursor:pointer;font-family:'Nunito',sans-serif">
                        <i class="fas fa-tag"></i> Diskon
                    </button>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0">
                <i class="fas fa-times cart-item-remove" onclick="removeFromCart(${item.id})"></i>
                ${hasDisc ? `<span style="font-size:11px;color:var(--text3);text-decoration:line-through">Rp ${fmt(gross)}</span>` : ''}
                <span class="cart-item-subtotal" style="${hasDisc?'color:var(--red)':''}">Rp ${fmt(subtotal)}</span>
            </div>
        </div>`;
    });

    container.querySelectorAll('.cart-item').forEach(el => el.remove());
    empty.insertAdjacentHTML('afterend', html);
    recalculate();
    document.getElementById('checkoutBtn').disabled = false;
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
    if (item) { item.note = document.getElementById('noteText').value.trim(); }
    closeModal('itemNoteModal');
    renderCart();
}

function clearItemNote() {
    const item = cart.find(i => i.id === noteItemId);
    if (item) { item.note = ''; }
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
        document.getElementById('discItemPct').value = gross > 0 ? parseFloat(((amt / gross) * 100).toFixed(2)) : '';
    } else {
        const pct = parseFloat(document.getElementById('discItemPct').value) || 0;
        document.getElementById('discItemAmt').value = parseFloat(((pct / 100) * gross).toFixed(0));
    }
    updateDiscPreview(item);
}

function updateDiscPreview(item) {
    const gross   = item.price * item.qty;
    const amt     = parseFloat(document.getElementById('discItemAmt').value) || 0;
    const net     = Math.max(0, gross - amt);
    const preview = document.getElementById('discItemPreview');
    if (amt > 0) {
        preview.style.display = 'block';
        document.getElementById('discItemGross').textContent   = 'Rp ' + fmt(gross);
        document.getElementById('discItemDiscVal').textContent = '- Rp ' + fmt(amt);
        document.getElementById('discItemNet').textContent     = 'Rp ' + fmt(net);
    } else {
        preview.style.display = 'none';
    }
}

function applyItemDiscount() {
    const item = cart.find(i => i.id === discItemId);
    if (!item) return;
    const gross = item.price * item.qty;
    const amt   = parseFloat(document.getElementById('discItemAmt').value) || 0;
    const pct   = parseFloat(document.getElementById('discItemPct').value) || 0;
    if (amt > gross) { toast('Diskon melebihi harga item!', 'err'); return; }
    item.discount    = amt;
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
    btn.disabled = true;
    btn.textContent = '...';

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
        btn.disabled = false;
        btn.textContent = 'Terapkan';
    }
}

function removeCoupon() {
    appliedCoupon = null;
    const inp = document.getElementById('couponInput');
    inp.value = '';
    inp.disabled = false;
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
    const tax      = cart.reduce((s, i) => s + (Math.max(0, (i.price * i.qty) - (i.discount || 0)) * (i.tax / 100)), 0);
    let discAmt    = parseFloat(document.getElementById('discountAmt').value) || 0;
    const discPct  = parseFloat(document.getElementById('discountPct').value) || 0;
    if (discPct > 0) discAmt = subtotal * (discPct / 100);

    // Coupon discount
    let couponDiscount = 0;
    if (appliedCoupon) {
        couponDiscount = appliedCoupon.discount_type === 'percent'
            ? Math.round(subtotal * appliedCoupon.discount_value / 100)
            : Math.min(appliedCoupon.discount_value, subtotal);
        appliedCoupon.calculated_discount = couponDiscount;
        document.getElementById('couponDiscountRow').style.display = '';
        document.getElementById('couponCodeLabel').textContent = appliedCoupon.code;
        document.getElementById('couponDiscountDisplay').textContent = '- Rp ' + fmt(couponDiscount);
    } else {
        document.getElementById('couponDiscountRow').style.display = 'none';
    }

    const base = subtotal - discAmt - couponDiscount;

    // Service Charge & PB1 — only for Dine In
    let scAmt  = 0;
    let pb1Amt = 0;

    if (selectedOrderType === 'dine_in') {
        if (dineInCharges.service_charge_enabled && dineInCharges.service_charge_pct > 0) {
            scAmt = Math.round(base * dineInCharges.service_charge_pct / 100);
        }
        if (dineInCharges.pb1_enabled && dineInCharges.pb1_pct > 0) {
            pb1Amt = Math.round((base + scAmt) * dineInCharges.pb1_pct / 100);
        }
    }

    const total = base + tax + scAmt + pb1Amt;

    document.getElementById('subtotalDisplay').textContent = 'Rp ' + fmt(subtotal);
    document.getElementById('discountDisplay').textContent = '- Rp ' + fmt(discAmt);
    document.getElementById('taxDisplay').textContent      = 'Rp ' + fmt(tax);
    document.getElementById('totalDisplay').textContent    = 'Rp ' + fmt(total);
    document.getElementById('topbarTotalDisplay').textContent = 'Rp ' + fmt(total);

    // Show/hide Service Charge row
    const scRow = document.getElementById('scRow');
    if (scAmt > 0) {
        scRow.style.display = '';
        document.getElementById('scLabel').textContent   = `Service Charge (${dineInCharges.service_charge_pct}%)`;
        document.getElementById('scDisplay').textContent = 'Rp ' + fmt(scAmt);
    } else {
        scRow.style.display = 'none';
    }

    // Show/hide PB1 row
    const pb1Row = document.getElementById('pb1Row');
    if (pb1Amt > 0) {
        pb1Row.style.display = '';
        document.getElementById('pb1Label').textContent   = `PB1 (${dineInCharges.pb1_pct}%)`;
        document.getElementById('pb1Display').textContent = 'Rp ' + fmt(pb1Amt);
    } else {
        pb1Row.style.display = 'none';
    }

    if (selectedPaymentIsCash) {
        const amounts = [total, Math.ceil(total/10000)*10000, Math.ceil(total/50000)*50000, Math.ceil(total/100000)*100000];
        const unique = [...new Set(amounts)].filter((v,i,a)=>a.indexOf(v)===i).slice(0,4);
        document.getElementById('quickAmounts').innerHTML = unique.map(a =>
            `<button onclick="setPaid(${a})" style="padding:5px 12px;border:2px solid var(--border);border-radius:16px;font-size:12px;cursor:pointer;background:var(--surface2);font-weight:800;font-family:'Nunito',sans-serif;color:var(--text2)">Rp ${fmt(a)}</button>`
        ).join('');
    }
    calcChange();
}

function calcChange() {
    const totalText = document.getElementById('totalDisplay').textContent;
    const total = parseFloat(totalText.replace(/[^0-9]/g,''));
    const paid = parseFloat(document.getElementById('paidAmount').value) || 0;
    const change = paid - total;
    const el = document.getElementById('changeDisplay');
    el.textContent = 'Rp ' + fmt(Math.max(0, change));
    el.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
    const changeBox = el.closest('.change-display');
    if (changeBox) {
        changeBox.style.background = change >= 0 ? 'var(--green-light)' : 'var(--red-light)';
    }
    const changeLabel = document.querySelector('.change-label');
    if (changeLabel) changeLabel.style.color = change >= 0 ? 'var(--green)' : 'var(--red)';
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
    const btn     = document.getElementById('customerBtn');
    const textEl  = document.getElementById('customerBtnText');
    const clearEl = document.getElementById('customerClearBtn');
    if (!selectedCustomer || selectedCustomer.id === null) {
        btn.classList.remove('has-customer');
        btn.style.cssText = '';
        textEl.textContent = '🚶 ' + walkinCustomerName;
        clearEl.style.display = 'none';
    } else {
        btn.classList.add('has-customer');
        btn.style.cssText = '';
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

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

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
                <div style="font-size:12px;color:var(--text3)">${c.code} ${c.phone ? '· '+c.phone : ''}</div>
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

function clearCustomer(e) {
    if (e) e.stopPropagation();
    setWalkin();
}

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
// Kunci idempoten: satu nilai untuk satu isi keranjang, dipakai ulang oleh setiap
// percobaan kirim, dan baru diganti setelah checkout benar-benar berhasil. Dengan
// begitu klik dobel atau kirim ulang setelah koneksi putus tetap menghasilkan satu
// transaksi — server mengenalinya sebagai percobaan yang sama.
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
    const paid = selectedPaymentIsCash ? parseFloat(document.getElementById('paidAmount').value) || 0 : total;
    if (selectedPaymentIsCash && paid < total) { toast('Nominal bayar kurang!', 'err'); return; }

    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    document.getElementById('checkoutBtnText').innerHTML = '<span class="spinner"></span> Memproses...';

    // Dibuat sekali per keranjang; percobaan kirim berikutnya memakai nilai yang sama.
    if (!checkoutKey) checkoutKey = newCheckoutKey();

    const discAmt = parseFloat(document.getElementById('discountAmt').value) || 0;
    const discPct = parseFloat(document.getElementById('discountPct').value) || 0;
    const tableNumber = document.getElementById('tableNumber').value.trim();

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
            // Kunci dilepas hanya setelah berhasil, supaya keranjang berikutnya
            // memakai kunci baru.
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
        ${parseFloat(tx.discount_amount) > 0 ? `<div class="receipt-row"><span>Diskon</span><span>- Rp ${fmt(tx.discount_amount)}</span></div>` : ''}
        ${tx.coupon_code && parseFloat(tx.coupon_discount||0) > 0 ? `<div class="receipt-row" style="color:var(--green)"><span>Kupon (${tx.coupon_code})</span><span>- Rp ${fmt(tx.coupon_discount)}</span></div>` : ''}
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

function printKitchen() {
    if (!lastReceipt) return;
    const table = document.getElementById('tableNumber').value.trim();
    const q = table ? `?table=${encodeURIComponent(table)}` : '';
    window.open(`{{ url('pos/print-kitchen') }}/${lastReceipt.id}${q}`, '_blank', 'width=400,height=600');
}

function closeReceiptAndReset() {
    closeModal('receiptModal');
    clearCart();
    setWalkin();
    document.getElementById('paidAmount').value = '';
    document.getElementById('discountAmt').value = '';
    document.getElementById('discountPct').value = '';
    // Reset delivery platform
    selectedDeliveryPlatform = null;
    document.querySelectorAll('.platform-btn').forEach(b => b.classList.remove('active'));
}

// ============================================================
// CATEGORIES SCROLL
// ============================================================
const catsBar = document.getElementById('categoriesBar');
function updateCatArrows() {
    const atStart = catsBar.scrollLeft <= 4;
    const atEnd   = catsBar.scrollLeft + catsBar.clientWidth >= catsBar.scrollWidth - 4;
    document.getElementById('catArrowLeft').classList.toggle('hidden', atStart);
    document.getElementById('catArrowRight').classList.toggle('hidden', atEnd);
}
function scrollCats(dir) { catsBar.scrollBy({ left: dir * 200, behavior: 'smooth' }); }
catsBar.addEventListener('scroll', updateCatArrows);
setTimeout(updateCatArrows, 100);

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
    setTimeout(()=>{ t.style.transition='.3s'; t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3000);
}

// Initial
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
