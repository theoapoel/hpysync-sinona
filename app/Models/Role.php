<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'description', 'color', 'is_system'];
    protected $casts    = ['is_system' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'name');
    }

    /** All roles except admin — used for permission matrix and user form */
    public static function configurable()
    {
        return static::where('name', '!=', 'admin')->orderByDesc('is_system')->orderBy('label')->get();
    }

    /** All roles — for user form dropdown */
    public static function allOrdered()
    {
        return static::orderByDesc('is_system')->orderBy('label')->get();
    }
}
