<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Laporan transaksi dibatasi per kasir atau tidak.
     * 'all' (default) = semua transaksi toko, tanpa membedakan kasir/shift.
     */
    public static function reportScopedByUser(): bool
    {
        return static::get('report_scope', 'all') === 'user';
    }

    /**
     * Role yang laporannya terkunci ke tanggal hari ini.
     * Disimpan sebagai JSON array nama role; admin tidak pernah ikut terkunci.
     *
     * Bila key baru belum pernah disimpan, pengaturan lama (report_date_limit =
     * 'today' untuk semua role non-admin) dipakai sebagai fallback lewat '*'.
     */
    public static function reportDateLimitedRoles(): array
    {
        $raw = static::get('report_date_limit_roles', '');

        if ($raw === '' || $raw === null) {
            return static::get('report_date_limit', 'all') === 'today' ? ['*'] : [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_diff($decoded, ['admin'])) : [];
    }

    /**
     * Apakah pengguna yang sedang login terkunci ke tanggal hari ini.
     * Admin dikecualikan agar tetap bisa menelusuri data lama.
     */
    public static function reportDateLocked(): bool
    {
        $user = auth()->user();

        if (! $user || $user->isAdmin()) {
            return false;
        }

        $roles = static::reportDateLimitedRoles();

        return in_array('*', $roles, true) || in_array($user->role, $roles, true);
    }

    public static function set(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget("setting_{$key}");
    }
}
