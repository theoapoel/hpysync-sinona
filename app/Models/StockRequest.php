<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class StockRequest extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_no', 'requested_by', 'delivery_order_id', 'status',
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

    /** Delivery Order asal — hanya terisi untuk permintaan yang dibuat otomatis dari DO. */
    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class)->withTrashed();
    }

    /**
     * Buat Permintaan FG otomatis untuk satu Delivery Order yang baru dikonfirmasi.
     *
     * DO tidak lagi memicu produksi langsung ke ERP HPY. Ia hanya menitipkan
     * permintaan di sini, lalu produksinya mengikuti alur Permintaan FG biasa:
     * Material Request dibuat Draft, dan baru disahkan saat dapur menekan
     * "Selesai". Dengan begitu pintu produksi hanya satu dan stok tidak bisa
     * terbentuk dua kali tanpa ada orang yang menekan tombol.
     *
     * Idempoten: satu DO hanya punya satu permintaan aktif (yang dibatalkan
     * tidak dihitung).
     *
     * @param  array|null  $producibleCodes  Kode item yang punya BOM aktif di ERP.
     *                                       null = tidak diketahui, semua item DO ikut.
     * @return self|null  null bila DO tidak punya barang yang perlu diproduksi.
     */
    public static function createFromDeliveryOrder(DeliveryOrder $order, ?array $producibleCodes = null, ?int $userId = null): ?self
    {
        $existing = static::where('delivery_order_id', $order->id)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        $order->loadMissing('items.product', 'customer');

        $rows = $order->items
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'item_name' => $item->product_name,
                'item_code' => $item->product?->erp_item_code ?? $item->product_sku ?? null,
                'qty' => (float) $item->qty,
                'uom' => $item->product?->unit ?: 'Nos',
            ])
            ->filter(fn ($row) => $row['qty'] > 0);

        if ($producibleCodes !== null) {
            $rows = $rows->filter(fn ($row) => $row['item_code'] && in_array($row['item_code'], $producibleCodes, true));
        }

        if ($rows->isEmpty()) {
            return null;
        }

        // Waktu butuh = jadwal produksi DO (mengikuti jam kirim paling awal),
        // kalau tidak ada pakai tanggal kirim.
        $schedule = $order->kitchen_scheduled_at;
        $neededDate = $schedule
            ? $schedule->format('Y-m-d')
            : ($order->delivery_date ? $order->delivery_date->format('Y-m-d') : now()->format('Y-m-d'));

        return DB::transaction(function () use ($order, $rows, $schedule, $neededDate, $userId) {
            $request = static::create([
                'request_no' => static::generateRequestNo(),
                'requested_by' => $userId ?: (auth()->id() ?: $order->created_by),
                'delivery_order_id' => $order->id,
                'status' => 'submitted',
                'kitchen_status' => 'requested',
                'needed_date' => $neededDate,
                'needed_time' => $schedule ? $schedule->format('H:i') : null,
                'notes' => trim('Auto dari Delivery Order '.$order->order_no
                    .($order->customer?->name ? ' - '.$order->customer->name : '')),
            ]);

            foreach ($rows as $row) {
                StockRequestItem::create(['stock_request_id' => $request->id] + $row);
            }

            return $request;
        });
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
