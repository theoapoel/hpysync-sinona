<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockOpnameController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    public function index()
    {
        $opnames = StockOpname::with(['warehouse', 'creator'])
            ->orderByDesc('opname_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('stock-opname.index', compact('opnames'));
    }

    public function create()
    {
        $warehouses = Warehouse::active()->orderBy('warehouse_name')->get();
        return view('stock-opname.create', compact('warehouses'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'opname_date'  => 'required|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        $warehouse = Warehouse::findOrFail($request->warehouse_id);

        // Ambil semua produk aktif + track_stock di warehouse ini
        $stocks = ProductStock::with('product')
            ->where('warehouse_id', $warehouse->id)
            ->whereHas('product', fn($q) => $q->where('is_active', true)->where('track_stock', true))
            ->get();

        DB::beginTransaction();
        try {
            $opname = StockOpname::create([
                'warehouse_id' => $warehouse->id,
                'created_by'   => Auth::id(),
                'opname_date'  => $request->opname_date,
                'notes'        => $request->notes,
                'status'       => 'draft',
            ]);

            // Snapshot system_qty saat ini
            $items = $stocks->map(fn($s) => [
                'stock_opname_id' => $opname->id,
                'product_id'      => $s->product_id,
                'system_qty'      => $s->quantity,
                'actual_qty'      => $s->quantity, // default sama dengan sistem
                'difference'      => 0,
                'created_at'      => now(),
                'updated_at'      => now(),
            ])->values()->all();

            StockOpnameItem::insert($items);

            DB::commit();
            return redirect()->route('stock-opname.show', $opname)->with('success', 'Stock opname dibuat. Silakan input jumlah aktual.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal membuat opname: ' . $e->getMessage()]);
        }
    }

    public function show(StockOpname $stockOpname)
    {
        $stockOpname->load(['warehouse', 'creator', 'items.product.category']);

        $items = $stockOpname->items()
            ->join('products', 'products.id', '=', 'stock_opname_items.product_id')
            ->orderBy('products.name')
            ->select('stock_opname_items.*')
            ->with('product.category')
            ->paginate(50)
            ->withQueryString();

        $summary = [
            'total'  => $stockOpname->items()->count(),
            'lebih'  => $stockOpname->items()->where('difference', '>', 0)->count(),
            'kurang' => $stockOpname->items()->where('difference', '<', 0)->count(),
            'sama'   => $stockOpname->items()->where('difference', 0)->count(),
        ];

        return view('stock-opname.show', compact('stockOpname', 'items', 'summary'));
    }

    public function updateItems(Request $request, StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'draft') {
            return response()->json(['success' => false, 'error' => 'Opname sudah dikunci.']);
        }

        // $request->items = [['id' => ..., 'actual_qty' => ...], ...]
        foreach ($request->items as $row) {
            $item = StockOpnameItem::where('id', $row['id'])
                ->where('stock_opname_id', $stockOpname->id)
                ->first();

            if (!$item) continue;

            $actual     = (int) $row['actual_qty'];
            $difference = $actual - $item->system_qty;

            $item->update([
                'actual_qty'  => $actual,
                'difference'  => $difference,
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function submit(StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'draft') {
            return back()->with('error', 'Opname sudah disubmit atau dibatalkan.');
        }

        $stockOpname->load('warehouse', 'items.product');

        $warehouseName = $stockOpname->warehouse->name;
        $opnameDate    = $stockOpname->opname_date->format('Y-m-d');

        // Pisahkan item berselisih
        $issueItems   = []; // actual > system → Material Issue
        $receiptItems = []; // actual < system → Material Receipt

        foreach ($stockOpname->items as $item) {
            if (!$item->product->erp_item_code) continue;
            if ($item->difference === 0) continue;

            $entry = [
                'item_code'  => $item->product->erp_item_code,
                'qty'        => abs($item->difference),
                'basic_rate' => $item->product->cost_price ?? 0,
            ];

            if ($item->difference > 0) {
                // actual > system: sistem perlu ditambah → Material Receipt
                $receiptItems[] = $entry;
            } else {
                // actual < system: sistem perlu dikurangi → Material Issue
                $issueItems[] = $entry;
            }
        }

        $erpEntryIssue   = null;
        $erpEntryReceipt = null;
        $errors          = [];

        if (!empty($issueItems)) {
            $result = $this->erp->createOpnameMaterialIssue($warehouseName, $opnameDate, $issueItems);
            if ($result['success']) {
                $erpEntryIssue = $result['name'];
            } else {
                $errors[] = 'Material Issue: ' . $result['error'];
            }
        }

        if (!empty($receiptItems)) {
            $result = $this->erp->createOpnameMaterialReceipt($warehouseName, $opnameDate, $receiptItems);
            if ($result['success']) {
                $erpEntryReceipt = $result['name'];
            } else {
                $errors[] = 'Material Receipt: ' . $result['error'];
            }
        }

        $syncStatus = empty($errors) ? 'synced' : 'failed';

        $stockOpname->update([
            'status'           => 'submitted',
            'erp_sync_status'  => $syncStatus,
            'erp_sync_error'   => empty($errors) ? null : implode(' | ', $errors),
            'erp_entry_issue'  => $erpEntryIssue,
            'erp_entry_receipt' => $erpEntryReceipt,
        ]);

        if ($syncStatus === 'synced') {
            // Update product_stocks lokal sesuai actual_qty
            foreach ($stockOpname->items as $item) {
                ProductStock::updateOrCreate(
                    ['product_id' => $item->product_id, 'warehouse_id' => $stockOpname->warehouse_id],
                    ['quantity'   => $item->actual_qty]
                );
                if ($stockOpname->warehouse->is_default) {
                    $item->product->update(['stock' => $item->actual_qty]);
                }
            }
            return back()->with('success', 'Stock opname berhasil disubmit dan disinkronkan ke ERP HPY.');
        }

        return back()->with('error', 'Opname disubmit tapi ada error ERP: ' . implode(', ', $errors));
    }

    public function cancel(StockOpname $stockOpname)
    {
        if ($stockOpname->status !== 'draft') {
            return back()->with('error', 'Hanya opname draft yang bisa dibatalkan.');
        }
        $stockOpname->update(['status' => 'cancelled']);
        return back()->with('success', 'Stock opname dibatalkan.');
    }
}
