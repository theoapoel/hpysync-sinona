<?php
// app/Models/User.php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\RolePermission;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'role', 'pin', 'is_active'];
    protected $hidden = ['password', 'remember_token', 'pin'];
    protected $casts = ['is_active' => 'boolean'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isAdmin(): bool   { return $this->role === 'admin'; }
    public function isManager(): bool { return in_array($this->role, ['admin', 'manager']); }

    public function hasPermission(string $module): bool
    {
        if ($this->role === 'admin') return true;
        return RolePermission::can($this->role, $module);
    }
}
