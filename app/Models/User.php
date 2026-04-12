<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Mass Assignable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'designation_id',
        'department',
        'region',
        'city',
        'mobile',
        'employee_id',
        'profile_image',
        'is_active',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden Fields
    |--------------------------------------------------------------------------
    */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE CONSTANTS (only for reference, not storage)
    |--------------------------------------------------------------------------
    */
    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_STAFF = 'staff';
    const ROLE_SALES = 'sales';

    /*
    |--------------------------------------------------------------------------
    | Boot Logic
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::deleting(function ($user) {

            // 🔥 load role relation
            $user->load('role');

            if ($user->role?->name === self::ROLE_SUPERADMIN) {

                $count = self::whereHas('role', function ($q) {
                    $q->where('name', self::ROLE_SUPERADMIN);
                })->count();

                if ($count <= 1) {
                    throw new \Exception('At least one superadmin must exist.');
                }

                throw new \Exception('Superadmin cannot be deleted.');
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === self::ROLE_SUPERADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role?->name === self::ROLE_STAFF;
    }

    public function isSales(): bool
    {
        return $this->role?->name === self::ROLE_SALES;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getThumbnailAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    public function getProfileImageAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }
}
