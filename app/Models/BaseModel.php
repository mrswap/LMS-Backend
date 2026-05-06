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
    | STATUS MUTATOR
    |--------------------------------------------------------------------------
    */

    public function setStatusAttribute($value)
    {
        $status = (bool) $value;

        $this->attributes['status'] = $status;

        /*
        |--------------------------------------------------------------------------
        | AUTO SYNC publish_status
        |--------------------------------------------------------------------------
        */

        $this->attributes['publish_status'] =

            $status

            ? 'published'

            : 'unpublished';
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLISH STATUS MUTATOR
    |--------------------------------------------------------------------------
    */

    public function setPublishStatusAttribute($value)
    {
        $this->attributes['publish_status'] = $value;

        /*
        |--------------------------------------------------------------------------
        | AUTO SYNC status
        |--------------------------------------------------------------------------
        */

        $this->attributes['status'] =

            $value === 'published';
    }

    /*
    |--------------------------------------------------------------------------
    | BOOTED
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        /*
        |--------------------------------------------------------------------------
        | PREVENT ACCIDENTAL HARD DELETE
        |--------------------------------------------------------------------------
        */

        static::deleting(function ($model) {

            if ($model->isForceDeleting()) {
                return;
            }

            if (method_exists($model, 'cascadeSoftDelete')) {
                $model->cascadeSoftDelete();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | RESTORE
        |--------------------------------------------------------------------------
        */

        static::restoring(function ($model) {

            if (method_exists($model, 'cascadeRestore')) {
                $model->cascadeRestore();
            }
        });
    }
}
