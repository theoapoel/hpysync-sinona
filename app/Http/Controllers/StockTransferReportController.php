<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use Illuminate\Http\Request;

class StockTransferReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom  = $request->date_from;
        $dateTo    = $request->date_to;
        $type      = $request->type;      // '' / 'outgoing' / 'incoming'
        $status    = $request->status;    // '' / 'submitted' / 'draft' / 'cancelled'
        $warehouse = $request->warehouse;

        $query = StockTransfer::with(['items.product', 'user'])
            ->orderByDesc('created_at');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($warehouse) {
            $query->where(function ($q) use ($warehouse) {
                $q->where('from_warehouse', 'like', "%{$warehouse}%")
                  ->orWhere('to_warehouse', 'like', "%{$warehouse}%");
            });
        }

        $transfers = $query->get();

        $summary = [
            'total'    => $transfers->count(),
            'outgoing' => $transfers->where('type', 'outgoing')->count(),
            'incoming' => $transfers->where('type', 'incoming')->count(),
            'total_items' => $transfers->sum(fn($t) => $t->items->count()),
            'total_qty_sent'     => $transfers->sum(fn($t) => $t->items->sum('quantity')),
            'total_qty_received' => $transfers->sum(fn($t) => $t->items->sum('actual_quantity')),
        ];

        return view('stock-transfer.report', compact(
            'transfers', 'summary',
            'dateFrom', 'dateTo', 'type', 'status', 'warehouse'
        ));
    }
}
