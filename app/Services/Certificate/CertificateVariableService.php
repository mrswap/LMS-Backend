<?php

namespace App\Services\Certificate;

class CertificateVariableService
{
    public static function get()
    {
        return [

            // USER
            '{{name}}' => 'User Name',
            '{{email}}' => 'User Email',
            '{{employee_id}}' => 'Employee ID',

            // CONTEXT
            '{{type}}' => 'Level / Topic',
            '{{title}}' => 'Level/Topic Title',

            // RESULT
            '{{score}}' => 'Score',
            '{{percentage}}' => 'Percentage',
            '{{status}}' => 'Pass/Fail',

            // QUESTIONS
            '{{total_questions}}' => 'Total Questions',
            '{{attempted}}' => 'Attempted',
            '{{correct}}' => 'Correct',
            '{{incorrect}}' => 'Incorrect',
            '{{skipped}}' => 'Skipped',

            // MARKS
            '{{total_marks}}' => 'Total Marks',
            '{{obtained_marks}}' => 'Obtained Marks',
            '{{passing_marks}}' => 'Passing Marks',

            // TIME
            '{{date}}' => 'Issued Date',
            '{{time_taken}}' => 'Time Taken',

            // CERTIFICATE
            '{{certificate_id}}' => 'Certificate ID',
        ];
    }
}
