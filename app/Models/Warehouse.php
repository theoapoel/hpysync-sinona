<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Warehouse extends Model
{
    protected $fillable = [
        'name', 'warehouse_name', 'company', 'warehouse_type',
        'parent_warehouse', 'is_group', 'is_active', 'is_default',
        'is_transit', 'erp_last_pulled',
    ];

    protected $casts = [
        'is_group'        => 'boolean',
        'is_active'       => 'boolean',
        'is_default'      => 'boolean',
        'is_transit'      => 'boolean',
        'erp_last_pulled' => 'datetime',
    ];

    /** Scope: only leaf (non-group) active warehouses */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_group', false);
    }

    /** Get the configured default warehouse for POS transactions */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /** Get the configured in-transit warehouse for stock transfers */
    public static function getTransit(): ?self
    {
        return static::where('is_transit', true)->first();
    }

    /** All active warehouses for dropdowns */
    public static function activeList()
    {
        return static::active()->orderBy('name')->get();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->warehouse_name ?: $this->name;
    }
}
