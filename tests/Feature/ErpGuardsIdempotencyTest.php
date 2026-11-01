<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderPayment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Slice;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\User;
use App\Services\ErpNextService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Penjaga idempoten untuk doctype sisanya: Payment Entry, Material Request, dan
 * Stock Entry repack. Ketiganya sebelumnya hanya dijaga di controller, sehingga
 * jalur lain (mis. auto-sync saat konfirmasi order) masih bisa memPOST ulang.
 */
class ErpGuardsIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_company', 'HPY');
    }

    /** Mock tanpa respons: kalau kode nekat mengirim HTTP, tesnya gagal — itu memang tujuannya. */
    private function service(): ErpNextService
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler([]));
        $stack->push(Middleware::history($this->sent));

        $service = new ErpNextService;
        $prop = new ReflectionProperty(ErpNextService::class, 'client');
        $prop->setAccessible(true);
        $prop->setValue($service, new Client(['handler' => $stack, 'base_uri' => 'https://erp.example.test']));

        return $service;
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'a@test.local',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
    }

    public function test_payment_entry_yang_sudah_ada_tidak_dipost_ulang(): void
    {
        $user = $this->user();
        $customer = Customer::create(['code' => 'CUST-001', 'name' => 'PT Uji', 'erp_customer_name' => 'PT Uji']);

        $order = DeliveryOrder::create([
            'order_no' => 'DO-20260802-0001', 'customer_id' => $customer->id,
            'order_date' => now()->toDateString(), 'delivery_date' => now()->toDateString(),
            'status' => 'confirmed', 'payment_status' => 'partial',
            'subtotal' => 100000, 'total' => 100000, 'created_by' => $user->id,
            'erp_sales_order' => 'SAL-ORD-001', 'erp_sales_invoice' => 'ACC-SINV-001',
        ]);

        $payment = DeliveryOrderPayment::create([
            // 'cash' huruf kecil: di sqlite kolomnya masih enum lama (migrasi
            // pengubahan ke varchar khusus MySQL). Tidak memengaruhi yang diuji.
            'delivery_order_id' => $order->id, 'payment_method' => 'cash',
            'amount' => 50000, 'payment_date' => now()->toDateString(),
            'created_by' => $user->id,
            'erp_payment_entry' => 'ACC-PAY-001', 'erp_sync_status' => 'failed',
        ]);

        $result = $this->service()->createPaymentEntry($payment);

        $this->assertTrue($result['success']);
        $this->assertSame('ACC-PAY-001', $result['docname']);
        $this->assertCount(0, $this->sent, 'tidak boleh ada request ke ERP sama sekali');
    }

    public function test_material_request_yang_sudah_ada_tidak_dipost_ulang(): void
    {
        $user = $this->user();
        $product = Product::create([
            'name' => 'Kopi', 'sku' => 'KOPI-1', 'price' => 10000,
            'stock' => 100, 'is_active' => true, 'track_stock' => true,
        ]);

        $request = StockRequest::create([
            'request_no' => 'FG-20260802-0001', 'requested_by' => $user->id,
            'status' => 'submitted', 'needed_date' => now()->toDateString(),
            'erp_material_request' => 'MAT-MR-001', 'erp_sync_status' => 'failed',
        ]);

        StockRequestItem::create([
            'stock_request_id' => $request->id, 'product_id' => $product->id,
            'item_code' => 'KOPI-1', 'item_name' => 'Kopi', 'qty' => 3, 'uom' => 'Nos',
        ]);

        $result = $this->service()->createMaterialRequest($request->load('items.product'));

        $this->assertTrue($result['success']);
        $this->assertSame('MAT-MR-001', $result['docname']);
        $this->assertCount(0, $this->sent);
    }

    public function test_repack_yang_sudah_synced_tidak_dipost_ulang(): void
    {
        $slice = Slice::create([
            'slice_no' => 'SLC-20260802-0001', 'created_by' => $this->user()->id,
            'status' => 'submitted',
            'erp_stock_entry' => 'MAT-STE-100', 'erp_sync_status' => 'synced',
        ]);

        $result = $this->service()->createRepackEntry($slice);

        $this->assertTrue($result['success']);
        $this->assertSame('MAT-STE-100', $result['docname']);
        $this->assertCount(0, $this->sent);
    }

    /** Repack berdokumen draft: ditolak dengan pesan jelas, bukan dilaporkan sukses palsu. */
    public function test_repack_dengan_dokumen_draft_ditolak(): void
    {
        $slice = Slice::create([
            'slice_no' => 'SLC-20260802-0002', 'created_by' => $this->user()->id,
            'status' => 'submitted',
            'erp_stock_entry' => 'MAT-STE-101', 'erp_sync_status' => 'failed',
        ]);

        $result = $this->service()->createRepackEntry($slice);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('MAT-STE-101', $result['error']);
        $this->assertCount(0, $this->sent);
    }
}
