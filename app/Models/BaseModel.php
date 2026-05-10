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
    | ENABLE publish_status
    |--------------------------------------------------------------------------
    */

    protected $hasPublishStatus = false;


    /*
    |--------------------------------------------------------------------------
    | BOOTED
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::deleting(function ($model) {

            if ($model->isForceDeleting()) {
                return;
            }

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
