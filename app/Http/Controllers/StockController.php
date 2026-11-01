<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StockController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    public function debugBinEndpoint()
    {
        $erpUrl    = \App\Models\Setting::get('erpnext_url', env('ERPNEXT_URL', ''));
        $apiKey    = \App\Models\Setting::get('erpnext_api_key', env('ERPNEXT_API_KEY', ''));
        $warehouses = Warehouse::where('is_active', true)->get(['id', 'name', 'warehouse_name', 'is_default']);

        $endpoints = $warehouses->map(function ($wh) use ($erpUrl) {
            $params = http_build_query([
                'fields'            => json_encode(['item_code', 'warehouse', 'actual_qty']),
                'filters'           => json_encode([['warehouse', '=', $wh->name]]),
                'limit_page_length' => 0,
            ]);
            return [
                'warehouse_lokal' => $wh->warehouse_name ?: $wh->name,
                'warehouse_erp'   => $wh->name,
                'is_default'      => $wh->is_default,
                'url'             => rtrim($erpUrl, '/') . '/api/resource/Bin?' . $params,
            ];
        });

        return response()->json([
            'base_url'          => $erpUrl,
            'authorization'     => 'token ' . $apiKey . ':***',
            'active_warehouses' => $warehouses->count(),
            'endpoints'         => $endpoints,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function debugSync()
    {
        $log = [];

        // STEP 1: Cek warehouse aktif di lokal
        $activeWarehouses = Warehouse::where('is_active', true)->get();
        $log[] = [
            'step'   => '1. Warehouse aktif di lokal',
            'count'  => $activeWarehouses->count(),
            'detail' => $activeWarehouses->map(fn($w) => [
                'id'         => $w->id,
                'name'       => $w->name,
                'label'      => $w->warehouse_name,
                'is_default' => $w->is_default,
            ])->values(),
        ];

        if ($activeWarehouses->isEmpty()) {
            return response()->json(['log' => $log, 'halted' => 'Tidak ada warehouse aktif'], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // STEP 2: Produk lokal yang track_stock & punya SKU
        $products = Product::where('track_stock', true)
            ->whereNotNull('sku')
            ->select('id', 'sku')
            ->get()
            ->keyBy('sku');

        $log[] = [
            'step'        => '2. Produk lokal (track_stock=true, sku not null)',
            'count'       => $products->count(),
            'sample_skus' => $products->keys()->take(10)->values(),
        ];

        // STEP 3: Per warehouse — tarik Bin dari ERP dan cocokkan SKU
        foreach ($activeWarehouses as $warehouse) {
            $whLabel = $warehouse->warehouse_name ?: $warehouse->name;

            $result = $this->erp->pullStockFromBin($warehouse->name);

            if (!$result['success']) {
                $log[] = [
                    'step'      => "3. Bin ERP — {$whLabel}",
                    'erp_name'  => $warehouse->name,
                    'error'     => $result['error'],
                ];
                continue;
            }

            $bins = $result['data'];

            $matched   = [];
            $unmatched = [];

            foreach ($bins as $bin) {
                $found = $products->has($bin['item_code']);
                if ($found) {
                    $matched[] = [
                        'item_code'  => $bin['item_code'],
                        'actual_qty' => $bin['actual_qty'],
                    ];
                } else {
                    $unmatched[] = $bin['item_code'];
                }
            }

            $log[] = [
                'step'              => "3. Bin ERP — {$whLabel}",
                'erp_name'          => $warehouse->name,
                'is_default'        => $warehouse->is_default,
                'total_bins'        => count($bins),
                'matched_count'     => count($matched),
                'unmatched_count'   => count($unmatched),
                'matched_sample'    => array_slice($matched, 0, 5),
                'unmatched_sample'  => array_slice($unmatched, 0, 10),
            ];
        }

        return response()->json(['log' => $log], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function syncWarehouse(Warehouse $warehouse)
    {
        // Pastikan tabel ada
        if (!Schema::hasTable('product_stocks')) {
            return response()->json([
                'success'   => false,
                'warehouse' => $warehouse->warehouse_name ?: $warehouse->name,
                'error'     => 'Tabel product_stocks belum ada. Jalankan: php artisan migrate',
            ]);
        }

        // Index produk by erp_item_code untuk lookup O(1)
        $products = Product::where('track_stock', true)
            ->whereNotNull('erp_item_code')
            ->select('id', 'erp_item_code')
            ->get()
            ->keyBy('erp_item_code');

        if ($products->isEmpty()) {
            return response()->json([
                'success'   => false,
                'warehouse' => $warehouse->warehouse_name ?: $warehouse->name,
                'error'     => 'Tidak ada produk dengan erp_item_code. Lakukan Pull Products dari menu Sync HPY terlebih dahulu.',
            ]);
        }

        // Tarik Bin dari ERP untuk warehouse ini
        $result = $this->erp->pullStockFromBin($warehouse->name);

        if (!$result['success']) {
            return response()->json([
                'success'   => false,
                'warehouse' => $warehouse->warehouse_name ?: $warehouse->name,
                'error'     => $result['error'],
            ]);
        }

        $bins    = $result['data'];
        $updated = 0;
        $skipped = 0;
        $writeError  = null;
        $updatedIds  = [];

        foreach ($bins as $bin) {
            // Cocokkan bin.item_code dengan products.erp_item_code
            $product = $products->get($bin['item_code']);

            if (!$product) {
                $skipped++;
                continue;
            }

            $qty = (int) round($bin['actual_qty']);

            try {
                // Update stok per warehouse di tabel product_stocks
                ProductStock::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity'   => $qty]
                );

                // Sinkronkan juga products.stock untuk warehouse default
                if ($warehouse->is_default) {
                    $product->update(['stock' => $qty]);
                }

                $updatedIds[] = $product->id;
                $updated++;
            } catch (\Exception $e) {
                $writeError = $e->getMessage();
                break;
            }
        }

        if ($writeError) {
            return response()->json([
                'success'   => false,
                'warehouse' => $warehouse->warehouse_name ?: $warehouse->name,
                'error'     => 'Gagal tulis ke DB: ' . $writeError,
                'updated'   => $updated,
            ]);
        }

        // Hapus baris stok lama yang tidak ada di ERP (sudah tidak relevan)
        $removed = ProductStock::where('warehouse_id', $warehouse->id)
            ->whereNotIn('product_id', $updatedIds)
            ->delete();

        // Verifikasi: hitung baris yang tersimpan
        $savedRows = ProductStock::where('warehouse_id', $warehouse->id)->count();

        return response()->json([
            'success'    => true,
            'warehouse'  => $warehouse->warehouse_name ?: $warehouse->name,
            'erp_name'   => $warehouse->name,
            'is_default' => $warehouse->is_default,
            'bin_count'  => count($bins),
            'updated'    => $updated,
            'skipped'    => $skipped,
            'removed'    => $removed,
            'db_rows'    => $savedRows,
        ]);
    }

    public function syncFromBin()
    {
        $activeWarehouses = Warehouse::where('is_active', true)->get();

        if ($activeWarehouses->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'Tidak ada warehouse aktif di database lokal.']);
        }

        $products = Product::where('track_stock', true)
            ->whereNotNull('sku')
            ->select('id', 'sku')
            ->get()
            ->keyBy('sku');

        $totalUpdated  = 0;
        $warehouseLogs = [];
        $errors        = [];
        $writeErrors   = [];

        foreach ($activeWarehouses as $warehouse) {
            $result = $this->erp->pullStockFromBin($warehouse->name);

            if (!$result['success']) {
                $errors[] = ($warehouse->warehouse_name ?: $warehouse->name) . ': ' . $result['error'];
                continue;
            }

            $bins    = $result['data'];
            $updated = 0;
            $skipped = 0;

            foreach ($bins as $bin) {
                $product = $products->get($bin['item_code']);

                if (!$product) {
                    $skipped++;
                    continue;
                }

                $qty = (int) round($bin['actual_qty']);

                try {
                    ProductStock::updateOrCreate(
                        ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                        ['quantity'   => $qty]
                    );

                    if ($warehouse->is_default) {
                        $product->update(['stock' => $qty]);
                    }

                    $updated++;
                } catch (\Exception $e) {
                    $writeErrors[] = "SKU {$bin['item_code']}: " . $e->getMessage();
                    if (count($writeErrors) >= 3) break;
                }
            }

            $totalUpdated += $updated;

            // Verifikasi: hitung baris aktual di product_stocks untuk warehouse ini
            $actualRows = ProductStock::where('warehouse_id', $warehouse->id)->count();

            $warehouseLogs[] = [
                'warehouse'        => $warehouse->warehouse_name ?: $warehouse->name,
                'erp_name'         => $warehouse->name,
                'bin_count'        => count($bins),
                'updated'          => $updated,
                'skipped'          => $skipped,
                'is_default'       => $warehouse->is_default,
                'db_rows_after'    => $actualRows,
            ];
        }

        return response()->json([
            'success'        => true,
            'warehouses'     => $warehouseLogs,
            'total'          => $totalUpdated,
            'errors'         => $errors,
            'write_errors'   => $writeErrors,
            'local_products' => $products->count(),
        ]);
    }

    public function index(Request $request)
    {
        $warehouses = Warehouse::active()->orderBy('warehouse_name')->get();
        $defaultWarehouse = Warehouse::getDefault();

        // Warehouse yang dipilih: dari query string, fallback ke default, fallback ke pertama
        $selectedWarehouseId = $request->integer('warehouse_id')
            ?: $defaultWarehouse?->id
            ?: $warehouses->first()?->id;

        $selectedWarehouse = $warehouses->firstWhere('id', $selectedWarehouseId);

        $query = ProductStock::with(['product.category', 'product.itemCategory'])
            ->where('warehouse_id', $selectedWarehouseId)
            ->whereHas('product', fn($q) => $q->where('is_active', true)->where('track_stock', true));

        if ($request->search) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%")
                  ->orWhere('barcode', 'like', "%{$request->search}%");
            });
        }

        if ($request->item_category_id) {
            $query->whereHas('product', fn($q) => $q->where('item_category_id', $request->item_category_id));
        }

        if ($request->status === 'empty') {
            $query->where('quantity', '<=', 0);
        } elseif ($request->status === 'low') {
            $query->where('quantity', '>', 0)
                  ->whereHas('product', fn($q) => $q->whereColumn('product_stocks.quantity', '<=', 'products.min_stock'));
        } elseif ($request->status === 'safe') {
            $query->where('quantity', '>', 0)
                  ->whereHas('product', fn($q) => $q->whereColumn('product_stocks.quantity', '>', 'products.min_stock'));
        }

        $stocks = $query->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->orderBy('products.name')
            ->select('product_stocks.*')
            ->paginate(50)
            ->withQueryString();

        // Summary stats untuk warehouse yang dipilih
        $allInWarehouse = ProductStock::where('warehouse_id', $selectedWarehouseId)
            ->whereHas('product', fn($q) => $q->where('is_active', true)->where('track_stock', true));

        $totalProducts = (clone $allInWarehouse)->count();
        $totalEmpty    = (clone $allInWarehouse)->where('quantity', '<=', 0)->count();
        $totalLow      = (clone $allInWarehouse)->where('quantity', '>', 0)
            ->whereHas('product', fn($q) => $q->whereColumn('product_stocks.quantity', '<=', 'products.min_stock'))
            ->count();
        $totalSafe     = $totalProducts - $totalEmpty - $totalLow;

        $itemCategories = ItemCategory::active()->orderBy('name')->get();

        return view('stock.index', compact(
            'stocks', 'itemCategories', 'warehouses', 'selectedWarehouse', 'selectedWarehouseId',
            'totalProducts', 'totalEmpty', 'totalLow', 'totalSafe'
        ));
    }
}
