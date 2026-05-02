<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseModel extends Model
{
    use SoftDeletes;

    protected $dates = ['deleted_at'];

    /*
    |--------------------------------------------------------------------------
    | Prevent accidental hard delete
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::deleting(function ($model) {

            // Allow force delete explicitly only
            if ($model->isForceDeleting()) {
                return;
            }

            // Hook for cascade (child classes override)
            if (method_exists($model, 'cascadeSoftDelete')) {
                $model->cascadeSoftDelete();
            }
        });

        static::restoring(function ($model) {

            if (method_exists($model, 'cascadeRestore')) {
                $model->cascadeRestore();
            }
        });
    }
}
