<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
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
 * Mengunci perilaku anti-dokumen-ganda pada sync POS Invoice ke ERP HPY.
 * Lapisan HTTP-nya dipalsukan lewat Guzzle MockHandler; tidak ada panggilan keluar.
 */
class PosInvoiceSyncIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,\Psr\Http\Message\RequestInterface> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_api_key', 'k');
        Setting::set('erpnext_api_secret', 's');
        Setting::set('erpnext_company', 'HPY');
    }

    /** Bangun service dengan HTTP client palsu yang mengembalikan $responses berurutan. */
    private function service(array $responses): ErpNextService
    {
        $this->sent = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->sent));

        $service = new ErpNextService;

        // Client dibuat di konstruktor dan tidak injectable; ditukar lewat refleksi
        // supaya kode produksi tidak perlu diberi lubang khusus tes.
        $prop = new ReflectionProperty(ErpNextService::class, 'client');
        $prop->setAccessible(true);
        $prop->setValue($service, new Client([
            'handler' => $stack,
            'base_uri' => 'https://erp.example.test',
        ]));

        return $service;
    }

    private function transaction(array $attrs = []): Transaction
    {
        $product = Product::create([
            'name' => 'Kopi', 'sku' => 'KOPI-1', 'price' => 10000,
            'stock' => 100, 'is_active' => true, 'track_stock' => false,
        ]);

        $user = User::create([
            'name' => 'Kasir', 'email' => 'kasir@test.local',
            'password' => bcrypt('x'), 'role' => 'kasir',
        ]);

        $tx = Transaction::create(array_merge([
            'invoice_no' => 'INV-20260802-0001',
            'user_id' => $user->id,
            'status' => 'completed',
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 10000,
            'change_amount' => 0, 'payment_method' => 'cash',
            'erp_sync_status' => 'pending',
        ], $attrs));

        TransactionItem::create([
            'transaction_id' => $tx->id, 'product_id' => $product->id,
            'product_name' => 'Kopi', 'product_sku' => 'KOPI-1',
            'quantity' => 1, 'price' => 10000, 'subtotal' => 10000,
            'discount_amount' => 0,
        ]);

        return $tx->load('items.product');
    }

    /** Daftar "METHOD path" dari tiap request yang benar-benar terkirim. */
    private function sentCalls(): array
    {
        return array_map(
            fn ($t) => $t['request']->getMethod().' '.$t['request']->getUri()->getPath(),
            $this->sent
        );
    }

    private function postCount(): int
    {
        return count(array_filter($this->sent, fn ($t) => $t['request']->getMethod() === 'POST'));
    }

    public function test_sync_normal_membuat_dan_submit_satu_invoice(): void
    {
        $tx = $this->transaction();

        $result = $this->service([
            new Response(200, [], json_encode(['data' => []])),                       // lookup po_no
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-001']])),  // POST
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-001']])),  // submit
        ])->syncTransaction($tx);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->postCount());
        $this->assertSame('PSINV-001', $tx->fresh()->erp_pos_invoice);
        $this->assertSame('synced', $tx->fresh()->erp_sync_status);
    }

    /**
     * Inti perbaikannya: submit gagal TIDAK boleh menghilangkan nomor dokumen,
     * kalau tidak sync berikutnya membuat POS Invoice kedua.
     */
    public function test_submit_gagal_tetap_menyimpan_nomor_dokumen(): void
    {
        $tx = $this->transaction();

        $result = $this->service([
            new Response(200, [], json_encode(['data' => []])),                       // lookup
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-002']])),  // POST sukses
            new Response(417, [], json_encode(['exception' => 'ValidationError'])),   // submit GAGAL
        ])->syncTransaction($tx);

        $this->assertFalse($result['success']);
        $this->assertSame('PSINV-002', $tx->fresh()->erp_pos_invoice, 'docname wajib tersimpan meski submit gagal');
        $this->assertSame('failed', $tx->fresh()->erp_sync_status);
    }

    /** Lanjutan: retry setelah submit gagal hanya menyelesaikan submit, tanpa POST baru. */
    public function test_retry_setelah_submit_gagal_tidak_membuat_invoice_kedua(): void
    {
        $tx = $this->transaction(['erp_pos_invoice' => 'PSINV-002', 'erp_sync_status' => 'failed']);

        $result = $this->service([
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-002']])),  // submit saja
        ])->syncTransaction($tx);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->postCount(), 'tidak boleh ada POST dokumen baru');
        $this->assertSame(['PUT /api/resource/POS%20Invoice/PSINV-002'], $this->sentCalls());
        $this->assertSame('synced', $tx->fresh()->erp_sync_status);
    }

    public function test_transaksi_yang_sudah_synced_tidak_dipost_ulang(): void
    {
        $tx = $this->transaction(['erp_pos_invoice' => 'PSINV-003', 'erp_sync_status' => 'synced']);

        $result = $this->service([
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-003']])),  // submit ulang
        ])->syncTransaction($tx);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->postCount());
    }

    /** Jaring pengaman timeout: invoice sudah ada di ERP (po_no cocok) → diadopsi, bukan dibuat ulang. */
    public function test_invoice_yang_sudah_ada_di_erp_diadopsi_lewat_po_no(): void
    {
        $tx = $this->transaction();

        $result = $this->service([
            new Response(200, [], json_encode(['data' => [['name' => 'PSINV-004']]])), // lookup KETEMU
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-004']])),   // submit
        ])->syncTransaction($tx);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $this->postCount(), 'invoice yang sudah ada tidak boleh dibuat ulang');
        $this->assertSame('PSINV-004', $tx->fresh()->erp_pos_invoice);
    }

    /** Pencarian po_no yang error tidak boleh memblokir sync — turun ke perilaku semula. */
    public function test_lookup_po_no_gagal_tidak_memblokir_sync(): void
    {
        $tx = $this->transaction();

        $result = $this->service([
            new Response(500, [], 'boom'),                                            // lookup error
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-005']])),  // POST tetap jalan
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-005']])),  // submit
        ])->syncTransaction($tx);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->postCount());
        $this->assertSame('PSINV-005', $tx->fresh()->erp_pos_invoice);
    }

    /** Payload wajib membawa po_no — tanpa itu jaring pengaman di atas tak punya kunci cari. */
    public function test_payload_membawa_nomor_invoice_lokal_sebagai_po_no(): void
    {
        $tx = $this->transaction();

        $this->service([
            new Response(200, [], json_encode(['data' => []])),
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-006']])),
            new Response(200, [], json_encode(['data' => ['name' => 'PSINV-006']])),
        ])->syncTransaction($tx);

        $post = collect($this->sent)->first(fn ($t) => $t['request']->getMethod() === 'POST');
        $body = json_decode((string) $post['request']->getBody(), true);

        $this->assertSame('INV-20260802-0001', $body['po_no'] ?? null);
    }
}
