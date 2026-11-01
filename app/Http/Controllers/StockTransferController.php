<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    // ----------------------------------------------------------
    // LIST — all transfers
    // ----------------------------------------------------------
    public function index(Request $request)
    {
        $query = StockTransfer::with('user')
            ->when($request->type, fn($q, $v) => $q->where('type', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->search, fn($q, $v) =>
                $q->where('transfer_no', 'like', "%{$v}%")
                  ->orWhere('from_warehouse', 'like', "%{$v}%")
                  ->orWhere('to_warehouse', 'like', "%{$v}%")
            )
            ->latest();

        $transfers = $query->paginate(20)->withQueryString();

        return view('stock-transfer.index', compact('transfers'));
    }

    // ----------------------------------------------------------
    // SEND — form
    // ----------------------------------------------------------
    public function createSend()
    {
        $warehouses  = Warehouse::activeList();
        $productData = $this->mapProductsForJs();

        return view('stock-transfer.send', compact('warehouses', 'productData'));
    }

    // ----------------------------------------------------------
    // SEND — store
    // ----------------------------------------------------------
    public function storeSend(Request $request)
    {
        $request->validate([
            'from_warehouse'        => 'required|string',
            'to_warehouse'          => 'required|string|different:from_warehouse',
            'in_transit_warehouse'  => 'nullable|string',
            'notes'                 => 'nullable|string|max:500',
            'items'                 => 'required|array|min:1',
            'items.*.item_code'     => 'required|string',
            'items.*.item_name'     => 'required|string',
            'items.*.quantity'      => 'required|numeric|min:0.001',
            'items.*.unit'          => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'transfer_no'         => StockTransfer::generateTransferNo('outgoing'),
                'type'                => 'outgoing',
                'status'              => 'draft',
                'local_status'        => 'sent',
                'from_warehouse'      => $request->from_warehouse,
                'to_warehouse'        => $request->to_warehouse,
                'in_transit_warehouse'=> $request->in_transit_warehouse,
                'notes'               => $request->notes,
                'user_id'             => auth()->id(),
                'erp_sync_status'     => 'pending',
            ]);

            foreach ($request->items as $row) {
                $product = Product::where('erp_item_code', $row['item_code'])
                    ->orWhere('sku', $row['item_code'])
                    ->first();

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $product?->id,
                    'item_code'         => $row['item_code'],
                    'item_name'         => $row['item_name'],
                    'sku'               => $product?->sku ?? $row['item_code'],
                    'quantity'          => $row['quantity'],
                    'unit'              => $row['unit'],
                    'notes'             => $row['notes'] ?? null,
                ]);
            }

            DB::commit();

            // Sync to ERP HPY immediately
            $transfer->load('items');
            $result = $this->erp->createOutgoingTransfer($transfer);

            if ($result['success']) {
                return redirect()->route('stock-transfer.show', $transfer)
                    ->with('success', "Transfer {$transfer->transfer_no} berhasil dikirim ke ERP HPY ({$result['docname']}).");
            }

            return redirect()->route('stock-transfer.show', $transfer)
                ->with('error', "Transfer disimpan, tapi sync ERP HPY gagal: {$result['error']}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // RECEIVE — form (show pending in-transit from ERP HPY)
    // ----------------------------------------------------------
    public function createReceive()
    {
        $warehouses    = Warehouse::activeList();

        $pendingResult = $this->erp->getPendingInTransitEntries();
        $pendingEntries  = $pendingResult['success'] ? $pendingResult['data'] : [];
        $productData     = $this->mapProductsForJs();

        return view('stock-transfer.receive', compact('warehouses', 'pendingEntries', 'productData'));
    }

    // ----------------------------------------------------------
    // RECEIVE — load items from a specific ERP HPY Stock Entry
    // ----------------------------------------------------------
    public function loadEntryItems(Request $request)
    {
        $request->validate(['entry_name' => 'required|string']);

        $result = $this->erp->getStockEntryDetail($request->entry_name);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']]);
        }

        $entry = $result['data'];
        $items = collect($entry['items'] ?? [])->map(function ($item) {
            $product = Product::where('erp_item_code', $item['item_code'])
                ->orWhere('sku', $item['item_code'])
                ->first();

            return [
                'item_code'  => $item['item_code'],
                'item_name'  => $item['item_name'],
                'quantity'   => $item['qty'],
                'unit'       => $item['uom'] ?? 'Nos',
                'product_id' => $product?->id,
                'local_name' => $product?->name,
            ];
        });

        return response()->json([
            'success'       => true,
            'items'         => $items,
            'from_warehouse'=> $entry['from_warehouse'] ?? '',
            'to_warehouse'  => $entry['to_warehouse'] ?? '',
        ]);
    }

    // ----------------------------------------------------------
    // RECEIVE — store
    // ----------------------------------------------------------
    public function storeReceive(Request $request)
    {
        $request->validate([
            'from_warehouse'         => 'required|string',
            'to_warehouse'           => 'required|string',
            'erp_source_entry'       => 'nullable|string',
            'notes'                  => 'nullable|string|max:500',
            'items'                  => 'required|array|min:1',
            'items.*.item_code'      => 'required|string',
            'items.*.item_name'      => 'required|string',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.actual_quantity'=> 'required|numeric|min:0.001',
            'items.*.unit'           => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'transfer_no'     => StockTransfer::generateTransferNo('incoming'),
                'type'            => 'incoming',
                'status'          => 'draft',
                'local_status'    => 'received',
                'from_warehouse'  => $request->from_warehouse,
                'to_warehouse'    => $request->to_warehouse,
                'notes'           => $request->notes,
                'user_id'         => auth()->id(),
                'erp_source_entry'=> $request->erp_source_entry,
                'erp_sync_status' => 'pending',
            ]);

            foreach ($request->items as $row) {
                $product = Product::where('erp_item_code', $row['item_code'])
                    ->orWhere('sku', $row['item_code'])
                    ->first();

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $product?->id,
                    'item_code'         => $row['item_code'],
                    'item_name'         => $row['item_name'],
                    'sku'               => $product?->sku ?? $row['item_code'],
                    'quantity'          => $row['quantity'],
                    'actual_quantity'   => $row['actual_quantity'],
                    'unit'              => $row['unit'],
                    'notes'             => $row['notes'] ?? null,
                ]);
            }

            DB::commit();

            $transfer->load('items.product');
            $result = $this->erp->createIncomingReceipt($transfer);

            if ($result['success']) {
                return redirect()->route('stock-transfer.show', $transfer)
                    ->with('success', "Penerimaan {$transfer->transfer_no} berhasil disubmit ke ERP HPY ({$result['docname']}).");
            }

            return redirect()->route('stock-transfer.show', $transfer)
                ->with('error', "Penerimaan disimpan, tapi sync ERP HPY gagal: {$result['error']}");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan: ' . $e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // SHOW — detail
    // ----------------------------------------------------------
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.product', 'user']);
        return view('stock-transfer.show', ['transfer' => $stockTransfer]);
    }

    // ----------------------------------------------------------
    // SURAT JALAN — standalone print page
    // ----------------------------------------------------------
    public function suratJalan(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.product', 'user']);
        $storeName = \App\Models\Setting::get('store_name', 'HPY');
        return view('stock-transfer.surat-jalan', [
            'transfer'  => $stockTransfer,
            'storeName' => $storeName,
        ]);
    }

    // ----------------------------------------------------------
    // RETRY SYNC to ERP HPY
    // ----------------------------------------------------------
    public function retry(StockTransfer $stockTransfer)
    {
        $stockTransfer->load('items.product');

        $result = $stockTransfer->isOutgoing()
            ? $this->erp->createOutgoingTransfer($stockTransfer)
            : $this->erp->createIncomingReceipt($stockTransfer);

        if (request()->wantsJson()) {
            return response()->json($result);
        }

        $msg = $result['success']
            ? "Sync berhasil: {$result['docname']}"
            : "Sync gagal: {$result['error']}";

        return back()->with($result['success'] ? 'success' : 'error', $msg);
    }

    // ----------------------------------------------------------
    // HELPER — map products to JS-safe array (avoid multi-line @json in Blade)
    // ----------------------------------------------------------
    private function mapProductsForJs(): array
    {
        return Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'erp_item_code', 'unit'])
            ->map(fn($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'sku'       => $p->sku,
                'item_code' => $p->erp_item_code ?? $p->sku,
                'unit'      => $p->unit ?? 'Nos',
            ])
            ->values()
            ->toArray();
    }
}
