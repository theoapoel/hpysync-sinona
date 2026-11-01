<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── USERS ────────────────────────────────────────────────────
        $admin = User::create([
            'name'     => 'Admin LaraPos',
            'email'    => 'admin@larapos.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'pin'      => '123456',
            'is_active'=> true,
        ]);

        User::create([
            'name'     => 'Budi Kasir',
            'email'    => 'kasir@larapos.com',
            'password' => Hash::make('password'),
            'role'     => 'cashier',
            'pin'      => '654321',
            'is_active'=> true,
        ]);

        User::create([
            'name'     => 'Siti Manager',
            'email'    => 'manager@larapos.com',
            'password' => Hash::make('password'),
            'role'     => 'manager',
            'pin'      => '111222',
            'is_active'=> true,
        ]);

        // ─── CATEGORIES ───────────────────────────────────────────────
        $categories = [
            ['name' => 'Minuman',      'slug' => 'minuman',      'color' => '#4285F4', 'icon' => '🥤', 'erp_item_group' => 'Beverages'],
            ['name' => 'Makanan',      'slug' => 'makanan',      'color' => '#EA4335', 'icon' => '🍔', 'erp_item_group' => 'Food'],
            ['name' => 'Snack',        'slug' => 'snack',        'color' => '#FBBC05', 'icon' => '🍿', 'erp_item_group' => 'Snacks'],
            ['name' => 'Sembako',      'slug' => 'sembako',      'color' => '#34A853', 'icon' => '🛒', 'erp_item_group' => 'Groceries'],
            ['name' => 'Rokok',        'slug' => 'rokok',        'color' => '#9C27B0', 'icon' => '🚬', 'erp_item_group' => 'Tobacco'],
            ['name' => 'Perawatan',    'slug' => 'perawatan',    'color' => '#00BCD4', 'icon' => '🧴', 'erp_item_group' => 'Personal Care'],
        ];

        $cats = [];
        foreach ($categories as $c) {
            $cats[$c['slug']] = Category::create($c);
        }

        // ─── PRODUCTS ─────────────────────────────────────────────────
        $products = [
            // Minuman
            ['sku'=>'MIN-001','barcode'=>'8992696130104','name'=>'Aqua 600ml',          'category'=>'minuman','price'=>4000, 'cost'=>2500,'stock'=>120,'unit'=>'botol'],
            ['sku'=>'MIN-002','barcode'=>'8992696130203','name'=>'Teh Botol Sosro 350ml','category'=>'minuman','price'=>5000, 'cost'=>3000,'stock'=>80, 'unit'=>'botol'],
            ['sku'=>'MIN-003','barcode'=>'8992696130302','name'=>'Coca Cola 330ml Can',  'category'=>'minuman','price'=>8000, 'cost'=>5500,'stock'=>60, 'unit'=>'kaleng'],
            ['sku'=>'MIN-004','barcode'=>'8992696130401','name'=>'Kopi Good Day Sachet', 'category'=>'minuman','price'=>2500, 'cost'=>1500,'stock'=>200,'unit'=>'sachet'],
            ['sku'=>'MIN-005','barcode'=>'8992696130500','name'=>'Ultra Milk Full Cream', 'category'=>'minuman','price'=>6500, 'cost'=>4500,'stock'=>45, 'unit'=>'kotak'],
            ['sku'=>'MIN-006','barcode'=>'8992696130609','name'=>'Pocari Sweat 500ml',   'category'=>'minuman','price'=>9000, 'cost'=>6000,'stock'=>55, 'unit'=>'botol'],

            // Makanan
            ['sku'=>'MAK-001','barcode'=>'8993244130101','name'=>'Indomie Goreng',        'category'=>'makanan','price'=>3500, 'cost'=>2200,'stock'=>300,'unit'=>'bungkus'],
            ['sku'=>'MAK-002','barcode'=>'8993244130202','name'=>'Indomie Soto',          'category'=>'makanan','price'=>3500, 'cost'=>2200,'stock'=>250,'unit'=>'bungkus'],
            ['sku'=>'MAK-003','barcode'=>'8993244130303','name'=>'Sarimi Soto Koya',      'category'=>'makanan','price'=>2500, 'cost'=>1500,'stock'=>180,'unit'=>'bungkus'],
            ['sku'=>'MAK-004','barcode'=>'8993244130404','name'=>'Pop Mie Cup',           'category'=>'makanan','price'=>5000, 'cost'=>3000,'stock'=>100,'unit'=>'cup'],
            ['sku'=>'MAK-005','barcode'=>'8993244130505','name'=>'Beras Premium 5kg',     'category'=>'makanan','price'=>75000,'cost'=>60000,'stock'=>30, 'unit'=>'karung'],

            // Snack
            ['sku'=>'SNK-001','barcode'=>'8991101130101','name'=>'Chitato Ori 68g',       'category'=>'snack','price'=>10000,'cost'=>7000,'stock'=>80, 'unit'=>'pcs'],
            ['sku'=>'SNK-002','barcode'=>'8991101130202','name'=>'Cheetos 55g',           'category'=>'snack','price'=>8000, 'cost'=>5500,'stock'=>90, 'unit'=>'pcs'],
            ['sku'=>'SNK-003','barcode'=>'8991101130303','name'=>'Qtela Singkong 65g',    'category'=>'snack','price'=>8500, 'cost'=>6000,'stock'=>70, 'unit'=>'pcs'],
            ['sku'=>'SNK-004','barcode'=>'8991101130404','name'=>'Taro Net Original 105g','category'=>'snack','price'=>9000, 'cost'=>6500,'stock'=>60, 'unit'=>'pcs'],
            ['sku'=>'SNK-005','barcode'=>'8991101130505','name'=>'Oreo Original 119g',    'category'=>'snack','price'=>11000,'cost'=>7500,'stock'=>75, 'unit'=>'pcs'],

            // Sembako
            ['sku'=>'SMB-001','barcode'=>'8990000130101','name'=>'Gula Pasir 1kg',         'category'=>'sembako','price'=>15000,'cost'=>12000,'stock'=>50,'unit'=>'kg'],
            ['sku'=>'SMB-002','barcode'=>'8990000130202','name'=>'Minyak Goreng Bimoli 1L','category'=>'sembako','price'=>22000,'cost'=>18000,'stock'=>40,'unit'=>'botol'],
            ['sku'=>'SMB-003','barcode'=>'8990000130303','name'=>'Tepung Terigu 1kg',      'category'=>'sembako','price'=>12000,'cost'=>9000, 'stock'=>60,'unit'=>'kg'],
            ['sku'=>'SMB-004','barcode'=>'8990000130404','name'=>'Kecap Bango 140ml',      'category'=>'sembako','price'=>10000,'cost'=>7000, 'stock'=>45,'unit'=>'botol'],

            // Rokok
            ['sku'=>'ROK-001','barcode'=>'8998100130101','name'=>'Sampoerna Mild 16',      'category'=>'rokok','price'=>30000,'cost'=>24000,'stock'=>100,'unit'=>'bungkus'],
            ['sku'=>'ROK-002','barcode'=>'8998100130202','name'=>'Gudang Garam Filter 12', 'category'=>'rokok','price'=>22000,'cost'=>17000,'stock'=>80, 'unit'=>'bungkus'],
            ['sku'=>'ROK-003','barcode'=>'8998100130303','name'=>'Djarum Super 12',        'category'=>'rokok','price'=>24000,'cost'=>19000,'stock'=>90, 'unit'=>'bungkus'],

            // Perawatan
            ['sku'=>'PRW-001','barcode'=>'8997033130101','name'=>'Sabun Lifebuoy 90g',     'category'=>'perawatan','price'=>5500, 'cost'=>3500,'stock'=>80,'unit'=>'pcs'],
            ['sku'=>'PRW-002','barcode'=>'8997033130202','name'=>'Shampo Pantene 180ml',   'category'=>'perawatan','price'=>22000,'cost'=>16000,'stock'=>40,'unit'=>'botol'],
            ['sku'=>'PRW-003','barcode'=>'8997033130303','name'=>'Pasta Gigi Pepsodent 190g','category'=>'perawatan','price'=>17000,'cost'=>12000,'stock'=>55,'unit'=>'tube'],
            ['sku'=>'PRW-004','barcode'=>'8997033130404','name'=>'Pembalut Charm 14s',     'category'=>'perawatan','price'=>19500,'cost'=>14000,'stock'=>35,'unit'=>'pack'],
        ];

        $productModels = [];
        foreach ($products as $p) {
            $productModels[] = Product::create([
                'sku'         => $p['sku'],
                'barcode'     => $p['barcode'],
                'name'        => $p['name'],
                'category_id' => $cats[$p['category']]->id,
                'price'       => $p['price'],
                'cost_price'  => $p['cost'],
                'stock'       => $p['stock'],
                'min_stock'   => (int)($p['stock'] * 0.1),
                'unit'        => $p['unit'],
                'is_active'   => true,
                'track_stock' => true,
                'tax_rate'    => 0,
            ]);
        }

        // ─── CUSTOMERS ────────────────────────────────────────────────
        $customersData = [
            ['code'=>'CUST00001','name'=>'Ahmad Fauzi',      'phone'=>'081234567890','email'=>'ahmad@email.com'],
            ['code'=>'CUST00002','name'=>'Sari Dewi',        'phone'=>'082345678901','email'=>'sari@email.com'],
            ['code'=>'CUST00003','name'=>'Budi Santoso',     'phone'=>'083456789012','email'=>null],
            ['code'=>'CUST00004','name'=>'Rina Marlina',     'phone'=>'084567890123','email'=>'rina@email.com'],
            ['code'=>'CUST00005','name'=>'Doni Pratama',     'phone'=>'085678901234','email'=>null],
            ['code'=>'CUST00006','name'=>'Toko Pak Hendra',  'phone'=>'086789012345','email'=>'hendra@toko.com'],
            ['code'=>'CUST00007','name'=>'Warung Bu Ani',    'phone'=>'087890123456','email'=>null],
            ['code'=>'CUST00008','name'=>'CV Maju Bersama',  'phone'=>'088901234567','email'=>'cv@majubersama.com'],
        ];

        $customerModels = [];
        foreach ($customersData as $c) {
            $customerModels[] = Customer::create(array_merge($c, ['is_active' => true]));
        }

        // ─── DEMO TRANSACTIONS (30 hari terakhir) ─────────────────────
        $paymentMethods = ['cash', 'cash', 'cash', 'card', 'qris', 'transfer'];

        for ($day = 29; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $txCount = rand(5, 20); // 5-20 transaksi per hari

            for ($t = 0; $t < $txCount; $t++) {
                // Pick 1-5 random products
                $itemCount  = rand(1, 5);
                $shuffled   = collect($productModels)->shuffle()->take($itemCount);
                $customer   = rand(0, 3) === 0 ? $customerModels[array_rand($customerModels)] : null;
                $method     = $paymentMethods[array_rand($paymentMethods)];

                $subtotal   = 0;
                $taxAmount  = 0;
                $itemsData  = [];

                foreach ($shuffled as $prod) {
                    $qty      = rand(1, 4);
                    $price    = $prod->price;
                    $itemSub  = $price * $qty;
                    $itemTax  = $itemSub * ($prod->tax_rate / 100);
                    $subtotal += $itemSub;
                    $taxAmount += $itemTax;

                    $itemsData[] = [
                        'product_id'    => $prod->id,
                        'product_name'  => $prod->name,
                        'product_sku'   => $prod->sku,
                        'price'         => $price,
                        'cost_price'    => $prod->cost_price,
                        'quantity'      => $qty,
                        'discount_amount'=> 0,
                        'tax_rate'      => $prod->tax_rate,
                        'tax_amount'    => $itemTax,
                        'subtotal'      => $itemSub + $itemTax,
                        'created_at'    => $date,
                        'updated_at'    => $date,
                    ];
                }

                $total      = $subtotal + $taxAmount;
                $paidAmount = ($method === 'cash')
                    ? (ceil($total / 5000) * 5000 + rand(0, 2) * 5000)
                    : $total;

                // Invoice number by date
                $seq = Transaction::whereDate('created_at', $date->toDateString())->count() + 1;
                $invoiceNo = 'INV-' . $date->format('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

                $syncStatus = $day > 3
                    ? (rand(0, 9) < 8 ? 'synced' : 'failed')
                    : (rand(0, 9) < 3 ? 'pending' : (rand(0, 5) < 4 ? 'synced' : 'failed'));

                $tx = Transaction::create([
                    'invoice_no'      => $invoiceNo,
                    'user_id'         => $admin->id,
                    'customer_id'     => $customer?->id,
                    'status'          => 'completed',
                    'subtotal'        => $subtotal,
                    'discount_amount' => 0,
                    'discount_percent'=> 0,
                    'tax_amount'      => $taxAmount,
                    'total'           => $total,
                    'paid_amount'     => $paidAmount,
                    'change_amount'   => max(0, $paidAmount - $total),
                    'payment_method'  => $method,
                    'erp_sync_status' => $syncStatus,
                    'erp_pos_invoice' => $syncStatus === 'synced' ? 'PSINV-'.$date->format('Y').'-'.str_pad(rand(1,9999),5,'0',STR_PAD_LEFT) : null,
                    'erp_synced_at'   => $syncStatus === 'synced' ? $date->addMinutes(rand(1,30)) : null,
                    'created_at'      => $date,
                    'updated_at'      => $date,
                ]);

                $tx->items()->insert(array_map(
                    fn($i) => array_merge($i, ['transaction_id' => $tx->id]),
                    $itemsData
                ));

                if ($customer) {
                    $customer->increment('total_purchase', $total);
                }
            }
        }

        // ─── SETTINGS ─────────────────────────────────────────────────
        $settings = [
            ['key' => 'store_name',          'value' => 'Toko Serba Ada',       'group' => 'general'],
            ['key' => 'store_address',        'value' => 'Jl. Merdeka No. 1, Jakarta', 'group' => 'general'],
            ['key' => 'store_phone',          'value' => '021-12345678',         'group' => 'general'],
            ['key' => 'receipt_footer',       'value' => 'Terima kasih atas kunjungan Anda!', 'group' => 'general'],
            ['key' => 'currency',             'value' => 'IDR',                  'group' => 'general'],
            ['key' => 'tax_enabled',          'value' => '0',                    'group' => 'tax'],
            ['key' => 'default_tax_rate',     'value' => '11',                   'group' => 'tax'],
            ['key' => 'erpnext_url',          'value' => '',                     'group' => 'erpnext'],
            ['key' => 'erpnext_api_key',      'value' => '',                     'group' => 'erpnext'],
            ['key' => 'erpnext_api_secret',   'value' => '',                     'group' => 'erpnext'],
            ['key' => 'erpnext_company',      'value' => 'Toko Serba Ada',       'group' => 'erpnext'],
            ['key' => 'erpnext_pos_profile',  'value' => 'Main POS Profile',     'group' => 'erpnext'],
        ];

        foreach ($settings as $s) {
            Setting::create($s);
        }

        $this->command->info('✅ Seeder selesai:');
        $this->command->info('   👤 3 users (admin@larapos.com / password)');
        $this->command->info('   📦 ' . count($products) . ' produk di ' . count($categories) . ' kategori');
        $this->command->info('   👥 ' . count($customersData) . ' customers');
        $this->command->info('   🧾 ~300 transaksi demo (30 hari)');
    }
}
