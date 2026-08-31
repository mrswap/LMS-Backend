<?php

namespace App\Modules\Admin\Import\Services;

use App\Models\Assessment;
use App\Modules\Admin\Import\DTO\AssessmentImportDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssessmentImporterService {
    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(

        protected AssessmentMatchingService $matcher

    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Import Assessments
    |--------------------------------------------------------------------------
    */

    public function import(

        AssessmentImportDTO $dto,

        int $programId,

        int $levelId,

        int $moduleId,

        ?int $createdBy = null

    ): void {

        $startedAt = microtime(true);

        Log::info(
            '[Assessment Import] Started',
            [

                'program_id' => $programId,

                'level_id' => $levelId,

                'module_id' => $moduleId,

                'module_questions' =>

                $dto->totalModuleQuestions(),

                'topic_assessments' =>

                $dto->totalTopics(),

                'topic_questions' =>

                $dto->totalTopicQuestions(),

            ]
        );

        DB::transaction(

            function () use (

                $dto,

                $programId,

                $levelId,

                $moduleId,

                $createdBy

            ) {




                /*
                |--------------------------------------------------------------------------
                | Module Assessment
                |--------------------------------------------------------------------------
                */


                if (

                    $dto->hasModuleAssessment()

                ) {



                    Log::info(
                        '[Assessment Import] Importing Module Assessment'
                    );

                    $this->importModuleAssessment(

                        $dto->getModuleAssessment(),

                        $moduleId,

                        $createdBy

                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Topic Assessments
                |--------------------------------------------------------------------------
                */

                if (

                    $dto->hasTopicAssessments()

                ) {

                    Log::info(
                        '[Assessment Import] Importing Topic Assessments'
                    );

                    $this->importTopicAssessments(

                        $dto->getTopicAssessments(),

                        $programId,

                        $levelId,

                        $moduleId,

                        $createdBy

                    );
                }
            }

        );

        Log::info(

            '[Assessment Import] Completed',

            [

                'execution_time' => round(

                    microtime(true)

                        -

                        $startedAt,

                    3

                ),

                'memory_mb' => round(

                    memory_get_peak_usage(true)

                        / 1024

                        / 1024,

                    2

                ),

            ]

        );
    }
    /*
    |--------------------------------------------------------------------------
    | Import Module Assessment
    |--------------------------------------------------------------------------
    */

    protected function importModuleAssessment(

        array $moduleAssessment,

        int $moduleId,

        ?int $createdBy = null

    ): void {

        /*
        |--------------------------------------------------------------------------
        | Empty Assessment
        |--------------------------------------------------------------------------
        */

        if (

            empty($moduleAssessment)

        ) {

            Log::warning(

                '[Assessment Import] Module Assessment Empty'

            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Match Existing Assessment
        |--------------------------------------------------------------------------
        */

        $assessment = $this->matcher->matchModule(

            $moduleId

        );

        Log::info(

            '[Assessment Import] Module Assessment Matched',

            [

                'assessment_id' => $assessment->id,

                'assessment_title' => $assessment->title,

            ]

        );

        /*
        |--------------------------------------------------------------------------
        | Cleanup Existing Questions
        |--------------------------------------------------------------------------
        */

        $this->cleanupAssessment(

            $assessment

        );

        /*
        |--------------------------------------------------------------------------
        | Import Questions
        |--------------------------------------------------------------------------
        */

        $questions =

            $moduleAssessment['questions']

            ??

            [];

        Log::info(

            '[Assessment Import] Importing Module Questions',

            [

                'assessment_id' => $assessment->id,

                'questions' => count(

                    $questions

                ),

            ]

        );

        foreach (

            $questions

            as

            $index => $question

        ) {

            $this->createQuestion(

                assessment: $assessment,

                data: $question,

                order: $index + 1,

                createdBy: $createdBy

            );
        }

        /*
        |--------------------------------------------------------------------------
        | Recalculate Marks
        |--------------------------------------------------------------------------
        */

        $assessment->recalculateQuestionMarks();

        Log::info(

            '[Assessment Import] Module Assessment Imported',

            [

                'assessment_id' => $assessment->id,

                'questions' =>

                $assessment->questions()->count(),

            ]

        );
    }
    /*
    |--------------------------------------------------------------------------
    | Import Topic Assessments
    |--------------------------------------------------------------------------
    */

    protected function importTopicAssessments(

        array $topicAssessments,

        int $programId,

        int $levelId,

        int $moduleId,

        ?int $createdBy = null

    ): void {

        /*
        |--------------------------------------------------------------------------
        | Empty
        |--------------------------------------------------------------------------
        */

        if (empty($topicAssessments)) {

            Log::warning(

                '[Assessment Import] No Topic Assessments Found'

            );

            return;
        }

        Log::info(

            '[Assessment Import] Topic Assessment Import Started',

            [

                'total_topics' => count($topicAssessments),

            ]

        );

        /*
        |--------------------------------------------------------------------------
        | Loop Topics
        |--------------------------------------------------------------------------
        */

        foreach (

            $topicAssessments

            as

            $topicAssessment

        ) {

            $topicTitle = trim(

                $topicAssessment['topic_title']

                    ?? ''

            );

            Log::info(

                '[Assessment Import] Processing Topic',

                [

                    'topic_title' => $topicTitle,

                ]

            );

            /*
            |--------------------------------------------------------------------------
            | Match Assessment
            |--------------------------------------------------------------------------
            */

            $assessment = $this->matcher->matchTopic(

                $programId,

                $levelId,

                $moduleId,

                $topicTitle

            );

            Log::info(

                '[Assessment Import] Topic Assessment Matched',

                [

                    'assessment_id' => $assessment->id,

                    'assessment_title' => $assessment->title,

                ]

            );

            /*
            |--------------------------------------------------------------------------
            | Cleanup
            |--------------------------------------------------------------------------
            */

            $this->cleanupAssessment(

                $assessment

            );

            /*
            |--------------------------------------------------------------------------
            | Questions
            |--------------------------------------------------------------------------
            */

            $questions =

                $topicAssessment['questions']

                ??

                [];

            Log::info(

                '[Assessment Import] Importing Topic Questions',

                [

                    'assessment_id' => $assessment->id,

                    'question_count' => count(

                        $questions

                    ),

                ]

            );

            foreach (

                $questions

                as

                $index => $question

            ) {

                $this->createQuestion(

                    assessment: $assessment,

                    data: $question,

                    order: $index + 1,

                    createdBy: $createdBy

                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate Marks
            |--------------------------------------------------------------------------
            */

            $assessment->recalculateQuestionMarks();

            Log::info(

                '[Assessment Import] Topic Imported',

                [

                    'assessment_id' => $assessment->id,

                    'questions' =>

                    $assessment->questions()->count(),

                ]

            );
        }

        Log::info(

            '[Assessment Import] All Topic Assessments Imported',

            [

                'topics' => count(

                    $topicAssessments

                ),

            ]

        );
    }
    /*
    |--------------------------------------------------------------------------
    | Create Question
    |--------------------------------------------------------------------------
    */

    protected function createQuestion(

        Assessment $assessment,

        array $data,

        int $order,

        ?int $createdBy = null

    ): void {

        Log::info(

            '[Assessment Import] Creating Question',

            [

                'assessment_id' => $assessment->id,

                'order' => $order,

                'question_preview' => mb_substr(

                    $data['question'] ?? '',

                    0,

                    100

                ),

            ]

        );

        /*
        |--------------------------------------------------------------------------
        | Create Question
        |--------------------------------------------------------------------------
        */

        $question = $assessment

            ->questions()

            ->create([

                'question_text' => trim(

                    $data['question']

                        ?? ''

                ),

                'question_type' =>

                strtolower(

                    trim(

                        $data['question_type']

                            ?? 'mcq'

                    )

                ),

                'marks' => 0,

                'order' => $order,

                /*
                |--------------------------------------------------------------------------
                | Case Based
                |--------------------------------------------------------------------------
                */

                'is_case' =>

                (bool) (

                    $data['is_case']

                    ?? false

                ),

                'case_title' =>

                $data['case_title']

                    ?? null,

                'case_text' =>

                $data['case_text']

                    ?? null,

                'case_order' =>

                $data['case_order']

                    ?? null,

            ]);

        Log::info(

            '[Assessment Import] Question Created',

            [

                'question_id' => $question->id,

            ]

        );

        /*
        |--------------------------------------------------------------------------
        | Options
        |--------------------------------------------------------------------------
        */

        $this->createOptions(

            question: $question,

            options: $data['options'] ?? [],

            correctAnswer: $data['correct_answer'] ?? null

        );
    }
    /*
|--------------------------------------------------------------------------
| Create Question Options
|--------------------------------------------------------------------------
*/

    protected function createOptions(

        \App\Models\AssessmentQuestion $question,

        array $options,

        ?string $correctAnswer = null

    ): void {

        if (empty($options)) {

            Log::warning(

                '[Assessment Import] No Options Found',

                [

                    'question_id' => $question->id,

                ]

            );

            return;
        }

        /*
    |--------------------------------------------------------------------------
    | Correct Answer Index
    |--------------------------------------------------------------------------
    */

        $correctIndex = null;

        if (!empty($correctAnswer)) {

            $correctAnswer = strtoupper(trim($correctAnswer));

            $correctIndex = match (strtoupper(trim((string) $correctAnswer))) {

                'A' => 0,

                'B' => 1,

                'C' => 2,

                'D' => 3,

                default => null,
            };
        }

        Log::info(

            '[Assessment Import] Creating Options',

            [

                'question_id' => $question->id,

                'total_options' => count($options),

                'correct_answer' => $correctAnswer,

            ]

        );

        foreach (

            $options

            as

            $index => $option

        ) {

            /*
        |--------------------------------------------------------------------------
        | Normalize Text
        |--------------------------------------------------------------------------
        */

            $text =

                trim(

                    $option['text']

                        ??

                        $option['option_text']

                        ??

                        $option['option']

                        ??

                        ''

                );

            if ($text === '') {

                Log::warning(

                    '[Assessment Import] Empty Option Skipped',

                    [

                        'question_id' => $question->id,

                        'index' => $index,

                    ]

                );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Determine Correct Option
            |--------------------------------------------------------------------------
            |
            | AI always returns:
            |
            | correct_answer = A/B/C/D
            |
            | options[].is_correct is intentionally FALSE for every option.
            |
            | Therefore correct_answer always has priority.
            |
            */

            $isCorrect = false;

            if ($correctIndex !== null) {

                $isCorrect = ($index === $correctIndex);
            } elseif (

                array_key_exists('is_correct', $option)

            ) {

                $isCorrect = (bool) $option['is_correct'];
            }

            /*
        |--------------------------------------------------------------------------
        | Fallback From Correct Answer
        |--------------------------------------------------------------------------
        */

            if ($isCorrect === null) {

                $isCorrect =

                    ($correctIndex !== null)

                    &&

                    ($correctIndex === $index);
            }

            $question

                ->options()

                ->create([

                    'option_text' => $text,

                    'is_correct' => (bool) $isCorrect,

                ]);
        }

        /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

        if (

            ! $question

                ->options()

                ->where(

                    'is_correct',

                    true

                )

                ->exists()

        ) {

            Log::warning(

                '[Assessment Import] No Correct Option Found',

                [

                    'question_id' => $question->id,

                ]

            );
        }

        Log::info(

            '[Assessment Import] Options Imported',

            [

                'question_id' => $question->id,

                'options' =>

                $question->options()->count(),

            ]

        );
    }
    /*
    |--------------------------------------------------------------------------
    | Cleanup Assessment
    |--------------------------------------------------------------------------
    */

    protected function cleanupAssessment(
        Assessment $assessment
    ): void {

        $totalQuestions =

            $assessment

            ->questions()

            ->count();

        Log::info(

            '[Assessment Import] Cleaning Assessment',

            [

                'assessment_id' => $assessment->id,

                'existing_questions' => $totalQuestions,

            ]

        );

        if ($totalQuestions === 0) {

            Log::info(

                '[Assessment Import] Assessment Already Empty',

                [

                    'assessment_id' => $assessment->id,

                ]

            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Questions
        |--------------------------------------------------------------------------
        */

        $assessment

            ->questions()

            ->cursor()

            ->each(function ($question) {

                $question->delete();
            });

        Log::info(

            '[Assessment Import] Assessment Cleaned',

            [

                'assessment_id' => $assessment->id,

            ]

        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Assessment
    |--------------------------------------------------------------------------
    */

    protected function verifyAssessment(
        Assessment $assessment
    ): void {

        $questionCount =

            $assessment

            ->questions()

            ->count();

        $optionCount =

            $assessment

            ->questions()

            ->withCount('options')

            ->get()

            ->sum('options_count');

        Log::info(

            '[Assessment Import] Verification',

            [

                'assessment_id' => $assessment->id,

                'questions' => $questionCount,

                'options' => $optionCount,

            ]

        );

        if ($questionCount <= 0) {

            throw new \Exception(

                'Assessment contains no questions.'

            );
        }
    }
}
