<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderPayment;
use App\Models\StockRequest;
use App\Services\ErpNextService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PullingOrderController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type; // '' = all, 'delivery' = DO only, 'fg_request' = SR only
        $search = $request->search;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        $rows = collect();

        if (! $type || $type === 'delivery') {
            $doQuery = DeliveryOrder::with(['customer', 'payments'])
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled');

            if ($search) {
                $doQuery->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
                });
            }
            if ($dateFrom) {
                $doQuery->whereDate('delivery_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $doQuery->whereDate('delivery_date', '<=', $dateTo);
            }

            foreach ($doQuery->get() as $order) {
                $paid = (float) $order->payments->sum('amount');

                $rows->push([
                    'type' => 'delivery',
                    'type_label' => 'Delivery Order',
                    'id' => $order->id,
                    'doc_no' => $order->order_no,
                    'party' => $order->customer?->name ?? '-',
                    'date' => $order->delivery_date,
                    'total' => $order->total,
                    'paid' => $paid,
                    'outstanding' => max(0, (float) $order->total - $paid),
                    'payment_status' => $order->payment_status,
                    'bill_as_receivable' => (bool) $order->bill_as_receivable,
                    'status' => $order->status,
                    'kitchen_scheduled_at' => $order->kitchen_scheduled_at,
                    'kitchen_confirmed_at' => $order->kitchen_confirmed_at,
                    'kitchen_status' => $order->kitchen_status,
                    'route_show' => route('delivery-orders.show', $order),
                ]);
            }
        }

        if (! $type || $type === 'fg_request') {
            $srQuery = StockRequest::with('requester')
                ->where('status', '!=', 'cancelled');

            if ($search) {
                $srQuery->where('request_no', 'like', "%{$search}%");
            }
            if ($dateFrom) {
                $srQuery->whereDate('needed_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $srQuery->whereDate('needed_date', '<=', $dateTo);
            }

            foreach ($srQuery->get() as $sr) {
                $rows->push([
                    'type' => 'fg_request',
                    'type_label' => 'Permintaan FG',
                    'id' => $sr->id,
                    'doc_no' => $sr->request_no,
                    'party' => $sr->requester?->name ?? '-',
                    'date' => $sr->needed_date,
                    'total' => null,
                    'paid' => null,
                    'outstanding' => null,
                    'payment_status' => null,
                    'status' => $sr->status,
                    'kitchen_scheduled_at' => $sr->kitchen_scheduled_at,
                    'kitchen_confirmed_at' => null,
                    'kitchen_status' => $sr->kitchen_status,
                    'route_show' => route('stock-requests.show', $sr),
                ]);
            }
        }

        $rows = $rows->sortByDesc(fn ($r) => $r['date'])->values();

        $mopList = [];
        try {
            $erp = new ErpNextService;
            if ($erp->isConfigured()) {
                $mopList = Cache::remember('erp_mode_of_payment_list', 1800, fn () => $erp->getModeOfPaymentList());
            }
        } catch (\Exception $e) {
            // silent — gunakan fallback
        }
        if (empty($mopList)) {
            $mopList = ['Cash', 'Transfer Bank', 'QRIS', 'Kartu'];
        }

        return view('pulling-order.index', compact('rows', 'search', 'dateFrom', 'dateTo', 'type', 'mopList'));
    }

    public function scheduleDeliveryOrder(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status === 'cancelled') {
            return response()->json(['success' => false, 'error' => 'Order sudah dibatalkan.'], 422);
        }

        $request->validate([
            'kitchen_scheduled_date' => 'required|date',
            'kitchen_scheduled_hour' => 'required|numeric|between:0,23',
            'kitchen_scheduled_minute' => 'required|in:00,05,10,15,20,25,30,35,40,45,50,55',
        ]);

        $scheduledAt = Carbon::parse(
            $request->kitchen_scheduled_date.' '
            .str_pad($request->kitchen_scheduled_hour, 2, '0', STR_PAD_LEFT)
            .':'.$request->kitchen_scheduled_minute
        );

        $deliveryOrder->update(['kitchen_scheduled_at' => $scheduledAt]);

        return response()->json([
            'success' => true,
            'scheduled_at' => $scheduledAt->isoFormat('dddd, D MMMM Y HH:mm'),
        ]);
    }

    /**
     * Konfirmasi jadwal Delivery Order dari Pulling Order → gate masuk Kitchen Monitor.
     * DO harus sudah dikonfirmasi (status confirmed/delivering) dan punya jadwal.
     * Jika jadwal sudah tiba, langsung masuk antrian; jika belum, menunggu waktunya
     * (activateScheduledOrders akan mengaktifkan otomatis karena kini sudah dikonfirmasi).
     */
    public function confirmKitchen(DeliveryOrder $deliveryOrder)
    {
        if (! in_array($deliveryOrder->status, ['confirmed', 'delivering'])) {
            return response()->json(['success' => false, 'error' => 'Order harus dikonfirmasi lebih dulu dari menu Delivery Order.'], 422);
        }
        if (! $deliveryOrder->kitchen_scheduled_at) {
            return response()->json(['success' => false, 'error' => 'Set jadwal produksi terlebih dahulu.'], 422);
        }
        if ($deliveryOrder->kitchen_confirmed_at) {
            return response()->json(['success' => false, 'error' => 'Jadwal sudah dikonfirmasi ke dapur.'], 422);
        }

        $data = ['kitchen_confirmed_at' => now()];
        // Jika waktu jadwal sudah tiba/lewat, langsung masukkan ke antrian dapur.
        if (! $deliveryOrder->kitchen_scheduled_at->isFuture() && ! $deliveryOrder->kitchen_status) {
            $data['kitchen_status'] = 'pending';
        }
        $deliveryOrder->update($data);

        return response()->json([
            'success' => true,
            'in_queue' => ($data['kitchen_status'] ?? null) === 'pending',
            'scheduled_at' => $deliveryOrder->kitchen_scheduled_at->isoFormat('dddd, D MMMM Y HH:mm'),
        ]);
    }

    public function scheduleStockRequest(Request $request, StockRequest $stockRequest)
    {
        if ($stockRequest->status === 'cancelled') {
            return response()->json(['success' => false, 'error' => 'Permintaan sudah dibatalkan.'], 422);
        }

        $request->validate([
            'kitchen_scheduled_date' => 'required|date',
            'kitchen_scheduled_hour' => 'required|numeric|between:0,23',
            'kitchen_scheduled_minute' => 'required|in:00,05,10,15,20,25,30,35,40,45,50,55',
        ]);

        $scheduledAt = Carbon::parse(
            $request->kitchen_scheduled_date.' '
            .str_pad($request->kitchen_scheduled_hour, 2, '0', STR_PAD_LEFT)
            .':'.$request->kitchen_scheduled_minute
        );

        $stockRequest->update(['kitchen_scheduled_at' => $scheduledAt]);

        return response()->json([
            'success' => true,
            'scheduled_at' => $scheduledAt->isoFormat('dddd, D MMMM Y HH:mm'),
        ]);
    }

    public function storePayment(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status === 'cancelled') {
            return response()->json(['success' => false, 'error' => 'Order sudah dibatalkan.'], 422);
        }

        $request->validate([
            'payment_method' => 'required|string|max:100',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $payment = DeliveryOrderPayment::create([
            'delivery_order_id' => $deliveryOrder->id,
            'payment_method' => $request->payment_method,
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'reference_no' => $request->reference_no,
            'notes' => $request->notes,
            'erp_sync_status' => 'none',
            'created_by' => auth()->id(),
        ]);

        $deliveryOrder->recalculatePaymentStatus();

        $syncWarning = null;
        if ($deliveryOrder->erp_sales_order) {
            try {
                $erp = new ErpNextService;

                // Terbitkan invoice dulu supaya pembayaran ini tercatat sebagai
                // pelunasan piutang, bukan uang muka di Sales Order. createSalesInvoice()
                // idempoten — aman dipanggil berulang, satu DO tetap satu invoice.
                $siResult = $erp->createSalesInvoice($deliveryOrder);
                if (! $siResult['success']) {
                    $syncWarning = 'Payment disimpan, tapi invoice ERP gagal dibuat: '.($siResult['error'] ?? '');
                } else {
                    $deliveryOrder->refresh();

                    $result = $erp->createPaymentEntry($payment);
                    if (! $result['success']) {
                        $syncWarning = 'Payment disimpan, tapi sync ERP gagal: '.($result['error'] ?? '');
                    }
                }
            } catch (\Exception $e) {
                $syncWarning = 'Payment disimpan, tapi sync ERP gagal: '.$e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'payment_status' => $deliveryOrder->fresh()->payment_status,
            'warning' => $syncWarning,
        ]);
    }
}
