<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_STAFF = 'staff';
    const ROLE_SALES = 'sales';

    /*
    |--------------------------------------------------------------------------
    | Boot Logic (Soft Delete Safe)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        parent::booted();

        static::deleting(function ($user) {

            // 🔥 Always load role (even if soft deleted)
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

            // ❗ DO NOT cascade delete anything
            // user data = audit data
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(Role::class)->withTrashed();
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    // Self reference (optional hierarchy)
    public function parentUser()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
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

    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getProfileImageAttribute($value)
    {
        return $value ? url('public/' . ltrim($value, '/')) : null;
    }

    public function progress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function contentProgress()
    {
        return $this->hasMany(UserContentProgress::class);
    }

    public function assessmentAttempts()
    {
        return $this->hasMany(AssessmentAttempt::class);
    }

    public function certifications()
    {
        return $this->hasMany(Certification::class);
    }
}
