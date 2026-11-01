@extends('layouts.app')
@section('title', 'Struk ' . $transaction->invoice_no)

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Struk Pembayaran</div>
        <div class="page-subtitle">{{ $transaction->invoice_no }}</div>
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('pos.print-kitchen', $transaction) }}" target="_blank" class="btn btn-warning">
            <i class="fas fa-utensils"></i> Cetak Kitchen
        </a>
        <a href="{{ route('pos.print', $transaction) }}" target="_blank" class="btn btn-primary">
            <i class="fas fa-print"></i> Cetak Struk
        </a>
        <a href="{{ route('pos.index') }}" class="btn btn-success">
            <i class="fas fa-plus"></i> Transaksi Baru
        </a>
    </div>
</div>

<div style="max-width:480px;margin:0 auto">
    <div class="card">
        <div class="card-body" style="padding:32px">
            <!-- Header -->
            <div style="text-align:center;margin-bottom:24px">
                @if($store['store_logo'])
                <img src="{{ asset($store['store_logo']) }}"
                    style="max-height:72px;max-width:200px;object-fit:contain;display:block;margin:0 auto 12px">
                @else
                <div style="width:64px;height:64px;background:#E6F4EA;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:28px">
                    ✅
                </div>
                @endif
                <div style="font-family:'Google Sans',sans-serif;font-size:20px;font-weight:700;color:var(--text)">
                    {{ $store['store_name'] }}
                </div>
                @if($store['store_tagline'])
                <div style="color:var(--text3);font-size:13px;">{{ $store['store_tagline'] }}</div>
                @endif
                @if($store['store_address'])
                <div style="color:var(--text3);font-size:12px;margin-top:2px;">{{ $store['store_address'] }}</div>
                @endif
                @if($store['store_phone'])
                <div style="color:var(--text3);font-size:12px;">Telp: {{ $store['store_phone'] }}</div>
                @endif
                <div style="font-family:'Google Sans',sans-serif;font-size:16px;font-weight:700;color:#34A853;margin-top:12px;">
                    Pembayaran Berhasil!
                </div>
                <div style="color:var(--text3);font-size:14px;margin-top:4px">
                    {{ local_dt($transaction->created_at, 'd F Y, H:i') }}
                </div>
            </div>

            <!-- Invoice Info -->
            <div style="background:var(--surface2);border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:13px;color:var(--text3)">No. Invoice</span>
                    <span style="font-weight:700;font-family:'Google Sans',sans-serif">{{ $transaction->invoice_no }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                    <span style="font-size:13px;color:var(--text3)">Kasir</span>
                    <span style="font-weight:500">{{ $transaction->user->name }}</span>
                </div>
                @if($transaction->customer)
                <div style="display:flex;justify-content:space-between">
                    <span style="font-size:13px;color:var(--text3)">Customer</span>
                    <span style="font-weight:500">{{ $transaction->customer->name }}</span>
                </div>
                @endif
            </div>

            <!-- Items -->
            <div style="margin-bottom:20px">
                <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">
                    Item Pembelian
                </div>
                @foreach($transaction->items as $item)
                <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--surface2)">
                    <div>
                        <div style="font-size:14px;font-weight:500">{{ $item->product_name }}</div>
                        <div style="font-size:12px;color:var(--text3)">
                            {{ $item->quantity }} × Rp {{ number_format($item->price, 0, ',', '.') }}
                        </div>
                    </div>
                    <div style="font-family:'Google Sans',sans-serif;font-weight:700;color:var(--text)">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Totals -->
            <div style="background:var(--blue-light);border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px">
                    <span style="color:var(--text2)">Subtotal</span>
                    <span>Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($transaction->discount_amount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;color:var(--red)">
                    <span>Diskon</span>
                    <span>− Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($transaction->coupon_code && $transaction->coupon_discount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;color:var(--green)">
                    <span>Kupon ({{ $transaction->coupon_code }})</span>
                    <span>− Rp {{ number_format($transaction->coupon_discount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($transaction->tax_amount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px">
                    <span style="color:var(--text2)">Pajak</span>
                    <span>Rp {{ number_format($transaction->tax_amount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($transaction->service_charge_amount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;color:#E65100">
                    <span>Service Charge ({{ $transaction->service_charge_pct }}%)</span>
                    <span>Rp {{ number_format($transaction->service_charge_amount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($transaction->pb1_amount > 0)
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;color:#E65100">
                    <span>PB1 ({{ $transaction->pb1_pct }}%)</span>
                    <span>Rp {{ number_format($transaction->pb1_amount, 0, ',', '.') }}</span>
                </div>
                @endif
                <div style="display:flex;justify-content:space-between;font-family:'Google Sans',sans-serif;font-size:20px;font-weight:700;border-top:2px solid rgba(66,133,244,.2);padding-top:10px;margin-top:6px">
                    <span>TOTAL</span>
                    <span style="color:var(--blue)">Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Payment -->
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
                <span style="color:var(--text2)">Metode Bayar</span>
                <span class="badge badge-blue">{{ strtoupper($transaction->payment_method) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px">
                <span style="color:var(--text2)">Jumlah Bayar</span>
                <span style="font-weight:600">Rp {{ number_format($transaction->paid_amount, 0, ',', '.') }}</span>
            </div>
            @if($transaction->change_amount > 0)
            <div style="display:flex;justify-content:space-between;font-size:16px;font-weight:700;color:var(--green)">
                <span>Kembalian</span>
                <span>Rp {{ number_format($transaction->change_amount, 0, ',', '.') }}</span>
            </div>
            @endif

            <hr style="margin:20px 0;border:none;border-top:1px dashed var(--border)">
            @if($store['receipt_footer'])
            <div style="text-align:center;color:var(--text3);font-size:13px">
                {{ $store['receipt_footer'] }}
            </div>
            @endif
            <div style="text-align:center;color:var(--text3);font-size:11px;margin-top:6px">
                Powered by HPY Solution
            </div>
        </div>
    </div>
</div>
@endsection
