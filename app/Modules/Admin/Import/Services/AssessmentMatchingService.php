<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Assessment;
use App\Models\Module;
use App\Models\Topic;
use Exception;

class AssessmentMatchingService {
    /*
    |--------------------------------------------------------------------------
    | Match Module Assessment
    |--------------------------------------------------------------------------
    */

    public function matchModule(
        int $moduleId
    ): Assessment {

        $module = Module::find(
            $moduleId
        );

        if (! $module) {

            throw new Exception(
                "Module not found. ID : {$moduleId}"
            );
        }

        $assessment = Assessment::query()

            ->where(

                'assessmentable_type',

                Module::class

            )

            ->where(

                'assessmentable_id',

                $module->id

            )

            ->first();

        if (! $assessment) {

            throw new Exception(

                "Assessment not found for Module : {$module->title}"

            );
        }

        logger()->info(

            '[Assessment Matching] Module Matched',

            [

                'module_id' => $module->id,

                'module_title' => $module->title,

                'assessment_id' => $assessment->id,

            ]

        );

        return $assessment;
    }

    /*
    |--------------------------------------------------------------------------
    | Match Topic Assessment
    |--------------------------------------------------------------------------
    */

    public function matchTopic(

        int $programId,

        int $levelId,

        int $moduleId,

        string $topicTitle

    ): Assessment {

        $topic = $this->findTopic(

            $programId,

            $levelId,

            $moduleId,

            $topicTitle

        );

        $assessment = $this->findAssessment(

            Topic::class,

            $topic->id

        );

        logger()->info(

            '[Assessment Matching] Topic Matched',

            [

                'topic_id' => $topic->id,

                'topic_title' => $topic->title,

                'assessment_id' => $assessment->id,

            ]

        );

        return $assessment;
    }

    /*
    |--------------------------------------------------------------------------
    | Find Topic
    |--------------------------------------------------------------------------
    */

    protected function findTopic(

        int $programId,

        int $levelId,

        int $moduleId,

        string $title

    ): Topic {

        $topic = Topic::query()

            ->where(

                'program_id',

                $programId

            )

            ->where(

                'level_id',

                $levelId

            )

            ->where(

                'module_id',

                $moduleId

            )

            ->where(

                'title',

                trim($title)

            )

            ->first();

        if (! $topic) {

            throw new Exception(

                "Topic not found : {$title}"

            );
        }

        return $topic;
    }

    /*
    |--------------------------------------------------------------------------
    | Find Assessment
    |--------------------------------------------------------------------------
    */

    protected function findAssessment(

        string $type,

        int $id

    ): Assessment {

        $assessment = Assessment::query()

            ->where(

                'assessmentable_type',

                $type

            )

            ->where(

                'assessmentable_id',

                $id

            )

            ->first();

        if (! $assessment) {

            throw new Exception(

                "Assessment not found."

            );
        }

        return $assessment;
    }
}
