<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    protected $fillable = [
        'user_id', 'pos_profile', 'status',
        'opened_at', 'closed_at',
        'opening_cash', 'expected_cash', 'counted_cash', 'cash_difference',
        'total_sales', 'invoice_count', 'payment_breakdown',
        'erp_opening_entry', 'erp_closing_entry', 'erp_sync_status', 'erp_sync_error',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'payment_breakdown' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fitur Buka/Tutup Kasir aktif atau tidak.
     * Dinonaktifkan secara default; hidupkan dengan Setting 'pos_shift_enabled' = '1'.
     */
    public static function featureEnabled(): bool
    {
        return (string) Setting::get('pos_shift_enabled', '0') === '1';
    }

    /** Shift terbuka milik seorang kasir (bila ada). */
    public static function openFor(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('status', 'open')->latest('id')->first();
    }
}
