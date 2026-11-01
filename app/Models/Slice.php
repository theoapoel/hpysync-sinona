<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slice_no', 'created_by', 'status',
        'erp_stock_entry', 'erp_sync_status', 'erp_sync_error',
        'submitted_at', 'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(SliceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function issues()
    {
        return $this->lines()->where('line_type', 'issue');
    }

    public function receipts()
    {
        return $this->lines()->where('line_type', 'receipt');
    }

    public static function generateSliceNo(): string
    {
        $prefix = 'SLC-'.date('Ymd').'-';
        $last = static::withTrashed()->where('slice_no', 'like', $prefix.'%')->latest('id')->value('slice_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'submitted' => 'Disubmit',
            'cancelled' => 'Dibatalkan',
            default => 'Draft',
        };
    }
}
