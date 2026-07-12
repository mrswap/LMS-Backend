<?php

namespace App\Modules\Admin\Import\Services;

use App\Modules\Admin\Import\DTO\AssessmentImportDTO;
use App\Modules\Admin\Import\Exceptions\AssessmentExtractionException;

class AssessmentValidationService
{
    /*
    |--------------------------------------------------------------------------
    | Validate AI Response
    |--------------------------------------------------------------------------
    */

    public function validate(
        AssessmentImportDTO $dto
    ): AssessmentImportDTO {

        /*
        |--------------------------------------------------------------------------
        | Must contain at least one assessment
        |--------------------------------------------------------------------------
        */

        if (
            ! $dto->hasModuleAssessment()
            &&
            ! $dto->hasTopicAssessments()
        ) {

            throw AssessmentExtractionException::invalidAssessment();
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Module Assessment
        |--------------------------------------------------------------------------
        */

        if (
            $dto->hasModuleAssessment()
        ) {

            $this->validateQuestions(

                $dto->getModuleAssessment()['questions']
                ?? [],

                'Module Assessment'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Topic Assessments
        |--------------------------------------------------------------------------
        */

        foreach (

            $dto->getTopicAssessments()

            as

            $topic

        ) {

            if (

                empty($topic['topic_title'])

            ) {

                throw AssessmentExtractionException::missingStructure(
                    'topic_title'
                );
            }

            if (

                empty($topic['questions'])

            ) {

                throw AssessmentExtractionException::missingStructure(
                    'questions'
                );
            }

            $this->validateQuestions(

                $topic['questions'],

                $topic['topic_title']
            );
        }

        return $dto;
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Question Collection
    |--------------------------------------------------------------------------
    */

    protected function validateQuestions(

        array $questions,

        string $assessmentName

    ): void {

        foreach (

            $questions

            as

            $index => $question

        ) {

            $this->validateQuestion(

                $question,

                $assessmentName,

                $index + 1
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Single Question
    |--------------------------------------------------------------------------
    */

    protected function validateQuestion(

        array $question,

        string $assessment,

        int $number

    ): void {

        if (

            empty($question['question'])

        ) {

            throw new AssessmentExtractionException(

                "{$assessment} Question {$number} is missing question text."
            );
        }

        if (

            empty($question['options'])

        ) {

            throw new AssessmentExtractionException(

                "{$assessment} Question {$number} has no options."
            );
        }

        if (

            count($question['options']) < 2

        ) {

            throw new AssessmentExtractionException(

                "{$assessment} Question {$number} must contain at least two options."
            );
        }

        foreach (

            $question['options']

            as

            $option

        ) {

            if (

                ! array_key_exists(
                    'text',
                    $option
                )

            ) {

                throw new AssessmentExtractionException(

                    "{$assessment} Question {$number} contains invalid option."
                );
            }

            if (

                ! array_key_exists(
                    'is_correct',
                    $option
                )

            ) {

                throw new AssessmentExtractionException(

                    "{$assessment} Question {$number} option missing is_correct."
                );
            }
        }
    }
}