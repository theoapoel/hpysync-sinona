<?php

namespace App\Http\Controllers;

use App\Models\DeliveryShipment;
use App\Models\DeliveryOrder;
use App\Models\Setting;
use App\Services\ErpNextService;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryShipment::with('order.customer')
            ->whereHas('order', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->orderByDesc(
                DeliveryOrder::select('delivery_date')
                    ->whereColumn('delivery_orders.id', 'delivery_shipments.delivery_order_id')
                    ->limit(1)
            )
            ->orderByDesc('delivery_shipments.id');

        if ($request->date) {
            $query->whereHas('order', fn($q) => $q->whereDate('delivery_date', $request->date));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $shipments = $query->paginate(25)->withQueryString();

        // Summary counts
        $counts = DeliveryShipment::whereHas('order', fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $storeName = Setting::get('store_name', 'Mitra POS');

        return view('delivery-notes.index', compact('shipments', 'counts', 'storeName'));
    }

    public function markDelivered(DeliveryShipment $shipment)
    {
        if ($shipment->status === 'delivered') {
            return response()->json(['success' => false, 'error' => 'Sudah ditandai terkirim.']);
        }

        try {
            $shipment->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            // Update order status — load with withTrashed in case order was soft-deleted
            $order = DeliveryOrder::withTrashed()->find($shipment->delivery_order_id);
            if ($order) {
                $allDelivered = $order->shipments()->where('status', '!=', 'delivered')->doesntExist();
                if ($allDelivered) {
                    $order->update(['status' => 'completed']);
                } elseif ($order->status === 'confirmed') {
                    $order->update(['status' => 'delivering']);
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function syncDeliveryNote(DeliveryShipment $shipment)
    {
        if ($shipment->erp_sync_status === 'synced') {
            return response()->json(['success' => false, 'error' => 'Delivery Note sudah pernah disync.']);
        }

        $shipment->load('order.customer');
        $erp    = new ErpNextService();
        $result = $erp->createDeliveryNote($shipment);

        return response()->json($result);
    }
}
