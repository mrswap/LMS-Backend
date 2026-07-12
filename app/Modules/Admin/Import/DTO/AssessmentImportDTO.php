<?php

namespace App\Modules\Admin\Import\DTO;

class AssessmentImportDTO {
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    protected array $moduleAssessment;

    protected array $topicAssessments;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct(
        array $moduleAssessment = [],
        array $topicAssessments = []
    ) {
        $this->moduleAssessment = $moduleAssessment;

        $this->topicAssessments = $topicAssessments;
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    public static function fromArray(
        array $data
    ): self {

        return new self(

            $data['module_assessment'] ?? [],

            $data['topic_assessments'] ?? []
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Module Assessment
    |--------------------------------------------------------------------------
    */

    public function getModuleAssessment(): array {
        return $this->moduleAssessment;
    }

    public function hasModuleAssessment(): bool {
        return !empty($this->moduleAssessment['questions']);
    }

    /*
    |--------------------------------------------------------------------------
    | Topic Assessments
    |--------------------------------------------------------------------------
    */

    public function getTopicAssessments(): array {
        return $this->topicAssessments;
    }

    public function hasTopicAssessments(): bool {
        return !empty($this->topicAssessments);
    }

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    public function totalTopics(): int {
        return count(
            $this->topicAssessments
        );
    }

    public function totalModuleQuestions(): int {
        return count(
            $this->moduleAssessment['questions'] ?? []
        );
    }

    public function totalTopicQuestions(): int {
        $count = 0;

        foreach (

            $this->topicAssessments

            as

            $assessment

        ) {

            $count += count(
                $assessment['questions'] ?? []
            );
        }

        return $count;
    }

    public function totalQuestions(): int {
        return

            $this->totalModuleQuestions()

            +

            $this->totalTopicQuestions();
    }

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */

    public function toArray(): array {
        return [

            'module_assessment' =>

            $this->moduleAssessment,

            'topic_assessments' =>

            $this->topicAssessments,
        ];
    }
}
