<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\StockRequest;
use App\Services\ErpNextService;
use Illuminate\Http\Request;

/**
 * API untuk aplikasi tablet Kitchen Monitor.
 * Autentikasi via header X-API-KEY / X-API-SECRET (middleware mobile.api).
 */
class KitchenApiController extends Controller
{
    public function index()
    {
        $this->activateScheduledOrders();

        $orders = DeliveryOrder::with(['customer:id,name', 'items:id,delivery_order_id,product_name,product_sku,qty'])
            ->whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
            ->whereIn('status', ['confirmed', 'delivering', 'completed'])
            ->orderBy('delivery_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'type' => 'delivery',
                'doc_no' => $o->order_no,
                'party' => $o->customer?->name ?? '-',
                'delivery_date' => $o->delivery_date?->format('Y-m-d'),
                'kitchen_status' => $o->kitchen_status,
                'kitchen_started_at' => $o->kitchen_started_at?->toIso8601String(),
                'kitchen_ready_at' => $o->kitchen_ready_at?->toIso8601String(),
                'created_at' => $o->created_at?->toIso8601String(),
                'notes' => $o->notes,
                'items' => $o->items->map(fn ($i) => [
                    'name' => $i->product_name,
                    'sku' => $i->product_sku,
                    'qty' => (float) $i->qty,
                ])->values(),
            ]);

        $stockRequests = StockRequest::with(['requester:id,name', 'items:id,stock_request_id,item_name,item_code,qty,uom,notes'])
            ->whereIn('kitchen_status', ['requested', 'preparing', 'done'])
            ->where('status', 'submitted')
            ->orderBy('needed_date')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($sr) => [
                'id' => $sr->id,
                'type' => 'fg_request',
                'doc_no' => $sr->request_no,
                'party' => $sr->requester?->name ?? '-',
                'needed_date' => $sr->needed_date?->format('Y-m-d'),
                'needed_time' => $sr->needed_time,
                'kitchen_status' => $sr->kitchen_status,
                'kitchen_started_at' => $sr->kitchen_started_at?->toIso8601String(),
                'kitchen_done_at' => $sr->kitchen_done_at?->toIso8601String(),
                'created_at' => $sr->created_at?->toIso8601String(),
                'notes' => $sr->notes,
                'items' => $sr->items->map(fn ($i) => [
                    'name' => $i->item_name,
                    'code' => $i->item_code,
                    'qty' => (float) $i->qty,
                    'uom' => $i->uom,
                    'notes' => $i->notes,
                ])->values(),
            ]);

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'delivery_orders' => $orders,
            'stock_requests' => $stockRequests,
        ]);
    }

    public function poll()
    {
        $activated = $this->activateScheduledOrders();

        $counts = DeliveryOrder::whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
            ->whereIn('status', ['confirmed', 'delivering', 'completed'])
            ->selectRaw('kitchen_status, count(*) as cnt')
            ->groupBy('kitchen_status')
            ->pluck('cnt', 'kitchen_status');

        $srCounts = StockRequest::whereIn('kitchen_status', ['requested', 'preparing', 'done'])
            ->where('status', 'submitted')
            ->selectRaw('kitchen_status, count(*) as total')
            ->groupBy('kitchen_status')
            ->pluck('total', 'kitchen_status');

        return response()->json([
            'success' => true,
            'server_time' => now()->toIso8601String(),
            'pending' => $counts->get('pending', 0),
            'preparing' => $counts->get('preparing', 0),
            'ready' => $counts->get('ready', 0),
            'sr_requested' => $srCounts->get('requested', 0),
            'sr_preparing' => $srCounts->get('preparing', 0),
            'sr_done' => $srCounts->get('done', 0),
            'newly_activated' => $activated,
        ]);
    }

    public function updateOrderStatus(Request $request, DeliveryOrder $order)
    {
        $request->validate([
            'kitchen_status' => 'required|in:pending,preparing,ready',
        ]);

        $newStatus = $request->kitchen_status;
        $data = ['kitchen_status' => $newStatus];

        if ($newStatus === 'preparing' && ! $order->kitchen_started_at) {
            $data['kitchen_started_at'] = now();
        } elseif ($newStatus === 'ready' && ! $order->kitchen_ready_at) {
            $data['kitchen_ready_at'] = now();
        }

        $order->update($data);

        return response()->json(['success' => true, 'kitchen_status' => $newStatus]);
    }

    public function updateStockRequestStatus(Request $request, StockRequest $stockRequest)
    {
        $request->validate([
            'kitchen_status' => 'required|in:requested,preparing,done',
        ]);

        $newStatus = $request->kitchen_status;
        $data = ['kitchen_status' => $newStatus];

        if ($newStatus === 'preparing' && ! $stockRequest->kitchen_started_at) {
            $data['kitchen_started_at'] = now();
        } elseif ($newStatus === 'done' && ! $stockRequest->kitchen_done_at) {
            $data['kitchen_done_at'] = now();
        }

        $stockRequest->update($data);

        // Saat selesai: submit Material Request di ERP HPY (sama seperti alur web)
        if ($newStatus === 'done' && $stockRequest->erp_material_request) {
            try {
                $erp = new ErpNextService;
                if ($erp->isConfigured()) {
                    $erp->submitMaterialRequest($stockRequest);
                }
            } catch (\Exception $e) {
                // silent — jangan block perubahan status kitchen
            }
        }

        return response()->json(['success' => true, 'kitchen_status' => $newStatus]);
    }

    private function activateScheduledOrders(): int
    {
        $toActivate = DeliveryOrder::whereNull('kitchen_status')
            ->whereIn('status', ['confirmed', 'delivering'])
            ->whereNotNull('kitchen_scheduled_at')
            ->whereNotNull('kitchen_confirmed_at')
            ->where('kitchen_scheduled_at', '<=', now())
            ->get();

        foreach ($toActivate as $order) {
            $order->update(['kitchen_status' => 'pending']);
        }

        return $toActivate->count();
    }
}
