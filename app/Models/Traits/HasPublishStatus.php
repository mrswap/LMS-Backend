<?php

namespace App\Models\Traits;

trait HasPublishStatus
{
    /*
    |--------------------------------------------------------------------------
    | STATUS MUTATOR
    |--------------------------------------------------------------------------
    */

    public function setStatusAttribute($value)
    {
        $status = (bool) $value;

        $this->attributes['status'] = $status;

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

        $this->attributes['status'] =
            $value === 'published';
    }
}
