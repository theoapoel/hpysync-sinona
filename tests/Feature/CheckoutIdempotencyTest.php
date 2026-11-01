<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkout ganda tidak boleh menghasilkan dua transaksi. Duplikat di sini paling
 * berbahaya karena di ERP HPY keduanya tampak sebagai penjualan yang sah dan tidak
 * bisa dibedakan lagi setelah tersimpan.
 */
class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_api_key', 'k');
        Setting::set('erpnext_api_secret', 's');
        // Auto-sync dimatikan: yang diuji pembuatan transaksinya, bukan jalur ERP.
        Setting::set('erp_auto_sync', '0');
    }

    private function kasir(): User
    {
        $user = User::create([
            'name' => 'Kasir', 'email' => 'kasir@test.local',
            'password' => bcrypt('x'), 'role' => 'kasir',
        ]);
        RolePermission::updateOrCreate(['role' => 'kasir', 'module' => 'pos'], ['allowed' => true]);
        RolePermission::clearCache('kasir');

        return $user;
    }

    private function payload(Product $product, Customer $customer, ?string $key): array
    {
        return array_filter([
            'idempotency_key' => $key,
            'items' => [[
                'product_id' => $product->id, 'quantity' => 2,
                'price' => 10000, 'discount_amount' => 0,
            ]],
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'paid_amount' => 20000,
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Kopi', 'sku' => 'KOPI-1', 'price' => 10000,
            'stock' => 100, 'is_active' => true, 'track_stock' => true,
        ]);
    }

    private function customer(): Customer
    {
        return Customer::create(['code' => 'CUST-001', 'name' => 'Walk-in']);
    }

    public function test_dua_checkout_dengan_kunci_sama_hanya_membuat_satu_transaksi(): void
    {
        $kasir = $this->kasir();
        $product = $this->product();
        $customer = $this->customer();
        $payload = $this->payload($product, $customer, 'kunci-abc');

        $first = $this->actingAs($kasir)->postJson('/pos/checkout', $payload);
        $second = $this->actingAs($kasir)->postJson('/pos/checkout', $payload);

        $first->assertOk()->assertJson(['success' => true]);
        $second->assertOk()->assertJson(['success' => true, 'duplicate' => true]);

        $this->assertSame(1, Transaction::count(), 'hanya boleh ada satu transaksi');
        $this->assertSame(
            $first->json('invoice_no'),
            $second->json('invoice_no'),
            'request kedua harus mengembalikan invoice yang sama'
        );
    }

    public function test_checkout_ganda_tidak_memotong_stok_dua_kali(): void
    {
        $kasir = $this->kasir();
        $product = $this->product();
        $customer = $this->customer();
        $payload = $this->payload($product, $customer, 'kunci-stok');

        $this->actingAs($kasir)->postJson('/pos/checkout', $payload)->assertOk();
        $this->actingAs($kasir)->postJson('/pos/checkout', $payload)->assertOk();

        $this->assertSame(98.0, (float) $product->fresh()->stock, 'stok hanya boleh berkurang 2');
    }

    public function test_kunci_berbeda_tetap_menghasilkan_transaksi_terpisah(): void
    {
        $kasir = $this->kasir();
        $product = $this->product();
        $customer = $this->customer();

        $this->actingAs($kasir)->postJson('/pos/checkout', $this->payload($product, $customer, 'kunci-1'))->assertOk();
        $this->actingAs($kasir)->postJson('/pos/checkout', $this->payload($product, $customer, 'kunci-2'))->assertOk();

        $this->assertSame(2, Transaction::count(), 'penjualan berbeda tidak boleh ikut diblokir');
        $this->assertSame(96.0, (float) $product->fresh()->stock);
    }

    /** Klien lama yang belum mengirim kunci harus tetap bisa checkout. */
    public function test_checkout_tanpa_kunci_tetap_berjalan(): void
    {
        $kasir = $this->kasir();
        $product = $this->product();
        $customer = $this->customer();

        $this->actingAs($kasir)->postJson('/pos/checkout', $this->payload($product, $customer, null))->assertOk();
        $this->actingAs($kasir)->postJson('/pos/checkout', $this->payload($product, $customer, null))->assertOk();

        $this->assertSame(2, Transaction::count());
        $this->assertNull(Transaction::first()->idempotency_key);
    }

    /**
     * Transaksi berkunci sama yang sudah ada — mis. dibuat request lain sesaat
     * sebelumnya — dikenali dan dikembalikan apa adanya, tanpa memotong stok.
     *
     * Catatan cakupan: yang menangkap di sini adalah pemeriksaan di awal checkout().
     * Cabang penangkap UniqueConstraintViolationException (dua request yang sama-sama
     * lolos pemeriksaan awal lalu ditolak unique index) tidak tercakup tes ini —
     * mensimulasikannya butuh dua koneksi DB yang menulis bersamaan, yang tidak bisa
     * ditiru dengan sqlite :memory: koneksi tunggal.
     */
    public function test_transaksi_berkunci_sama_dikenali_dan_tidak_memotong_stok(): void
    {
        $kasir = $this->kasir();
        $product = $this->product();
        $customer = $this->customer();

        $pemenang = Transaction::create([
            'invoice_no' => 'INV-PEMENANG', 'idempotency_key' => 'kunci-balap',
            'user_id' => $kasir->id, 'status' => 'completed',
            'subtotal' => 20000, 'total' => 20000, 'paid_amount' => 20000,
            'change_amount' => 0, 'payment_method' => 'cash', 'erp_sync_status' => 'pending',
        ]);

        $response = $this->actingAs($kasir)
            ->postJson('/pos/checkout', $this->payload($product, $customer, 'kunci-balap'));

        $response->assertOk()->assertJson([
            'success' => true, 'duplicate' => true, 'invoice_no' => $pemenang->invoice_no,
        ]);
        $this->assertSame(1, Transaction::count());
        $this->assertSame(100.0, (float) $product->fresh()->stock, 'stok tidak boleh terpotong');
    }

    /**
     * Penahan terakhir untuk kasus balapan adalah unique index di database, bukan
     * pemeriksaan di PHP. Kalau index-nya tidak benar-benar menegakkan keunikan,
     * dua request bersamaan bisa sama-sama tersimpan.
     */
    public function test_database_menolak_kunci_idempoten_kembar(): void
    {
        $kasir = $this->kasir();

        $buat = fn (string $invoiceNo) => Transaction::create([
            'invoice_no' => $invoiceNo, 'idempotency_key' => 'kunci-kembar',
            'user_id' => $kasir->id, 'status' => 'completed',
            'subtotal' => 1000, 'total' => 1000, 'paid_amount' => 1000,
            'change_amount' => 0, 'payment_method' => 'cash', 'erp_sync_status' => 'pending',
        ]);

        $buat('INV-A');

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $buat('INV-B');
    }

    /** Kunci null tidak boleh ikut terkena unique index (banyak baris lama bernilai null). */
    public function test_kunci_null_boleh_berulang(): void
    {
        $kasir = $this->kasir();

        foreach (['INV-X', 'INV-Y'] as $no) {
            Transaction::create([
                'invoice_no' => $no, 'idempotency_key' => null,
                'user_id' => $kasir->id, 'status' => 'completed',
                'subtotal' => 1000, 'total' => 1000, 'paid_amount' => 1000,
                'change_amount' => 0, 'payment_method' => 'cash', 'erp_sync_status' => 'pending',
            ]);
        }

        $this->assertSame(2, Transaction::count());
    }
}
