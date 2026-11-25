<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    use LogsActivity;

    protected $fillable = ['name', 'email', 'phone', 'address', 'city', 'password', 'is_admin', 'is_active', 'role', 'referred_by'];

    /** Admin permissions; each one opens a part of the admin panel. */
    public const PERMISSIONS = [
        'orders' => 'Orders, customers, blocked numbers, abandoned carts',
        'catalog' => 'Products, categories, brands, PC Builder parts',
        'marketing' => 'Coupons, flash sales, banners, offers, pages',
        'engagement' => 'Reviews, questions, messages',
        'reports' => 'Sales reports and exports',
        'settings' => 'Store settings, payments, couriers, system status',
        'staff' => 'Staff accounts and the activity log',
    ];

    public const ROLES = [
        'owner' => ['label' => 'Owner', 'permissions' => ['*']],
        'manager' => ['label' => 'Manager', 'permissions' => ['orders', 'catalog', 'marketing', 'engagement', 'reports']],
        'orders' => ['label' => 'Order manager', 'permissions' => ['orders', 'engagement']],
        'catalog' => ['label' => 'Catalog editor', 'permissions' => ['catalog', 'marketing']],
        'accountant' => ['label' => 'Accountant', 'permissions' => ['reports']],
    ];

    public function permissions(): array
    {
        if (! $this->is_admin) {
            return [];
        }
        $perms = self::ROLES[$this->role ?? 'owner']['permissions'] ?? [];

        return $perms === ['*'] ? array_keys(self::PERMISSIONS) : $perms;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function roleLabel(): ?string
    {
        return $this->is_admin ? (self::ROLES[$this->role ?? 'owner']['label'] ?? ucfirst((string) $this->role)) : null;
    }

    protected $hidden = ['password', 'remember_token'];

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class)->latest('id');
    }

    /** Created the first time it's needed. */
    public function referralCode(): string
    {
        if (! $this->referral_code) {
            do {
                $code = strtoupper(Str::random(7));
            } while (static::where('referral_code', $code)->exists() || preg_match('/[O0I1L]/', $code));
            $this->forceFill(['referral_code' => $code])->saveQuietly();
        }

        return $this->referral_code;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wishlist(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function savedPcs(): HasMany
    {
        return $this->hasMany(SavedPc::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
