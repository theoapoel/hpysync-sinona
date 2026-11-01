<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pembatasan rentang tanggal laporan ke hari ini, dikonfigurasi per role
 * (Pengaturan Toko → report_date_limit_roles). Yang diuji penegakannya di server:
 * filter tanggal dari request harus diabaikan, bukan sekadar input UI yang di-disable.
 */
class ReportDateLimitRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tanpa ini middleware setup mengalihkan semua request ke /setup.
        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_api_key', 'k');
        Setting::set('erpnext_api_secret', 's');
    }

    private function user(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role), 'email' => $role.'@test.local',
            'password' => bcrypt('x'), 'role' => $role,
        ]);

        RolePermission::updateOrCreate(
            ['role' => $role, 'module' => 'transactions'],
            ['allowed' => true]
        );
        RolePermission::clearCache($role);

        return $user;
    }

    private function transaction(User $user, string $date, string $invoiceNo): Transaction
    {
        $product = Product::firstOrCreate(
            ['sku' => 'KOPI-1'],
            ['name' => 'Kopi', 'price' => 10000, 'stock' => 100, 'is_active' => true, 'track_stock' => false]
        );

        $tx = Transaction::create([
            'invoice_no' => $invoiceNo, 'user_id' => $user->id, 'status' => 'completed',
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 10000,
            'change_amount' => 0, 'payment_method' => 'cash', 'erp_sync_status' => 'pending',
        ]);
        $tx->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        TransactionItem::create([
            'transaction_id' => $tx->id, 'product_id' => $product->id,
            'product_name' => 'Kopi', 'product_sku' => 'KOPI-1',
            'quantity' => 1, 'price' => 10000, 'subtotal' => 10000, 'discount_amount' => 0,
        ]);

        return $tx;
    }

    private function seedTwoDays(User $user): void
    {
        $this->transaction($user, now()->toDateTimeString(), 'INV-HARIINI');
        $this->transaction($user, now()->subDays(5)->toDateTimeString(), 'INV-LAMA');
    }

    private function lockRoles(array $roles): void
    {
        Setting::set('report_date_limit_roles', json_encode($roles));
    }

    public function test_kasir_terkunci_ke_hari_ini_meski_memaksa_lewat_query_string(): void
    {
        $this->lockRoles(['kasir']);
        $kasir = $this->user('kasir');
        $this->seedTwoDays($kasir);

        $response = $this->actingAs($kasir)->get('/transactions?date_from=2000-01-01&date_to=2100-01-01');

        $response->assertOk();
        $response->assertSee('INV-HARIINI');
        $response->assertDontSee('INV-LAMA');
    }

    public function test_manager_terkunci_bila_rolenya_dicentang(): void
    {
        $this->lockRoles(['kasir', 'manager']);
        $manager = $this->user('manager');
        $this->seedTwoDays($manager);

        $response = $this->actingAs($manager)->get('/transactions?date_from=2000-01-01');

        $response->assertOk();
        $response->assertDontSee('INV-LAMA');
    }

    public function test_manager_bebas_bila_hanya_kasir_yang_dikunci(): void
    {
        $this->lockRoles(['kasir']);
        $manager = $this->user('manager');
        $this->seedTwoDays($manager);

        $response = $this->actingAs($manager)->get('/transactions?date_from=2000-01-01&date_to=2100-01-01');

        $response->assertOk();
        $response->assertSee('INV-LAMA');
    }

    public function test_admin_dikecualikan_meski_dicentang(): void
    {
        $this->lockRoles(['admin', 'manager', 'kasir']);
        $admin = $this->user('admin');
        $this->seedTwoDays($admin);

        $response = $this->actingAs($admin)->get('/transactions?date_from=2000-01-01&date_to=2100-01-01');

        $response->assertOk();
        $response->assertSee('INV-LAMA');
    }

    public function test_default_tidak_membatasi_siapa_pun(): void
    {
        // Tidak ada pengaturan tersimpan — perilaku bawaan harus tetap bebas.
        $kasir = $this->user('kasir');
        $this->seedTwoDays($kasir);

        $response = $this->actingAs($kasir)->get('/transactions?date_from=2000-01-01&date_to=2100-01-01');

        $response->assertOk();
        $response->assertSee('INV-LAMA');
    }

    public function test_pengaturan_lama_today_masih_mengunci_semua_non_admin(): void
    {
        // Instalasi lama: hanya report_date_limit yang tersimpan, belum per-role.
        Setting::set('report_date_limit', 'today');
        $kasir = $this->user('kasir');
        $this->seedTwoDays($kasir);

        $response = $this->actingAs($kasir)->get('/transactions?date_from=2000-01-01&date_to=2100-01-01');

        $response->assertOk();
        $response->assertDontSee('INV-LAMA');
    }
}
