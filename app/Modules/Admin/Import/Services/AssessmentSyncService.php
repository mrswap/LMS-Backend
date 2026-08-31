<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Assessment;
use App\Models\Module;
use App\Models\Topic;

class AssessmentSyncService {
    /*
    |--------------------------------------------------------------------------
    | Sync Module Assessment
    |--------------------------------------------------------------------------
    */

    public function syncModule(
        Module $module,
        int $createdBy
    ): Assessment {

        return Assessment::updateOrCreate(

            [
                'assessmentable_type' => Module::class,
                'assessmentable_id'   => $module->id,
            ],

            [
                'type'            => 'module',

                'title'           => $module->title,

                'description'     => $module->title,

                'file'            => null,

                'duration'        => 20,

                'passing_score'   => 10,

                'total_marks'     => 15,

                'status'          => true,

                'created_by'      => $createdBy,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Topic Assessment
    |--------------------------------------------------------------------------
    */

    public function syncTopic(
        Topic $topic,
        int $createdBy
    ): Assessment {

        return Assessment::updateOrCreate(

            [
                'assessmentable_type' => Topic::class,
                'assessmentable_id'   => $topic->id,
            ],

            [
                'type'            => 'topic',

                'title'           => $topic->title,

                'description'     => $topic->title,

                'file'            => null,

                'duration'        => 10,

                'passing_score'   => 3,

                'total_marks'     => 5,

                'status'          => true,

                'created_by'      => $createdBy,
            ]
        );
    }
}
