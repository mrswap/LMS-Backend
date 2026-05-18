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
        /*
        |----------------------------------------------------------------------
        | Cascade Delete
        |----------------------------------------------------------------------
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
        |----------------------------------------------------------------------
        | Cascade Restore
        |----------------------------------------------------------------------
        */

        static::restoring(function ($model) {

            if (method_exists($model, 'cascadeRestore')) {
                $model->cascadeRestore();
            }
        });

        /*
        |----------------------------------------------------------------------
        | Publish Governance
        |----------------------------------------------------------------------
        */

        static::saving(function ($model) {

            // only models with publish system
            if (!$model->hasPublishStatus) {
                return;
            }

            // auth check
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            // load role safely
            $user->loadMissing('role');

            $isSystemUser = (bool) $user->role?->is_system;

            /*
            |------------------------------------------------------------------
            | Non System Users
            |------------------------------------------------------------------
            */

            if (!$isSystemUser) {

                // always inactive
                $model->status = false;

                // always draft
                $model->publish_status = $model::PUBLISH_DRAFT;
            }
        });
    }
}
