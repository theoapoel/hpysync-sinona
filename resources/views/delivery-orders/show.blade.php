@extends('layouts.app')
@section('title', 'Detail Delivery Order')

@section('content')
@php
    $statusColor    = ['draft'=>'badge-gray','confirmed'=>'badge-blue','delivering'=>'badge-yellow','completed'=>'badge-green','cancelled'=>'badge-red'][$order->status] ?? 'badge-gray';
    $payStatusColor = ['unpaid'=>'badge-red','partial'=>'badge-yellow','paid'=>'badge-green'][$order->payment_status] ?? 'badge-gray';
    $payStatusLabel = ['unpaid'=>'Belum Lunas','partial'=>'DP / Sebagian','paid'=>'Lunas'][$order->payment_status] ?? '-';
    $canEdit    = in_array($order->status, ['draft','confirmed']);
    $canConfirm = $order->status === 'draft';
    $canCancel  = !in_array($order->status, ['completed','cancelled']);
    $totalPaid  = $order->payments->sum('amount');
    $outstanding = max(0, $order->total - $totalPaid);
@endphp

<div class="page-header">
    <div>
        <div style="display:flex;align-items:center;gap:10px">
            <a href="{{ route('delivery-orders.index') }}" class="btn btn-ghost btn-sm">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <div class="page-title"><i class="fas fa-truck text-blue"></i> {{ $order->order_no }}</div>
                <div class="page-subtitle">{{ ($order->order_date ?? $order->created_at)->isoFormat('dddd, D MMMM Y') }}</div>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <span class="badge {{ $statusColor }}" style="font-size:13px;padding:6px 14px">{{ strtoupper($order->status) }}</span>
        <span class="badge {{ $payStatusColor }}" style="font-size:12px;padding:5px 12px"><i class="fas fa-money-bill-wave" style="margin-right:4px"></i>{{ $payStatusLabel }}</span>
        @if($order->bill_as_receivable)
        <span class="badge badge-blue" style="font-size:12px;padding:5px 12px" title="Invoice HPYERP terbit saat konfirmasi walau belum dibayar"><i class="fas fa-file-invoice-dollar" style="margin-right:4px"></i>Piutang (AR)</span>
        @endif
        <button type="button" id="thermalSlipBtn" class="btn btn-ghost" title="Kirim slip Gudang + QC langsung ke thermal printer"
                onclick="thermalPrintSlip(this, '{{ route('delivery-orders.direct-print-slip', $order) }}')">
            <i class="fas fa-receipt"></i> Print Thermal
        </button>
        <a href="{{ route('delivery-orders.proforma', $order) }}" target="_blank" class="btn btn-ghost" title="Cetak Proforma Invoice">
            <i class="fas fa-file-invoice"></i> Proforma
        </a>
        <a href="{{ route('delivery-orders.invoice', $order) }}" target="_blank" class="btn btn-ghost" title="Cetak Invoice">
            <i class="fas fa-file-invoice-dollar"></i> Invoice
        </a>
        @if($canConfirm)
        <form method="POST" action="{{ route('delivery-orders.confirm', $order) }}" style="display:inline" onsubmit="return confirm('Konfirmasi order ini?')">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check"></i> Konfirmasi
            </button>
        </form>
        @endif
        @if($canCancel)
        <form method="POST" action="{{ route('delivery-orders.cancel', $order) }}" style="display:inline" onsubmit="return confirm('Batalkan order ini?')">
            @csrf
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-times"></i> Batalkan
            </button>
        </form>
        @endif
        @if($order->erp_sync_status !== 'synced' && $order->status !== 'cancelled')
        <button onclick="syncSalesOrder()" id="syncSoBtn" class="btn btn-ghost">
            <i class="fas fa-sync-alt"></i> Sync SO ERP
        </button>
        @else
            @if($order->erp_sales_order)
            <span class="badge badge-green"><i class="fas fa-check-circle"></i> {{ $order->erp_sales_order }}</span>
            @endif
        @endif
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

    {{-- LEFT: Order Items + Shipments --}}
    <div style="display:flex;flex-direction:column;gap:20px">

        {{-- Order Items --}}
        <div class="card">
            <div class="card-header" style="padding:16px 20px;border-bottom:1px solid var(--border)">
                <h3 style="font-size:15px;font-weight:600;margin:0">Item Order</h3>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr>
                        <th>Produk</th><th>SKU</th><th style="text-align:right">Harga</th>
                        <th style="text-align:right">Qty</th><th style="text-align:right">Subtotal</th>
                    </tr></thead>
                    <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td style="font-weight:500">{{ $item->product_name }}</td>
                        <td class="text-muted text-xs">{{ $item->product_sku ?? '—' }}</td>
                        <td style="text-align:right" class="money">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                        <td style="text-align:right">{{ $item->qty }}</td>
                        <td style="text-align:right;font-weight:600" class="money">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr style="background:var(--surface2)">
                        <td colspan="4" style="text-align:right;font-weight:700;padding:12px 16px">Total Order</td>
                        <td style="text-align:right;font-weight:700;font-size:15px;padding:12px 16px" class="money">
                            Rp {{ number_format($order->total, 0, ',', '.') }}
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Shipments --}}
        <div>
            <h3 style="font-size:15px;font-weight:600;margin:0 0 12px">
                Tujuan Pengiriman
                <span class="badge badge-gray" style="margin-left:6px">{{ $order->shipments->count() }} tujuan</span>
            </h3>
            @foreach($order->shipments as $ship)
            @php
                $sColor = ['pending'=>'badge-gray','delivered'=>'badge-green'][$ship->status] ?? 'badge-gray';
            @endphp
            <div class="card" style="margin-bottom:16px">
                <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-weight:600;font-size:14px">
                            <span class="badge badge-gray" style="margin-right:8px">#{{ $ship->sequence }}</span>
                            {{ $ship->recipient_name }}
                            @if($ship->recipient_phone)
                                <span class="text-muted text-xs" style="margin-left:6px"><i class="fas fa-phone"></i> {{ $ship->recipient_phone }}</span>
                            @endif
                        </div>
                        <div class="text-muted text-xs" style="margin-top:4px"><i class="fas fa-map-marker-alt"></i> {{ $ship->shipping_address }}</div>
                        @if($ship->delivery_date)
                        <div class="text-muted text-xs" style="margin-top:2px">
                            <i class="fas fa-calendar-alt"></i> Pengiriman: {{ $ship->delivery_date->isoFormat('D MMMM Y') }}
                            @if($ship->delivery_date->format('H:i') !== '00:00')
                            <span style="margin-left:4px"><i class="fas fa-clock"></i> {{ $ship->delivery_date->format('H:i') }}</span>
                            @endif
                        </div>
                        @endif
                        @if($ship->notes)
                        <div class="text-muted text-xs" style="margin-top:2px"><i class="fas fa-sticky-note"></i> {{ $ship->notes }}</div>
                        @endif
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                        <span class="badge {{ $sColor }}">{{ strtoupper($ship->status) }}</span>
                        @if($ship->erp_sync_status === 'synced' && $ship->erp_delivery_note)
                            <span class="badge badge-green text-xs"><i class="fas fa-check-circle"></i> {{ $ship->erp_delivery_note }}</span>
                        @elseif($ship->erp_sync_status === 'failed')
                            <span class="badge badge-red text-xs" title="{{ $ship->erp_sync_error }}">DN FAILED</span>
                        @else
                            <button onclick="syncDeliveryNote(this)"
                                data-url="{{ route('delivery-notes.sync-dn', $ship) }}"
                                class="btn btn-ghost btn-sm"
                                {{ $order->erp_sales_order ? '' : 'disabled title=Sync SO dulu' }}>
                                <i class="fas fa-sync-alt"></i> Sync DN
                            </button>
                        @endif
                        @if($ship->status !== 'delivered')
                        <button onclick="markDelivered(this)"
                            data-url="{{ route('delivery-notes.mark-delivered', $ship) }}"
                            class="btn btn-ghost btn-sm">
                            <i class="fas fa-check"></i> Terkirim
                        </button>
                        @endif
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr>
                            <th>Produk</th><th>SKU</th>
                            <th style="text-align:right">Harga</th>
                            <th style="text-align:right">Qty</th>
                            <th style="text-align:right">Subtotal</th>
                        </tr></thead>
                        <tbody>
                        @foreach($ship->items as $si)
                        <tr>
                            <td style="font-weight:500">{{ $si['product_name'] }}</td>
                            <td class="text-muted text-xs">{{ $si['product_sku'] ?? '—' }}</td>
                            <td style="text-align:right" class="money">Rp {{ number_format($si['price'] ?? 0, 0, ',', '.') }}</td>
                            <td style="text-align:right">{{ $si['qty'] }}</td>
                            <td style="text-align:right;font-weight:600" class="money">Rp {{ number_format($si['subtotal'] ?? 0, 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr style="background:var(--surface2)">
                            <td colspan="4" style="text-align:right;font-weight:600;padding:10px 16px">Total Tujuan</td>
                            <td style="text-align:right;font-weight:700;padding:10px 16px" class="money">
                                Rp {{ number_format($ship->total, 0, ',', '.') }}
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                @if($ship->delivered_at)
                <div style="padding:10px 20px;font-size:12px;color:var(--green);border-top:1px solid var(--border)">
                    <i class="fas fa-check-circle"></i> Terkirim: {{ $ship->delivered_at->isoFormat('D MMM Y HH:mm') }}
                </div>
                @endif
            </div>
            @endforeach
        </div>

    </div>

    {{-- RIGHT: Info sidebar --}}
    <div style="display:flex;flex-direction:column;gap:16px">

        {{-- Customer --}}
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">Customer</h4>
            </div>
            <div class="card-body" style="padding:14px 16px">
                <div style="font-weight:600;font-size:14px">{{ $order->customer?->name ?? '-' }}</div>
                @if($order->customer?->phone)
                <div class="text-muted text-xs" style="margin-top:4px"><i class="fas fa-phone"></i> {{ $order->customer->phone }}</div>
                @endif
            </div>
        </div>

        {{-- Billing Address --}}
        @if($order->billing_address)
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">Alamat Penagihan</h4>
            </div>
            <div class="card-body" style="padding:14px 16px;font-size:13px;line-height:1.5">
                {{ $order->billing_address }}
            </div>
        </div>
        @endif

        {{-- Jadwal Produksi --}}
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">
                    <i class="fas fa-calendar-alt" style="margin-right:6px;color:var(--blue)"></i>Jadwal Produksi
                </h4>
            </div>
            <div class="card-body" style="padding:14px 16px">

                {{-- Status saat ini --}}
                @if($order->kitchen_scheduled_at)
                <div style="background:var(--surface2);border-radius:8px;padding:12px;margin-bottom:12px">
                    <div style="font-size:12px;color:var(--text3);margin-bottom:4px">Dijadwalkan masuk dapur:</div>
                    <div style="font-size:15px;font-weight:700;color:var(--blue)">
                        <i class="fas fa-clock"></i>
                        {{ $order->kitchen_scheduled_at->isoFormat('dddd, D MMMM Y') }}
                    </div>
                    <div style="font-size:13px;color:var(--text2);margin-top:2px">
                        Pukul {{ $order->kitchen_scheduled_at->format('H:i') }} WIB
                    </div>
                    @if($order->kitchen_scheduled_at->isFuture())
                    <div style="margin-top:8px">
                        <span class="badge badge-yellow"><i class="fas fa-hourglass-half"></i> Menunggu jadwal</span>
                    </div>
                    @else
                    <div style="margin-top:8px">
                        <span class="badge badge-green"><i class="fas fa-check-circle"></i> Sudah aktif di dapur</span>
                    </div>
                    @endif
                </div>
                @else
                <div class="text-muted text-sm" style="margin-bottom:12px;padding:8px;background:var(--surface2);border-radius:6px">
                    <i class="fas fa-info-circle"></i> Belum ada jadwal produksi.
                    @if($order->status === 'confirmed')
                    Order sudah masuk antrian dapur secara langsung.
                    @endif
                </div>
                @endif

                {{-- Form set jadwal --}}
                @if($order->status !== 'cancelled')
                <form method="POST" action="{{ route('delivery-orders.schedule', $order) }}">
                    @csrf
                    <div class="form-group" style="margin-bottom:8px">
                        <label class="form-label" style="font-size:12px">
                            {{ $order->kitchen_scheduled_at ? 'Ubah Jadwal' : 'Set Jadwal Produksi' }}
                        </label>
                        @php
                            $schH = $order->kitchen_scheduled_at ? $order->kitchen_scheduled_at->format('H') : '08';
                            $schM = $order->kitchen_scheduled_at ? $order->kitchen_scheduled_at->format('i') : '00';
                        @endphp
                        <div style="display:grid;grid-template-columns:1fr auto auto auto;gap:6px;align-items:center">
                            <input type="date" name="kitchen_scheduled_date" class="form-control"
                                style="font-size:13px"
                                value="{{ $order->kitchen_scheduled_at ? $order->kitchen_scheduled_at->format('Y-m-d') : now()->addDay()->format('Y-m-d') }}"
                                required>
                            <select name="kitchen_scheduled_hour" class="form-control form-select" style="font-size:13px;width:68px" required>
                                @for($h = 0; $h < 24; $h++)
                                @php $hh = str_pad($h, 2, '0', STR_PAD_LEFT); @endphp
                                <option value="{{ $hh }}" {{ $schH === $hh ? 'selected' : '' }}>{{ $hh }}</option>
                                @endfor
                            </select>
                            <span style="font-weight:700;color:var(--text2)">:</span>
                            <select name="kitchen_scheduled_minute" class="form-control form-select" style="font-size:13px;width:68px" required>
                                @foreach(['00','05','10','15','20','25','30','35','40','45','50','55'] as $mm)
                                <option value="{{ $mm }}" {{ $schM === $mm ? 'selected' : '' }}>{{ $mm }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="font-size:11px;color:var(--text3);margin-top:4px">
                            Order akan otomatis masuk antrian dapur pada waktu yang ditentukan.
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline w-full" style="font-size:13px">
                        <i class="fas fa-calendar-check"></i>
                        {{ $order->kitchen_scheduled_at ? 'Perbarui Jadwal' : 'Simpan Jadwal' }}
                    </button>
                </form>
                @endif

            </div>
        </div>

        {{-- Payment Card --}}
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">
                    <i class="fas fa-money-bill-wave" style="margin-right:6px;color:var(--green)"></i>Pembayaran
                </h4>
            </div>
            <div class="card-body" style="padding:14px 16px">

                {{-- Ringkasan --}}
                <div style="background:var(--surface2);border-radius:8px;padding:12px;margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                        <span class="text-muted">Total Order</span>
                        <span class="font-medium money">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
                        <span class="text-muted">Sudah Dibayar</span>
                        <span class="font-medium money" style="color:var(--green)">Rp {{ number_format($totalPaid, 0, ',', '.') }}</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:14px;border-top:1px solid var(--border);padding-top:8px;margin-top:4px">
                        <span class="font-bold">Sisa Tagihan</span>
                        <span class="font-bold money" style="color:{{ $outstanding > 0 ? 'var(--red)' : 'var(--green)' }}">
                            Rp {{ number_format($outstanding, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Daftar Payment --}}
                @forelse($order->payments as $pmt)
                <div style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px">
                    <div style="display:flex;justify-content:space-between;align-items:start">
                        <div>
                            <div style="font-size:13px;font-weight:600">
                                <i class="fas {{ $pmt->methodIcon() }}" style="margin-right:4px;color:var(--blue)"></i>
                                {{ $pmt->methodLabel() }}
                            </div>
                            <div class="text-muted" style="font-size:12px;margin-top:2px">
                                {{ $pmt->payment_date->isoFormat('D MMM Y') }}
                                @if($pmt->reference_no)
                                    &bull; Ref: {{ $pmt->reference_no }}
                                @endif
                            </div>
                            @if($pmt->notes)
                            <div class="text-muted" style="font-size:11px;margin-top:2px">{{ $pmt->notes }}</div>
                            @endif
                        </div>
                        <div style="text-align:right;flex-shrink:0;margin-left:8px">
                            <div class="money font-bold" style="font-size:14px;color:var(--green)">
                                Rp {{ number_format($pmt->amount, 0, ',', '.') }}
                            </div>
                            <div style="margin-top:4px;display:flex;gap:4px;justify-content:flex-end;align-items:center">
                                @if($pmt->erp_sync_status === 'synced')
                                    <span class="badge badge-green" style="font-size:10px"><i class="fas fa-check-circle"></i> {{ $pmt->erp_payment_entry }}</span>
                                @elseif($pmt->erp_sync_status === 'failed')
                                    <span class="badge badge-red" style="font-size:10px" title="{{ $pmt->erp_sync_error }}">PE FAILED</span>
                                    @if($order->erp_sales_order)
                                    <button onclick="syncPayment(this)"
                                        data-url="{{ route('delivery-orders.payments.sync', [$order, $pmt]) }}"
                                        class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    @endif
                                @else
                                    @if($order->erp_sales_order)
                                    <button onclick="syncPayment(this)"
                                        data-url="{{ route('delivery-orders.payments.sync', [$order, $pmt]) }}"
                                        class="btn btn-ghost btn-sm" style="padding:2px 6px;font-size:11px"
                                        title="Sync ke ERP">
                                        <i class="fas fa-sync-alt"></i> Sync PE
                                    </button>
                                    @else
                                    <span class="badge badge-gray" style="font-size:10px">Belum Sync</span>
                                    @endif
                                @endif
                                @if($pmt->erp_sync_status !== 'synced' && $order->status !== 'cancelled')
                                <form method="POST"
                                    action="{{ route('delivery-orders.payments.destroy', [$order, $pmt]) }}"
                                    onsubmit="return confirm('Hapus payment ini?')" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" style="padding:2px 6px;color:var(--red);font-size:11px">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-muted text-sm" style="text-align:center;padding:8px 0">Belum ada payment.</div>
                @endforelse

                {{-- Form Tambah Payment --}}
                @if($order->status !== 'cancelled')
                <div style="margin-top:12px;border-top:1px solid var(--border);padding-top:12px">
                    <div style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">
                        + Tambah Payment
                    </div>
                    <form method="POST" action="{{ route('delivery-orders.payments.store', $order) }}">
                        @csrf
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label" style="font-size:12px">Metode</label>
                            <select name="payment_method" class="form-control form-select" style="font-size:13px" required>
                                @foreach($mopList as $mop)
                                <option value="{{ $mop }}">{{ $mop }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label" style="font-size:12px">Jumlah (Rp) *</label>
                            <input type="number" name="amount" class="form-control" required
                                min="1" step="1" style="font-size:13px"
                                placeholder="{{ number_format($outstanding, 0, ',', '.') }}"
                                value="{{ $outstanding > 0 ? (int)$outstanding : '' }}">
                        </div>
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label" style="font-size:12px">Tanggal</label>
                            <input type="date" name="payment_date" class="form-control" required
                                style="font-size:13px" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div class="form-group" style="margin-bottom:8px">
                            <label class="form-label" style="font-size:12px">No. Referensi</label>
                            <input type="text" name="reference_no" class="form-control"
                                style="font-size:13px" placeholder="Nomor transfer / kode unik">
                        </div>
                        <div class="form-group" style="margin-bottom:10px">
                            <label class="form-label" style="font-size:12px">Catatan</label>
                            <input type="text" name="notes" class="form-control"
                                style="font-size:13px" placeholder="Opsional">
                        </div>
                        <button type="submit" class="btn btn-primary w-full" style="font-size:13px">
                            <i class="fas fa-plus"></i> Simpan Payment
                        </button>
                    </form>
                </div>
                @endif

            </div>
        </div>

        {{-- ERP Sync Status --}}
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">ERP Sync</h4>
            </div>
            <div class="card-body" style="padding:14px 16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span class="text-xs text-muted">Sales Order</span>
                    @if($order->erp_sales_order)
                        <span class="badge badge-green">{{ $order->erp_sales_order }}</span>
                    @elseif($order->erp_sync_status === 'failed')
                        <span class="badge badge-red">FAILED</span>
                    @else
                        <span class="badge badge-gray">BELUM SYNC</span>
                    @endif
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span class="text-xs text-muted">Sales Invoice</span>
                    @if($order->erp_sales_invoice)
                        <span class="badge badge-green">{{ $order->erp_sales_invoice }}</span>
                    @elseif($order->erp_si_sync_error)
                        <span class="badge badge-red">FAILED</span>
                    @elseif($order->bill_as_receivable)
                        {{-- Order AR: invoice terbit saat konfirmasi. --}}
                        <span class="badge badge-gray">{{ $order->status === 'draft' ? 'TERBIT SAAT KONFIRMASI' : 'BELUM TERBIT' }}</span>
                    @else
                        {{-- Invoice sengaja ditahan sampai ada pembayaran pertama. --}}
                        <span class="badge badge-gray">MENUNGGU BAYAR</span>
                    @endif
                </div>
                @if($order->erp_si_sync_error)
                <div class="alert" style="background:#FEF3E2;color:#B45309;border-radius:6px;padding:8px 10px;font-size:12px;margin-top:6px">
                    <i class="fas fa-exclamation-triangle"></i> {{ $order->erp_si_sync_error }}
                </div>
                @endif
                @if($order->erp_sync_error)
                <div class="alert" style="background:#FEF3E2;color:#B45309;border-radius:6px;padding:8px 10px;font-size:12px;margin-top:6px">
                    <i class="fas fa-exclamation-triangle"></i> {{ $order->erp_sync_error }}
                </div>
                @endif
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
                    <span class="text-xs text-muted">Delivery Notes</span>
                    @php
                        $syncedDn = $order->shipments->where('erp_sync_status','synced')->count();
                        $totalShip = $order->shipments->count();
                    @endphp
                    <span class="badge {{ $syncedDn === $totalShip && $totalShip > 0 ? 'badge-green' : 'badge-gray' }}">
                        {{ $syncedDn }}/{{ $totalShip }} synced
                    </span>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        @if($order->notes)
        <div class="card">
            <div class="card-header" style="padding:14px 16px;border-bottom:1px solid var(--border)">
                <h4 style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;color:var(--text3)">Catatan</h4>
            </div>
            <div class="card-body" style="padding:14px 16px;font-size:13px;line-height:1.5;color:var(--text2)">
                {{ $order->notes }}
            </div>
        </div>
        @endif

        {{-- Meta --}}
        <div class="card">
            <div class="card-body" style="padding:14px 16px;font-size:12px;color:var(--text3)">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span>Dibuat oleh</span>
                    <span style="font-weight:500;color:var(--text2)">{{ $order->creator?->name ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between">
                    <span>Tanggal buat</span>
                    <span style="font-weight:500;color:var(--text2)">{{ $order->created_at->isoFormat('D MMM Y HH:mm') }}</span>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
function thermalPrintSlip(btn, url) {
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim…';

    fetch(url)
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Gagal mencetak ke printer.');
            }
            alert(data.message || 'Slip berhasil dikirim ke printer.');
        })
        .catch((err) => alert('❌ ' + err.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = original;
        });
}

async function syncSalesOrder() {
    const btn = document.getElementById('syncSoBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Sync...';

    try {
        const res = await api.post('{{ route("delivery-orders.sync-so", $order) }}');
        if (res.success) {
            toast('Sales Order berhasil dibuat: ' + res.sales_order, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            toast('Sync gagal: ' + (res.error ?? 'Unknown error'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync SO ERP';
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync SO ERP';
    }
}

async function syncDeliveryNote(btn) {
    const url = btn.dataset.url;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
        const res = await api.post(url);
        if (res.success) {
            toast('Delivery Note dibuat: ' + res.delivery_note, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            toast('Sync DN gagal: ' + (res.error ?? 'Unknown error'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync DN';
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Sync DN';
    }
}

async function syncPayment(btn) {
    const url = btn.dataset.url;
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
        const res = await api.post(url);
        if (res.success) {
            toast('Payment Entry dibuat: ' + res.payment_entry, 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            toast('Sync Payment gagal: ' + (res.error ?? 'Unknown error'), 'error');
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = orig;
    }
}

async function markDelivered(btn) {
    if (!confirm('Tandai pengiriman ini sebagai terkirim?')) return;
    const url = btn.dataset.url;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
        const res = await api.post(url);
        if (res.success) {
            toast('Ditandai terkirim.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            toast(res.error ?? 'Gagal.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Terkirim';
        }
    } catch(e) {
        toast('Error: ' + e.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Terkirim';
    }
}
</script>
@endpush
