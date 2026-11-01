<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Services\ErpNextService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Stock Entry transfer barang: POST ulang berarti stok ERP termutasi dua kali,
 * jadi penjaganya diuji terpisah dari POS Invoice.
 */
class StockEntryIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_company', 'HPY');
    }

    private function service(array $responses): ErpNextService
    {
        $this->sent = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        $service = new ErpNextService;
        $prop = new ReflectionProperty(ErpNextService::class, 'client');
        $prop->setAccessible(true);
        $prop->setValue($service, new Client(['handler' => $stack, 'base_uri' => 'https://erp.example.test']));

        return $service;
    }

    private function postCount(): int
    {
        return count(array_filter($this->sent, fn ($t) => $t['request']->getMethod() === 'POST'));
    }

    private function transfer(array $attrs = []): StockTransfer
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'a@test.local',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
        $product = Product::create([
            'name' => 'Kopi', 'sku' => 'KOPI-1', 'price' => 10000,
            'stock' => 100, 'is_active' => true, 'track_stock' => true,
        ]);

        $transfer = StockTransfer::create(array_merge([
            'transfer_no' => 'STO-20260802-0001', 'type' => 'outgoing',
            'status' => 'draft', 'local_status' => 'draft',
            'from_warehouse' => 'Gudang A', 'to_warehouse' => 'Gudang B',
            'user_id' => $user->id, 'erp_sync_status' => 'pending',
        ], $attrs));

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id, 'product_id' => $product->id,
            'item_code' => 'KOPI-1', 'item_name' => 'Kopi', 'sku' => 'KOPI-1',
            'quantity' => 5, 'unit' => 'Nos',
        ]);

        return $transfer->load('items.product');
    }

    public function test_transfer_yang_sudah_synced_tidak_dipost_ulang(): void
    {
        $transfer = $this->transfer(['erp_stock_entry' => 'MAT-STE-001', 'erp_sync_status' => 'synced']);
        $stokAwal = Product::first()->stock;

        $result = $this->service([])->createOutgoingTransfer($transfer);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->postCount());
        $this->assertSame($stokAwal, Product::first()->stock, 'stok lokal tidak boleh dipotong dua kali');
    }

    /**
     * Dokumen ada tapi belum tersubmit: POST ulang akan menggandakan mutasi, tapi
     * melaporkannya "sukses" juga salah karena stoknya belum benar-benar berpindah.
     */
    public function test_transfer_dengan_dokumen_draft_ditolak_dengan_pesan_jelas(): void
    {
        $transfer = $this->transfer(['erp_stock_entry' => 'MAT-STE-002', 'erp_sync_status' => 'failed']);

        $result = $this->service([])->createOutgoingTransfer($transfer);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $this->postCount());
        $this->assertStringContainsString('MAT-STE-002', $result['error']);
        $this->assertStringContainsString('belum tersubmit', $result['error']);
    }

    /** Submit gagal tidak boleh menghilangkan nomor dokumen (bug yang sama dgn POS Invoice). */
    public function test_submit_gagal_tetap_menyimpan_nomor_stock_entry(): void
    {
        $transfer = $this->transfer();

        $result = $this->service([
            new Response(200, [], json_encode(['data' => ['name' => 'MAT-STE-003']])),  // POST sukses
            new Response(417, [], json_encode(['exception' => 'ValidationError'])),     // submit gagal
        ])->createOutgoingTransfer($transfer);

        $this->assertFalse($result['success']);
        $this->assertSame('MAT-STE-003', $transfer->fresh()->erp_stock_entry);

        // Dan retry berikutnya tidak membuat Stock Entry kedua.
        $retry = $this->service([])->createOutgoingTransfer($transfer->fresh()->load('items.product'));

        $this->assertFalse($retry['success']);
        $this->assertSame(0, $this->postCount());
    }

    public function test_transfer_baru_tetap_dikirim_normal(): void
    {
        $transfer = $this->transfer();

        $result = $this->service([
            new Response(200, [], json_encode(['data' => ['name' => 'MAT-STE-004']])),
            new Response(200, [], json_encode(['data' => ['name' => 'MAT-STE-004']])),
        ])->createOutgoingTransfer($transfer);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->postCount());
        $this->assertSame('synced', $transfer->fresh()->erp_sync_status);
    }
}
