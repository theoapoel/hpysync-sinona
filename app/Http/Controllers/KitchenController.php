<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\StockRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function index()
    {
        // Auto-aktifkan order yang jadwal produksinya sudah tiba
        $this->activateScheduledOrders();

        $orders = DeliveryOrder::with(['customer', 'items'])
            ->whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
            ->whereIn('status', ['confirmed', 'delivering', 'completed'])
            ->orderBy('delivery_date')
            ->orderBy('created_at')
            ->get()
            ->groupBy('kitchen_status');

        $pending   = $orders->get('pending',   collect());
        $preparing = $orders->get('preparing', collect());
        $ready     = $orders->get('ready',     collect());

        $stockRequests = StockRequest::with(['requester', 'items'])
            ->whereIn('kitchen_status', ['requested', 'preparing', 'done'])
            ->where('status', 'submitted')
            ->orderBy('needed_date')
            ->orderBy('created_at')
            ->get()
            ->groupBy('kitchen_status');

        $srRequested = $stockRequests->get('requested', collect());
        $srPreparing = $stockRequests->get('preparing', collect());
        $srDone      = $stockRequests->get('done',      collect());

        return view('kitchen.index', compact(
            'pending', 'preparing', 'ready',
            'srRequested', 'srPreparing', 'srDone'
        ));
    }

    public function updateStatus(Request $request, DeliveryOrder $order)
    {
        $request->validate([
            'kitchen_status' => 'required|in:pending,preparing,ready',
        ]);

        $newStatus = $request->kitchen_status;
        $data = ['kitchen_status' => $newStatus];

        if ($newStatus === 'preparing' && !$order->kitchen_started_at) {
            $data['kitchen_started_at'] = now();
        } elseif ($newStatus === 'ready' && !$order->kitchen_ready_at) {
            $data['kitchen_ready_at'] = now();
        }

        $order->update($data);

        return response()->json(['success' => true, 'kitchen_status' => $newStatus]);
    }

    public function poll()
    {
        // Auto-aktifkan order terjadwal setiap poll
        $activated = $this->activateScheduledOrders();

        $counts = DeliveryOrder::whereIn('kitchen_status', ['pending', 'preparing', 'ready'])
            ->whereIn('status', ['confirmed', 'delivering', 'completed'])
            ->selectRaw('kitchen_status, count(*) as total')
            ->groupBy('kitchen_status')
            ->pluck('total', 'kitchen_status');

        $srCounts = StockRequest::whereIn('kitchen_status', ['requested', 'preparing'])
            ->where('status', 'submitted')
            ->selectRaw('kitchen_status, count(*) as total')
            ->groupBy('kitchen_status')
            ->pluck('total', 'kitchen_status');

        return response()->json([
            'pending'      => $counts->get('pending', 0),
            'preparing'    => $counts->get('preparing', 0),
            'ready'        => $counts->get('ready', 0),
            'sr_requested' => $srCounts->get('requested', 0),
            'sr_preparing' => $srCounts->get('preparing', 0),
            'sr_done'      => $srCounts->get('done', 0),
            'newly_activated' => $activated,
        ]);
    }

    // ── Kalender Produksi ────────────────────────────────────────
    public function calendar(Request $request)
    {
        $month = $request->month ? Carbon::parse($request->month . '-01') : Carbon::today()->startOfMonth();
        $prev  = $month->copy()->subMonth();
        $next  = $month->copy()->addMonth();

        // Ambil semua Delivery Order bulan ini yang punya jadwal produksi
        $scheduledDo = DeliveryOrder::with('customer', 'items')
            ->whereNotNull('kitchen_scheduled_at')
            ->whereNotNull('kitchen_confirmed_at') // hanya yang sudah dikonfirmasi di Pulling Order
            ->whereIn('status', ['draft', 'confirmed', 'delivering', 'completed'])
            ->whereYear('kitchen_scheduled_at', $month->year)
            ->whereMonth('kitchen_scheduled_at', $month->month)
            ->get()
            ->map(fn ($o) => (object) [
                'entry_type'      => 'delivery',
                'doc_no'          => $o->order_no,
                'party'           => $o->customer?->name ?? '-',
                'kitchen_scheduled_at' => $o->kitchen_scheduled_at,
                'kitchen_status'  => $o->kitchen_status,
                'payment_status'  => $o->payment_status,
                'total'           => $o->total,
                'items_count'     => $o->items->count(),
                'route_show'      => route('delivery-orders.show', $o),
            ]);

        // Ambil semua Permintaan FG bulan ini yang punya jadwal produksi
        $scheduledSr = StockRequest::with('requester', 'items')
            ->whereNotNull('kitchen_scheduled_at')
            ->where('status', 'submitted')
            ->whereYear('kitchen_scheduled_at', $month->year)
            ->whereMonth('kitchen_scheduled_at', $month->month)
            ->get()
            ->map(fn ($sr) => (object) [
                'entry_type'      => 'fg_request',
                'doc_no'          => $sr->request_no,
                'party'           => $sr->requester?->name ?? '-',
                'kitchen_scheduled_at' => $sr->kitchen_scheduled_at,
                'kitchen_status'  => $sr->kitchen_status,
                'payment_status'  => null,
                'total'           => null,
                'items_count'     => $sr->items->count(),
                'route_show'      => route('stock-requests.show', $sr),
            ]);

        $scheduledOrders = $scheduledDo->concat($scheduledSr)->sortBy('kitchen_scheduled_at')->values();

        // Group per tanggal (Y-m-d)
        $ordersByDate = $scheduledOrders->groupBy(fn($o) => $o->kitchen_scheduled_at->format('Y-m-d'));

        // Tanggal yang dipilih (default: hari ini kalau ada, atau null)
        $selectedDate = $request->date
            ? Carbon::parse($request->date)
            : null;

        $selectedOrders = $selectedDate
            ? ($ordersByDate->get($selectedDate->format('Y-m-d')) ?? collect())
            : collect();

        return view('kitchen.calendar', compact(
            'month', 'prev', 'next', 'ordersByDate', 'selectedDate', 'selectedOrders'
        ));
    }

    // ── Private helper ───────────────────────────────────────────
    private function activateScheduledOrders(): int
    {
        $toActivate = DeliveryOrder::whereNull('kitchen_status')
            ->whereIn('status', ['confirmed', 'delivering'])
            ->whereNotNull('kitchen_scheduled_at')
            ->whereNotNull('kitchen_confirmed_at') // gate: hanya yang sudah dikonfirmasi di Pulling Order
            ->where('kitchen_scheduled_at', '<=', now())
            ->get();

        foreach ($toActivate as $order) {
            $order->update(['kitchen_status' => 'pending']);
        }

        return $toActivate->count();
    }
}
