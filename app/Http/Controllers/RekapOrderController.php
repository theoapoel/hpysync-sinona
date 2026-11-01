<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrderItem;
use App\Models\StockRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RekapOrderController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;
        $source   = $request->source; // '' = all, 'delivery' = DO only, 'fg_request' = SR only

        $doItems = collect();
        $srItems = collect();

        if (!$source || $source === 'delivery') {
            $query = DeliveryOrderItem::query()
                ->join('delivery_orders', 'delivery_order_items.delivery_order_id', '=', 'delivery_orders.id')
                ->whereNull('delivery_orders.deleted_at')
                ->where('delivery_orders.status', 'confirmed')
                ->select(
                    'delivery_order_items.product_name as item_name',
                    'delivery_order_items.product_sku as item_code',
                    DB::raw('SUM(delivery_order_items.qty) as qty_do')
                )
                ->groupBy('delivery_order_items.product_name', 'delivery_order_items.product_sku');

            if ($dateFrom) {
                $query->whereDate('delivery_orders.delivery_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('delivery_orders.delivery_date', '<=', $dateTo);
            }

            $doItems = $query->get();
        }

        if (!$source || $source === 'fg_request') {
            $query = StockRequestItem::query()
                ->join('stock_requests', 'stock_request_items.stock_request_id', '=', 'stock_requests.id')
                ->where('stock_requests.status', 'submitted')
                ->select(
                    'stock_request_items.item_name',
                    'stock_request_items.item_code',
                    'stock_request_items.uom',
                    DB::raw('SUM(stock_request_items.qty) as qty_sr')
                )
                ->groupBy('stock_request_items.item_name', 'stock_request_items.item_code', 'stock_request_items.uom');

            if ($dateFrom) {
                $query->whereDate('stock_requests.needed_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('stock_requests.needed_date', '<=', $dateTo);
            }

            $srItems = $query->get();
        }

        // Merge by item_code (if non-empty) or item_name
        $merged = [];

        foreach ($doItems as $item) {
            $key = trim($item->item_code) ?: trim($item->item_name);
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'item_name' => $item->item_name,
                    'item_code' => $item->item_code ?? '',
                    'uom'       => '',
                    'qty_do'    => 0,
                    'qty_sr'    => 0,
                ];
            }
            $merged[$key]['qty_do'] += (float) $item->qty_do;
        }

        foreach ($srItems as $item) {
            $key = trim($item->item_code) ?: trim($item->item_name);
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'item_name' => $item->item_name,
                    'item_code' => $item->item_code ?? '',
                    'uom'       => $item->uom,
                    'qty_do'    => 0,
                    'qty_sr'    => 0,
                ];
            }
            $merged[$key]['qty_sr'] += (float) $item->qty_sr;
            if (empty($merged[$key]['uom'])) {
                $merged[$key]['uom'] = $item->uom;
            }
        }

        // Sort alphabetically by item_name
        usort($merged, fn($a, $b) => strcmp($a['item_name'], $b['item_name']));

        // Compute totals per row
        foreach ($merged as &$row) {
            $row['qty_total'] = $row['qty_do'] + $row['qty_sr'];
        }
        unset($row);

        $summary = [
            'total_items'  => count($merged),
            'total_qty_do' => array_sum(array_column($merged, 'qty_do')),
            'total_qty_sr' => array_sum(array_column($merged, 'qty_sr')),
            'total_qty'    => array_sum(array_column($merged, 'qty_total')),
        ];

        return view('rekap-order.index', compact('merged', 'summary', 'dateFrom', 'dateTo', 'source'));
    }
}
