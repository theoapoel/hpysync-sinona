<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryShipment;
use App\Models\Product;
use App\Services\ErpNextService;
use App\Services\ThermalPrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryOrder::with('customer', 'creator')
            ->orderByDesc('delivery_date')
            ->orderByDesc('id');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from) {
            $query->whereDate('delivery_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('delivery_date', '<=', $request->date_to);
        }
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_no', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', '%' . $request->search . '%'));
            });
        }

        $orders = $query->paginate(20)->withQueryString();
        return view('delivery-orders.index', compact('orders'));
    }

    public function create()
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'address']);
        $products  = Product::where('is_active', true)->inItemGroups('delivery_item_groups')->orderBy('name')->get(['id', 'name', 'sku', 'price', 'erp_item_code']);
        return view('delivery-orders.create', compact('customers', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'                    => 'required|exists:customers,id',
            'order_date'                     => 'required|date',
            'billing_address'                => 'nullable|string|max:500',
            'notes'                          => 'nullable|string|max:1000',
            'bill_as_receivable'             => 'nullable|boolean',
            'items'                          => 'required|array|min:1',
            'items.*.product_name'           => 'required|string',
            'items.*.qty'                    => 'required|numeric|min:0.01',
            'items.*.price'                  => 'required|numeric|min:0',
            'shipments'                      => 'required|array|min:1',
            'shipments.*.recipient_name'     => 'required|string|max:200',
            'shipments.*.recipient_phone'    => 'nullable|string|max:30',
            'shipments.*.shipping_address'   => 'required|string|max:500',
            'shipments.*.delivery_date'      => 'required|date',
            // Jam dikirim form sebagai "08"; aturan `integer` menolak angka
            // berawalan nol, sehingga jam 00–09 selalu gagal validasi.
            'shipments.*.delivery_hour'      => 'nullable|numeric|between:0,23',
            'shipments.*.delivery_minute'    => 'nullable|in:00,05,10,15,20,25,30,35,40,45,50,55',
            'shipments.*.notes'              => 'nullable|string|max:500',
            'shipments.*.items'              => 'required|array|min:1',
        ]);

        // Validate that shipment item qtys match order item qtys exactly
        $orderQtyMap = [];
        foreach ($request->items as $item) {
            $name = $item['product_name'] ?? '';
            if ($name) $orderQtyMap[$name] = ($orderQtyMap[$name] ?? 0) + (float)$item['qty'];
        }
        $shippedQtyMap = [];
        foreach ($request->shipments as $ship) {
            foreach ($ship['items'] ?? [] as $si) {
                $name = $si['product_name'] ?? '';
                $qty  = (float)($si['qty'] ?? 0);
                if ($name && $qty > 0) $shippedQtyMap[$name] = ($shippedQtyMap[$name] ?? 0) + $qty;
            }
        }
        $allocationErrors = [];
        foreach ($orderQtyMap as $name => $orderQty) {
            $shippedQty = $shippedQtyMap[$name] ?? 0;
            if (abs($shippedQty - $orderQty) >= 0.001) {
                $diff = $shippedQty - $orderQty;
                $allocationErrors[] = $diff < 0
                    ? "\"$name\": dipesan $orderQty, baru dialokasikan $shippedQty (kurang " . ($orderQty - $shippedQty) . ")"
                    : "\"$name\": dipesan $orderQty, kelebihan " . ($shippedQty - $orderQty);
            }
        }
        foreach (array_keys($shippedQtyMap) as $name) {
            if (!isset($orderQtyMap[$name])) {
                $allocationErrors[] = "\"$name\" ada di pengiriman tapi tidak ada di item order";
            }
        }
        if (!empty($allocationErrors)) {
            return back()->withInput()->withErrors([
                'shipments' => 'Alokasi item pengiriman tidak sesuai: ' . implode('; ', $allocationErrors),
            ]);
        }

        // Harga hanya boleh diubah pada order AR. Order biasa selalu memakai harga
        // produk saat ini, apa pun yang dikirim form. Item per tujuan mengikuti
        // harga Item Order (dicocokkan per nama produk) supaya total selalu sinkron.
        $billAsReceivable = $request->boolean('bill_as_receivable');
        $productPrices = Product::whereIn('id', collect($request->items)->pluck('product_id')->filter())
            ->pluck('price', 'id');
        $orderItems = collect($request->items)->map(function ($row) use ($billAsReceivable, $productPrices) {
            $pid = $row['product_id'] ?? null;
            if (! $billAsReceivable && $pid && isset($productPrices[$pid])) {
                $row['price'] = (float) $productPrices[$pid];
            }
            return $row;
        })->values();
        $priceByName = $orderItems->pluck('price', 'product_name');

        DB::transaction(function () use ($request, $orderItems, $priceByName, $billAsReceivable) {
            // Set delivery_date on the order to the earliest shipment date (date only) for backward compat
            $earliestDeliveryDate = collect($request->shipments)
                ->pluck('delivery_date')
                ->filter()
                ->sort()
                ->first() ?? $request->order_date;

            // Jadwal Produksi otomatis mengikuti waktu pengiriman paling awal (tanggal + jam)
            $earliestDeliveryDatetime = collect($request->shipments)
                ->map(function ($ship) {
                    if (empty($ship['delivery_date'])) return null;
                    $h = str_pad($ship['delivery_hour'] ?? 0, 2, '0', STR_PAD_LEFT);
                    $m = $ship['delivery_minute'] ?? '00';
                    return \Carbon\Carbon::parse($ship['delivery_date'] . ' ' . $h . ':' . $m);
                })
                ->filter()
                ->sort()
                ->first();

            $order = DeliveryOrder::create([
                'order_no'             => DeliveryOrder::generateOrderNo(),
                'customer_id'          => $request->customer_id,
                'billing_address'      => $request->billing_address,
                'order_date'           => $request->order_date,
                'delivery_date'        => $earliestDeliveryDate,
                'kitchen_scheduled_at' => $earliestDeliveryDatetime,
                'notes'                => $request->notes,
                'bill_as_receivable'   => $billAsReceivable,
                'status'               => 'draft',
                'created_by'           => auth()->id(),
            ]);

            foreach ($orderItems as $row) {
                $subtotal = $row['price'] * $row['qty'];
                DeliveryOrderItem::create([
                    'delivery_order_id' => $order->id,
                    'product_id'        => $row['product_id'] ?? null,
                    'product_name'      => $row['product_name'],
                    'product_sku'       => $row['product_sku'] ?? null,
                    'price'             => $row['price'],
                    'qty'               => $row['qty'],
                    'subtotal'          => $subtotal,
                ]);
            }

            $order->recalculateTotal();

            foreach ($request->shipments as $i => $ship) {
                $shipItems = collect($ship['items'])->map(function ($si) use ($priceByName) {
                    $price = (float) ($priceByName[$si['product_name']] ?? $si['price'] ?? 0);
                    $qty   = (float) ($si['qty'] ?? 0);
                    return [
                        'product_name' => $si['product_name'],
                        'product_sku'  => $si['product_sku'] ?? null,
                        'qty'          => $qty,
                        'price'        => $price,
                        'subtotal'     => $price * $qty,
                    ];
                })->filter(fn($si) => $si['qty'] > 0)->values()->toArray();

                $shipTotal = collect($shipItems)->sum('subtotal');

                $deliveryDatetime = null;
                if (!empty($ship['delivery_date'])) {
                    $h = str_pad($ship['delivery_hour'] ?? 0, 2, '0', STR_PAD_LEFT);
                    $m = $ship['delivery_minute'] ?? '00';
                    $deliveryDatetime = \Carbon\Carbon::parse($ship['delivery_date'] . ' ' . $h . ':' . $m);
                }

                DeliveryShipment::create([
                    'delivery_order_id' => $order->id,
                    'sequence'          => $i + 1,
                    'recipient_name'    => $ship['recipient_name'],
                    'recipient_phone'   => $ship['recipient_phone'] ?? null,
                    'shipping_address'  => $ship['shipping_address'],
                    'delivery_date'     => $deliveryDatetime,
                    'notes'             => $ship['notes'] ?? null,
                    'items'             => $shipItems,
                    'total'             => $shipTotal,
                ]);
            }

            $this->order = $order;
        });

        return redirect()->route('delivery-orders.show', $this->order ?? DeliveryOrder::latest()->first())
            ->with('success', 'Delivery order ' . ($this->order->order_no ?? '') . ' berhasil dibuat.');
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load('customer', 'creator', 'items.product', 'shipments', 'payments.creator');

        // Daftar Mode of Payment dari ERP (cached 30 menit, clear dengan php artisan cache:clear)
        $mopList = [];
        try {
            $erp = new ErpNextService();
            if ($erp->isConfigured()) {
                $mopList = \Illuminate\Support\Facades\Cache::remember('erp_mode_of_payment_list', 1800, fn () => $erp->getModeOfPaymentList());
            }
        } catch (\Exception $e) {
            // silent — gunakan fallback
        }
        if (empty($mopList)) {
            $mopList = ['Cash', 'Transfer Bank', 'QRIS', 'Kartu'];
        }

        return view('delivery-orders.show', ['order' => $deliveryOrder, 'mopList' => $mopList]);
    }

    public function confirm(DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'draft') {
            return back()->with('error', 'Order sudah tidak dalam status draft.');
        }
        // Order dikonfirmasi → masuk Pulling Order. Belum masuk antrian dapur:
        // butuh konfirmasi jadwal terpisah di Pulling Order (kitchen_confirmed_at).
        $deliveryOrder->update([
            'status'         => 'confirmed',
            'kitchen_status' => null,
        ]);

        $syncMsg = '';
        try {
            $erp = new ErpNextService();
            if ($erp->isConfigured()) {
                $deliveryOrder->load('items.product', 'customer');

                // 1. Sync Sales Order
                $soResult = $erp->createSalesOrder($deliveryOrder);
                if ($soResult['success']) {
                    $syncMsg = ' SO ERP: ' . ($soResult['docname'] ?? 'synced') . '.';

                    // 2. Terbitkan Sales Invoice bila order sudah ada pembayarannya
                    // ATAU ditandai "tagih sebagai piutang (AR)". Order biasa tanpa
                    // pembayaran tidak ditagihkan dulu supaya tidak menumpuk piutang
                    // untuk order yang belum tentu jalan; invoicenya menyusul saat
                    // pembayaran pertama masuk.
                    $deliveryOrder->load('payments');
                    if ($deliveryOrder->bill_as_receivable || $deliveryOrder->payments->isNotEmpty()) {
                        $siResult = $erp->createSalesInvoice($deliveryOrder);
                        $syncMsg .= $siResult['success']
                            ? ' Invoice ERP: ' . ($siResult['docname'] ?? 'synced') . '.'
                            : ' (Buat invoice ERP gagal: ' . ($siResult['error'] ?? '') . ')';
                    }

                    // 3. Auto-sync semua payment yang belum disync
                    $paymentSyncFails = [];
                    foreach ($deliveryOrder->payments->where('erp_sync_status', '!=', 'synced') as $payment) {
                        $peResult = $erp->createPaymentEntry($payment);
                        if (!$peResult['success']) {
                            $paymentSyncFails[] = $peResult['error'] ?? 'unknown';
                        }
                    }
                    if (!empty($paymentSyncFails)) {
                        $syncMsg .= ' (Sync payment gagal: ' . implode('; ', $paymentSyncFails) . ')';
                    }

                    // 4. Picu produksi FG lewat Material Request (Manufacture).
                    //    Tanpa ini DO tak pernah memicu produksi -> stok FG 0 ->
                    //    Delivery Note mentok Draft.
                    $mrResult = $erp->createMaterialRequestForDeliveryOrder($deliveryOrder);
                    $syncMsg .= $mrResult['success']
                        ? ' MR produksi: ' . ($mrResult['docname'] ?? 'synced') . '.'
                        : ' (Buat MR produksi gagal: ' . ($mrResult['error'] ?? '') . ')';
                } else {
                    $syncMsg = ' (Sync SO ERP gagal: ' . ($soResult['error'] ?? '') . ')';
                }
            }
        } catch (\Exception $e) {
            // Silent — sync failure must not block confirmation
        }

        return back()->with('success', 'Order dikonfirmasi dan masuk antrian dapur.' . $syncMsg);
    }

    public function schedule(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status === 'cancelled') {
            return back()->with('error', 'Order sudah dibatalkan.');
        }

        $request->validate([
            'kitchen_scheduled_date'   => 'required|date',
            'kitchen_scheduled_hour'   => 'required|numeric|between:0,23',
            'kitchen_scheduled_minute' => 'required|in:00,05,10,15,20,25,30,35,40,45,50,55',
        ]);

        $scheduledAt = \Carbon\Carbon::parse(
            $request->kitchen_scheduled_date . ' '
            . str_pad($request->kitchen_scheduled_hour, 2, '0', STR_PAD_LEFT)
            . ':' . $request->kitchen_scheduled_minute
        );

        $deliveryOrder->update(['kitchen_scheduled_at' => $scheduledAt]);

        return back()->with('success', 'Jadwal produksi disimpan: ' . $scheduledAt->isoFormat('dddd, D MMMM Y HH:mm') . '.');
    }

    public function directPrintSlip(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load('items.product', 'customer', 'shipments');

        try {
            (new ThermalPrintService())->printDeliverySlip($deliveryOrder);

            return response()->json([
                'success' => true,
                'message' => 'Slip berhasil dikirim ke printer.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function printSlip(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load('items.product', 'customer', 'shipments');
        return view('delivery-orders.print-slip', [
            'order'   => $deliveryOrder,
            'printAt' => now(),
        ]);
    }

    public function printInvoice(DeliveryOrder $deliveryOrder, string $type = 'invoice')
    {
        abort_unless(in_array($type, ['proforma', 'invoice']), 404);

        $deliveryOrder->load('customer', 'creator', 'items.product', 'shipments', 'payments');

        $totalPaid   = $deliveryOrder->payments->sum('amount');
        $outstanding = max(0, $deliveryOrder->total - $totalPaid);

        return view('delivery-orders.invoice', [
            'order'       => $deliveryOrder,
            'store'       => SettingsController::storeSettings(),
            'type'        => $type,
            'totalPaid'   => $totalPaid,
            'outstanding' => $outstanding,
            'printAt'     => now(),
        ]);
    }

    /**
     * Kirim invoice / proforma langsung ke thermal printer (ESC/POS),
     * sama seperti struk kasir.
     */
    public function directPrintInvoice(DeliveryOrder $deliveryOrder, string $type = 'invoice')
    {
        abort_unless(in_array($type, ['proforma', 'invoice']), 404);

        $deliveryOrder->load('customer', 'items.product', 'payments');

        try {
            (new ThermalPrintService())->printInvoice($deliveryOrder, SettingsController::storeSettings(), $type);

            return response()->json([
                'success' => true,
                'message' => ($type === 'proforma' ? 'Proforma' : 'Invoice') . ' berhasil dikirim ke printer.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(DeliveryOrder $deliveryOrder)
    {
        if (in_array($deliveryOrder->status, ['completed', 'cancelled'])) {
            return back()->with('error', 'Order tidak dapat dibatalkan.');
        }

        // Invoice yang sudah terbit harus ikut dibatalkan di ERP HPY, kalau tidak
        // penjualan dan piutangnya tetap terhitung padahal ordernya batal. Bila
        // pembatalan di ERP gagal, order lokal TIDAK jadi dibatalkan supaya kedua
        // sisi tidak berbeda tanpa ketahuan.
        if ($deliveryOrder->erp_sales_invoice) {
            try {
                $erp = new ErpNextService();
                $result = $erp->cancelSalesInvoiceForOrder($deliveryOrder);
                if (! $result['success']) {
                    return back()->with('error', 'Order tidak dibatalkan — gagal membatalkan invoice di ERP HPY: ' . ($result['error'] ?? ''));
                }
            } catch (\Exception $e) {
                return back()->with('error', 'Order tidak dibatalkan — ERP HPY tidak dapat dihubungi: ' . $e->getMessage());
            }
        }

        $deliveryOrder->update(['status' => 'cancelled']);

        return back()->with('success', 'Order dibatalkan.'
            . ($deliveryOrder->erp_sales_invoice ? ' Invoice & pembayaran di ERP HPY ikut dibatalkan.' : ''));
    }

    public function syncSalesOrder(DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->erp_sync_status === 'synced') {
            return response()->json(['success' => false, 'error' => 'Sales Order sudah pernah disync.']);
        }

        $deliveryOrder->load('items.product', 'customer');
        $erp    = new ErpNextService();
        $result = $erp->createSalesOrder($deliveryOrder);

        return response()->json($result);
    }
}
