<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncStockFromErp extends Command
{
    protected $signature = 'stock:sync-erp {--force : Jalankan walau setting stock_auto_sync mati}';

    protected $description = 'Tarik level stok (Bin actual_qty) dari ERP HPY ke product_stocks untuk semua warehouse aktif';

    public function handle(ErpNextService $erp): int
    {
        // Gate: hormati toggle setting kecuali dipaksa
        if (!$this->option('force') && Setting::get('stock_auto_sync', '0') !== '1') {
            $this->info('stock_auto_sync nonaktif — dilewati. Pakai --force untuk paksa jalan.');

            return self::SUCCESS;
        }

        if (!$erp->isConfigured()) {
            $this->warn('ERP HPY belum dikonfigurasi — sync stok dilewati.');

            return self::SUCCESS;
        }

        if (!Schema::hasTable('product_stocks')) {
            $this->error('Tabel product_stocks belum ada. Jalankan: php artisan migrate');

            return self::FAILURE;
        }

        $activeWarehouses = Warehouse::where('is_active', true)->get();

        if ($activeWarehouses->isEmpty()) {
            $this->warn('Tidak ada warehouse aktif.');

            return self::SUCCESS;
        }

        // Index produk by erp_item_code untuk lookup O(1)
        $products = Product::where('track_stock', true)
            ->whereNotNull('erp_item_code')
            ->select('id', 'erp_item_code')
            ->get()
            ->keyBy('erp_item_code');

        if ($products->isEmpty()) {
            $this->warn('Tidak ada produk dengan erp_item_code. Lakukan Pull Products dulu.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($activeWarehouses as $warehouse) {
            $label = $warehouse->warehouse_name ?: $warehouse->name;

            $result = $erp->pullStockFromBin($warehouse->name);

            if (!$result['success']) {
                $this->error("{$label}: {$result['error']}");
                Log::warning('Stock auto-sync gagal', ['warehouse' => $warehouse->name, 'error' => $result['error']]);
                continue;
            }

            $updated     = 0;
            $updatedIds  = [];

            foreach ($result['data'] as $bin) {
                $product = $products->get($bin['item_code']);

                if (!$product) {
                    continue;
                }

                $qty = (int) round($bin['actual_qty']);

                try {
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
                    Log::warning('Stock auto-sync write error', ['sku' => $bin['item_code'], 'error' => $e->getMessage()]);
                }
            }

            // Hapus baris stok lama yang tidak ada di ERP
            ProductStock::where('warehouse_id', $warehouse->id)
                ->whereNotIn('product_id', $updatedIds)
                ->delete();

            $totalUpdated += $updated;
            $this->line("{$label}: {$updated} item disinkronkan.");
        }

        $this->info("Selesai. Total {$totalUpdated} item stok diperbarui.");
        Log::info('Stock auto-sync selesai', ['total_updated' => $totalUpdated]);

        return self::SUCCESS;
    }
}
