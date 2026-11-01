<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    public function kasirRedirect()
    {
        $layout = \App\Models\Setting::get('pos_layout', 'index');
        return match($layout) {
            'quick'   => redirect()->route('pos.quick'),
            'express' => redirect()->route('pos.express'),
            default   => redirect()->route('pos.index'),
        };
    }

    public function quickIndex()
    {
        $categories        = Category::filteredByGroups('pos_item_groups')->get();
        $customers         = Customer::where('is_active', true)->get(['id', 'code', 'name', 'phone', 'loyalty_points']);
        $storeSettings     = SettingsController::storeSettings();
        $posClass          = $storeSettings['pos_class'] ?? '';
        $walkinCustomerName = \App\Models\Setting::get('erpnext_walkin_customer', 'Walk-in Customer');
        $posPaymentMethods  = pos_payment_methods();
        $deliveryPrices    = \App\Models\DeliveryPrice::allGrouped();
        $erpBaseUrl        = rtrim(\App\Models\Setting::get('erpnext_url', ''), '/');
        $dineInCharges     = [
            'service_charge_enabled' => $storeSettings['service_charge_enabled'] === '1',
            'service_charge_pct'     => (float) ($storeSettings['service_charge_pct'] ?? 0),
            'pb1_enabled'            => $storeSettings['pb1_enabled'] === '1',
            'pb1_pct'                => (float) ($storeSettings['pb1_pct'] ?? 0),
        ];

        return view('pos.quick', compact(
            'categories', 'customers', 'posClass',
            'walkinCustomerName', 'erpBaseUrl', 'deliveryPrices', 'dineInCharges',
            'storeSettings', 'posPaymentMethods'
        ));
    }

    public function expressIndex()
    {
        $categories        = Category::filteredByGroups('pos_item_groups')->get();
        $customers         = Customer::where('is_active', true)->get(['id', 'code', 'name', 'phone', 'loyalty_points']);
        $storeSettings     = SettingsController::storeSettings();
        $posClass          = $storeSettings['pos_class'] ?? '';
        $walkinCustomerName = \App\Models\Setting::get('erpnext_walkin_customer', 'Walk-in Customer');
        $posPaymentMethods  = pos_payment_methods();
        $deliveryPrices    = \App\Models\DeliveryPrice::allGrouped();
        $erpBaseUrl        = rtrim(\App\Models\Setting::get('erpnext_url', ''), '/');
        $dineInCharges     = [
            'service_charge_enabled' => $storeSettings['service_charge_enabled'] === '1',
            'service_charge_pct'     => (float) ($storeSettings['service_charge_pct'] ?? 0),
            'pb1_enabled'            => $storeSettings['pb1_enabled'] === '1',
            'pb1_pct'                => (float) ($storeSettings['pb1_pct'] ?? 0),
        ];

        return view('pos.express', compact(
            'categories', 'customers', 'posClass',
            'walkinCustomerName', 'erpBaseUrl', 'deliveryPrices', 'dineInCharges',
            'storeSettings', 'posPaymentMethods'
        ));
    }

    public function index()
    {
        $categories = Category::filteredByGroups('pos_item_groups')->get();
        $products = Product::active()->inItemGroups('pos_item_groups')->with('category')->get();
        $customers = Customer::where('is_active', true)->get(['id', 'code', 'name', 'phone', 'loyalty_points']);
        $storeSettings      = SettingsController::storeSettings();
        $posClass           = $storeSettings['pos_class'] ?? '';
        $posProductDisplay  = $storeSettings['pos_product_display'] ?? 'image';
        $walkinCustomerName  = \App\Models\Setting::get('erpnext_walkin_customer', 'Walk-in Customer');
        $posPaymentMethods   = pos_payment_methods();
        $erpBaseUrl         = rtrim(\App\Models\Setting::get('erpnext_url', ''), '/');

        $deliveryPrices = \App\Models\DeliveryPrice::allGrouped();

        $dineInCharges = [
            'service_charge_enabled' => $storeSettings['service_charge_enabled'] === '1',
            'service_charge_pct'     => (float) ($storeSettings['service_charge_pct'] ?? 0),
            'pb1_enabled'            => $storeSettings['pb1_enabled'] === '1',
            'pb1_pct'                => (float) ($storeSettings['pb1_pct'] ?? 0),
        ];

        return view('pos.index', compact(
            'categories', 'products', 'customers', 'posClass', 'posProductDisplay',
            'walkinCustomerName', 'erpBaseUrl', 'deliveryPrices', 'dineInCharges',
            'storeSettings', 'posPaymentMethods'
        ));
    }

    public function searchProducts(Request $request)
    {
        $term = $request->get('q', '');
        $categoryId = $request->get('category_id');

        $query = Product::active()->with('category')->inItemGroups('pos_item_groups');

        if ($term) {
            $query->search($term);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->limit(50)->get();

        return response()->json($products->map(function ($p) {
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'sku'            => $p->sku,
                'barcode'        => $p->barcode,
                'price'          => $p->price,
                'stock'          => $p->stock,
                'unit'           => $p->unit,
                'tax_rate'       => $p->tax_rate,
                'track_stock'    => (bool) $p->track_stock,
                'image'          => $p->image,
                'category'       => $p->category?->name,
                'category_id'    => $p->category_id,
                'category_color' => $p->category?->color ?? '#4285F4',
                'is_low_stock'   => $p->isLowStock(),
                'erp_item_code'  => $p->erp_item_code,
            ];
        }));
    }

    public function validateCoupon(Request $request)
    {
        $code     = strtoupper(trim($request->code ?? ''));
        $subtotal = (float) ($request->subtotal ?? 0);

        if (!$code) {
            return response()->json(['valid' => false, 'message' => 'Kode kupon tidak boleh kosong']);
        }

        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Kode kupon tidak ditemukan']);
        }

        $check = $coupon->isValid($subtotal);
        if (!$check['valid']) {
            return response()->json(['valid' => false, 'message' => $check['message']]);
        }

        $discount = $coupon->calculateDiscount($subtotal);

        return response()->json([
            'valid'   => true,
            'message' => $check['message'],
            'coupon'  => [
                'id'             => $coupon->id,
                'code'           => $coupon->code,
                'description'    => $coupon->description,
                'discount_type'  => $coupon->discount_type,
                'discount_value' => (float) $coupon->discount_value,
                'calculated_discount' => $discount,
            ],
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'paid_amount' => 'required|numeric|min:0',
            'idempotency_key' => 'nullable|string|max:64',
        ]);

        // Checkout ganda (klik dobel, atau koneksi putus lalu kasir mengulang) tidak
        // boleh menghasilkan dua transaksi: keduanya akan tersync ke ERP HPY sebagai
        // penjualan yang sama-sama sah dan tidak bisa dibedakan lagi sesudahnya.
        // Kunci dikirim klien, satu nilai per isi keranjang, dan hanya diganti setelah
        // checkout berhasil.
        $idempotencyKey = $request->input('idempotency_key');
        if ($idempotencyKey) {
            $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'transaction' => $existing->load('items.product', 'customer', 'user'),
                    'invoice_no' => $existing->invoice_no,
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $defaultWarehouse = Warehouse::getDefault();
            $subtotal = 0;
            $taxAmount = 0;
            $itemsData = [];

            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                $qty = $item['quantity'];
                $price = $item['price'];
                $discount = $item['discount_amount'] ?? 0;
                $taxRate = $product->tax_rate ?? 0;
                $itemSubtotal = ($price * $qty) - $discount;
                $itemTax = $itemSubtotal * ($taxRate / 100);

                $subtotal += $itemSubtotal;
                $taxAmount += $itemTax;

                // Update stock
                if ($product->track_stock) {
                    $product->decrement('stock', $qty);
                    if ($defaultWarehouse) {
                        ProductStock::forProductWarehouse($product->id, $defaultWarehouse->id)
                            ->decrementQty($qty);
                    }
                }

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'price' => $price,
                    'cost_price' => $product->cost_price,
                    'quantity' => $qty,
                    'discount_amount' => $discount,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $itemTax,
                    'subtotal' => $itemSubtotal + $itemTax,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $discountAmount = $request->discount_amount ?? 0;
            $discountPercent = $request->discount_percent ?? 0;
            if ($discountPercent > 0) {
                $discountAmount = $subtotal * ($discountPercent / 100);
            }

            // Coupon discount
            $couponCode     = null;
            $couponDiscount = 0;
            if ($request->coupon_code) {
                $coupon = Coupon::where('code', strtoupper(trim($request->coupon_code)))->first();
                if ($coupon) {
                    $check = $coupon->isValid($subtotal);
                    if ($check['valid']) {
                        $couponCode     = $coupon->code;
                        $couponDiscount = $coupon->calculateDiscount($subtotal);
                        $coupon->increment('used_count');
                    }
                }
            }

            $orderType        = $request->order_type ?? 'dine_in';
            $deliveryPlatform = $request->delivery_platform ?? null;

            // Service Charge & PB1 — hanya untuk Dine In
            $serviceChargePct    = 0;
            $serviceChargeAmount = 0;
            $pb1Pct              = 0;
            $pb1Amount           = 0;

            if ($orderType === 'dine_in') {
                $base = $subtotal - $discountAmount - $couponDiscount;

                $scEnabled = \App\Models\Setting::get('service_charge_enabled', '0') === '1';
                if ($scEnabled) {
                    $serviceChargePct    = (float) \App\Models\Setting::get('service_charge_pct', '0');
                    $serviceChargeAmount = round($base * $serviceChargePct / 100, 2);
                }

                $pb1Enabled = \App\Models\Setting::get('pb1_enabled', '0') === '1';
                if ($pb1Enabled) {
                    $pb1Pct    = (float) \App\Models\Setting::get('pb1_pct', '0');
                    $pb1Amount = round(($base + $serviceChargeAmount) * $pb1Pct / 100, 2);
                }
            }

            $total      = $subtotal + $taxAmount - $discountAmount - $couponDiscount + $serviceChargeAmount + $pb1Amount;
            $paidAmount = $request->paid_amount;
            $change     = $paidAmount - $total;

            $transaction = Transaction::create([
                'invoice_no'            => Transaction::generateInvoiceNo(),
                'idempotency_key'       => $idempotencyKey ?: null,
                'user_id'               => Auth::id(),
                'customer_id'           => $request->customer_id,
                'status'                => 'completed',
                'subtotal'              => $subtotal,
                'discount_amount'       => $discountAmount,
                'discount_percent'      => $discountPercent,
                'coupon_code'           => $couponCode,
                'coupon_discount'       => $couponDiscount,
                'tax_amount'            => $taxAmount,
                'total'                 => $total,
                'paid_amount'           => $paidAmount,
                'change_amount'         => max(0, $change),
                'payment_method'        => $request->payment_method,
                'payment_details'       => $request->payment_details,
                'notes'                 => $request->notes,
                'pos_class'             => $request->pos_class,
                'order_type'            => $orderType,
                'delivery_platform'     => $deliveryPlatform,
                'table_number'          => $request->table_number ?: null,
                'service_charge_pct'    => $serviceChargePct,
                'service_charge_amount' => $serviceChargeAmount,
                'pb1_pct'               => $pb1Pct,
                'pb1_amount'            => $pb1Amount,
                'erp_sync_status'       => 'pending',
            ]);

            $transaction->items()->insert(array_map(fn($i) => array_merge($i, ['transaction_id' => $transaction->id]), $itemsData));

            // Update customer total purchase
            if ($request->customer_id) {
                Customer::where('id', $request->customer_id)
                    ->increment('total_purchase', $total);
            }

            DB::commit();

            // Auto-sync to ERPNext if configured, reachable, and auto-sync is enabled
            try {
                $autoSync = \App\Models\Setting::get('erp_auto_sync', '1') === '1';
                $erp = new ErpNextService();
                if ($autoSync && $erp->isConfigured()) {
                    $erp->syncTransaction($transaction->load('items.product', 'customer'));
                }
            } catch (\Exception $e) {
                // Silent — sync failure must not affect checkout response
            }

            return response()->json([
                'success' => true,
                'transaction' => $transaction->load('items.product', 'customer', 'user'),
                'invoice_no' => $transaction->invoice_no,
            ]);

        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Dua request kembar tiba nyaris bersamaan: keduanya lolos pemeriksaan di
            // awal, lalu unique index menolak yang kalah cepat. Transaksinya sudah
            // dibuat oleh request pemenang, jadi kembalikan itu — bukan error.
            DB::rollBack();

            $winner = $idempotencyKey
                ? Transaction::where('idempotency_key', $idempotencyKey)->first()
                : null;

            if (! $winner) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
            }

            return response()->json([
                'success' => true,
                'duplicate' => true,
                'transaction' => $winner->load('items.product', 'customer', 'user'),
                'invoice_no' => $winner->invoice_no,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function receipt(Transaction $transaction)
    {
        $transaction->load('items.product', 'customer', 'user');
        $store = SettingsController::storeSettings();
        return view('pos.receipt', compact('transaction', 'store'));
    }

    public function printReceipt(Transaction $transaction)
    {
        $transaction->load('items.product', 'customer', 'user');
        $store = SettingsController::storeSettings();
        return view('pos.print-receipt', compact('transaction', 'store'));
    }

    public function printKitchen(Request $request, Transaction $transaction)
    {
        $transaction->load('items.product.itemCategory', 'customer', 'user');
        $store = SettingsController::storeSettings();

        // Nomor meja: dari query (saat checkout) atau tersimpan di transaksi (cetak ulang)
        $table = trim((string) $request->query('table', $transaction->table_number ?? ''));

        // Kelompokkan item per kategori: MAKANAN / MINUMAN / dll.
        // Item tanpa kategori masuk ke "LAINNYA".
        $groups = $transaction->items
            ->groupBy(fn($item) => $item->product?->itemCategory?->name ?: 'LAINNYA')
            ->sortKeys();

        return view('pos.print-kitchen', compact('transaction', 'store', 'groups', 'table'));
    }

    /**
     * Struk dapur untuk pesanan yang belum disubmit (ditahan / keranjang berjalan).
     * Tidak menyimpan apa pun — hanya merender view yang sama dari data draft,
     * supaya dapur bisa mulai memasak sebelum pembayaran.
     */
    public function printKitchenDraft(Request $request)
    {
        $data = $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.id'     => 'nullable|integer',
            'items.*.name'   => 'required|string|max:200',
            'items.*.qty'    => 'required|numeric|min:0.01',
            'table'          => 'nullable|string|max:20',
            'order_type'     => 'nullable|string|max:20',
            'customer_name'  => 'nullable|string|max:150',
            'notes'          => 'nullable|string|max:500',
        ]);

        $store = SettingsController::storeSettings();
        $table = trim((string) ($data['table'] ?? ''));

        // Ambil kategori produk sekali jalan untuk pengelompokan.
        $ids       = collect($data['items'])->pluck('id')->filter()->all();
        $products  = Product::with('itemCategory')->whereIn('id', $ids)->get()->keyBy('id');

        $items = collect($data['items'])->map(fn ($row) => (object) [
            'product_name' => $row['name'],
            'quantity'     => 0 + $row['qty'],
            // get() — bukan akses array — supaya produk yang sudah dihapus
            // tidak membuat cetak gagal, cukup masuk kategori LAINNYA.
            'category'     => $products->get($row['id'] ?? null)?->itemCategory?->name ?: 'LAINNYA',
        ]);

        $groups = $items->groupBy('category')->sortKeys();

        $customerName = trim((string) ($data['customer_name'] ?? ''));

        $transaction = (object) [
            'invoice_no' => 'BELUM DISUBMIT',
            'created_at' => now(),
            'user'       => (object) ['name' => auth()->user()->name],
            'customer'   => $customerName !== '' ? (object) ['name' => $customerName] : null,
            'order_type' => $data['order_type'] ?? null,
            'notes'      => $data['notes'] ?? null,
            'items'      => $items,
        ];

        return view('pos.print-kitchen', compact('transaction', 'store', 'groups', 'table'));
    }

    public function directPrint(Transaction $transaction)
    {
        $transaction->load('items.product', 'customer', 'user');
        $store = SettingsController::storeSettings();

        try {
            (new \App\Services\ThermalPrintService())->printReceipt($transaction, $store);

            return response()->json([
                'success' => true,
                'message' => 'Struk berhasil dikirim ke printer.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
