<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $fillable = ['name', 'label', 'is_active'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
