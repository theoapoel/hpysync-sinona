<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model {
    protected $fillable = [
        'transaction_id','product_id','product_name','product_sku',
        'price','cost_price','quantity','discount_amount','tax_rate','tax_amount','subtotal'
    ];
    protected $casts = ['price'=>'decimal:2','cost_price'=>'decimal:2','subtotal'=>'decimal:2'];
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
