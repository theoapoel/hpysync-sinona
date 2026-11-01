<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model {
    protected $fillable = ['name','slug','color','icon','is_active','erp_item_group'];
    protected $casts = ['is_active'=>'boolean'];
    public function products(): HasMany { return $this->hasMany(Product::class); }

    /**
     * Active categories, optionally restricted to the Item Groups configured
     * for a screen (setting stores a JSON array of category IDs; empty = all).
     */
    public static function filteredByGroups(string $settingKey)
    {
        $q   = static::where('is_active', true);
        $ids = json_decode(Setting::get($settingKey, '[]'), true);
        if (is_array($ids) && count($ids)) {
            $q->whereIn('id', $ids);
        }
        return $q;
    }
}
