<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class RolePermission extends Model
{
    protected $fillable = ['role', 'module', 'allowed'];
    protected $casts    = ['allowed' => 'boolean'];

    // All configurable modules (every navigable menu appears here)
    public static function modules(): array
    {
        return [
            // Menu
            'dashboard'      => ['label' => 'Dashboard',         'icon' => 'fa-th-large'],
            'pos'            => ['label' => 'Kasir (POS)',        'icon' => 'fa-cash-register'],
            'transactions'   => ['label' => 'Transaksi',         'icon' => 'fa-receipt'],
            // Manajemen
            'products'       => ['label' => 'Produk',            'icon' => 'fa-box'],
            'customers'      => ['label' => 'Customer',          'icon' => 'fa-users'],
            'stock'          => ['label' => 'Stok Barang',       'icon' => 'fa-boxes'],
            'stock_opname'   => ['label' => 'Stock Opname',      'icon' => 'fa-clipboard-list'],
            'slice'          => ['label' => 'Repack (Konversi)', 'icon' => 'fa-scissors'],
            'stock_transfer' => ['label' => 'Transfer Barang',   'icon' => 'fa-truck-loading'],
            // Delivery & Dapur
            'delivery'       => ['label' => 'Delivery Order',    'icon' => 'fa-truck'],
            'delivery_notes' => ['label' => 'Delivery Notes',    'icon' => 'fa-map-marker-alt'],
            'stock_request'  => ['label' => 'Permintaan FG',     'icon' => 'fa-clipboard-check'],
            'pulling_order'  => ['label' => 'Pulling Order',     'icon' => 'fa-list-check'],
            'rekap_order'    => ['label' => 'Rekap Order',       'icon' => 'fa-layer-group'],
            'kitchen'        => ['label' => 'Kitchen Monitor',   'icon' => 'fa-utensils'],
            // Integrasi
            'sync'           => ['label' => 'Sync HPY',          'icon' => 'fa-sync-alt'],
            'online_report'  => ['label' => 'Laporan Online',    'icon' => 'fa-cloud-download-alt'],
            'mop_report'     => ['label' => 'Laporan Pembayaran', 'icon' => 'fa-wallet'],
            'do_report'      => ['label' => 'Laporan DO',        'icon' => 'fa-file-invoice-dollar'],
            // Sistem (dulu admin-only — default OFF untuk non-admin, lihat sensitiveModules())
            'coupons'        => ['label' => 'Kupon',             'icon' => 'fa-ticket-alt'],
            'users'          => ['label' => 'Manajemen User',    'icon' => 'fa-users-cog'],
            'roles'          => ['label' => 'Manajemen Role',    'icon' => 'fa-user-shield'],
            'permissions'    => ['label' => 'Hak Akses',         'icon' => 'fa-shield-alt'],
            'warehouses'     => ['label' => 'Warehouse',         'icon' => 'fa-warehouse'],
            'settings'       => ['label' => 'Pengaturan Toko',   'icon' => 'fa-store'],
            'backup'         => ['label' => 'Restore Backup',    'icon' => 'fa-upload'],
            'update'         => ['label' => 'Update Sistem',     'icon' => 'fa-download'],
            'factory_reset'  => ['label' => 'Factory Reset',     'icon' => 'fa-trash-alt'],
        ];
    }

    // Sensitive admin modules: when no row exists yet they stay LOCKED for
    // non-admin roles (admin always passes via User::hasPermission()).
    public static function sensitiveModules(): array
    {
        return [
            'coupons', 'users', 'roles', 'permissions',
            'warehouses', 'settings', 'backup', 'update', 'factory_reset',
        ];
    }

    public static function forRole(string $role): array
    {
        return Cache::remember("role_permissions_{$role}", 300, function () use ($role) {
            return static::where('role', $role)->pluck('allowed', 'module')->toArray();
        });
    }

    public static function can(string $role, string $module): bool
    {
        $perms = static::forRole($role);
        // Fall back to sensible defaults if row missing (e.g. new module added)
        if (!array_key_exists($module, $perms)) {
            // Sensitive admin modules stay locked until explicitly granted.
            if (in_array($module, static::sensitiveModules(), true)) {
                return false;
            }
            return $role === 'manager';
        }
        return (bool) $perms[$module];
    }

    public static function clearCache(?string $role = null): void
    {
        if ($role) {
            Cache::forget("role_permissions_{$role}");
            return;
        }
        // Clear all distinct roles stored in DB
        foreach (static::distinct('role')->pluck('role') as $r) {
            Cache::forget("role_permissions_{$r}");
        }
    }
}
