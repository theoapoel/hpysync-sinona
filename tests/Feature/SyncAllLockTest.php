<?php

namespace Tests\Feature;

use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Kunci lintas-request pada "Sync Semua Pending". Sengaja memakai cache store
 * 'database' — sama seperti produksi (config/cache.php default), supaya yang diuji
 * adalah lock yang benar-benar dipakai, bukan array store yang selalu mendukungnya.
 */
class SyncAllLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);

        Setting::set('erpnext_url', 'https://erp.example.test');
        Setting::set('erpnext_api_key', 'k');
        Setting::set('erpnext_api_secret', 's');
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('x'), 'role' => 'admin',
        ]);
        RolePermission::updateOrCreate(['role' => 'admin', 'module' => 'sync'], ['allowed' => true]);
        RolePermission::clearCache('admin');

        return $user;
    }

    public function test_database_cache_store_mendukung_atomic_lock(): void
    {
        $lock = Cache::lock('uji-lock', 10);

        $this->assertTrue($lock->get(), 'lock pertama harus berhasil');
        $this->assertFalse(Cache::lock('uji-lock', 10)->get(), 'lock kedua harus ditolak');

        $lock->release();

        $this->assertTrue(Cache::lock('uji-lock', 10)->get(), 'setelah dilepas harus bisa dikunci lagi');
    }

    public function test_sync_all_ditolak_saat_sync_lain_sedang_berjalan(): void
    {
        $lock = Cache::lock('erp-sync-all', 600);
        $this->assertTrue($lock->get());

        $response = $this->actingAs($this->admin())->postJson('/sync/all');

        $response->assertStatus(409);
        $response->assertJson(['locked' => true]);

        $lock->release();
    }

    public function test_sync_all_melepas_kunci_setelah_selesai(): void
    {
        $admin = $this->admin();

        // Tanpa transaksi pending, sync selesai tanpa memanggil ERP sama sekali.
        $this->actingAs($admin)->postJson('/sync/all')->assertOk();

        // Kalau kuncinya bocor, permintaan kedua akan kena 409.
        $this->actingAs($admin)->postJson('/sync/all')->assertOk();
    }
}
