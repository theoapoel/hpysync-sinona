<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_no', 'requested_by', 'status',
        'kitchen_status', 'kitchen_started_at', 'kitchen_done_at', 'kitchen_scheduled_at',
        'erp_material_request', 'erp_sync_status', 'erp_sync_error',
        'needed_date', 'needed_time', 'notes',
    ];

    protected $casts = [
        'needed_date'           => 'date',
        'kitchen_started_at'    => 'datetime',
        'kitchen_done_at'       => 'datetime',
        'kitchen_scheduled_at'  => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items()
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public static function generateRequestNo(): string
    {
        $prefix = 'FG-' . date('Ymd') . '-';
        $last   = static::withTrashed()->where('request_no', 'like', $prefix . '%')->latest('id')->value('request_no');
        $seq    = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'submitted'  => 'Diajukan',
            'cancelled'  => 'Dibatalkan',
            default      => 'Draft',
        };
    }

    public function getKitchenStatusLabelAttribute(): string
    {
        return match($this->kitchen_status) {
            'requested'  => 'Diminta',
            'preparing'  => 'Dipersiapkan',
            'done'       => 'Selesai',
            default      => '—',
        };
    }
}
