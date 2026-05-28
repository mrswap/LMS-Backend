<?php

namespace App\Services;

use App\Models\Certification;
use App\Models\AssessmentAnswer;
use Illuminate\Support\Str;

class CertificationService
{
    public function generate(
        $user,
        $context,
        $attempt,
        $type = 'topic'
    ) {

        /*
        |------------------------------------------------------------------
        | ❌ DUPLICATE PREVENTION
        |------------------------------------------------------------------
        */

        $query = Certification::where(
            'user_id',
            $user->id
        )->where(
            'type',
            $type
        );

        /*
        |------------------------------------------------------------------
        | LEVEL CERTIFICATE
        |------------------------------------------------------------------
        */

        if ($type === 'level') {

            $query->where(
                'level_id',
                $context->id
            );
        }

        /*
        |------------------------------------------------------------------
        | MODULE CERTIFICATE
        |------------------------------------------------------------------
        */

        if ($type === 'module') {

            $query->where(
                'module_id',
                $context->id
            );
        }

        /*
        |------------------------------------------------------------------
        | CHAPTER CERTIFICATE
        |------------------------------------------------------------------
        */

        if ($type === 'chapter') {

            $query->where(
                'chapter_id',
                $context->id
            );
        }

        /*
        |------------------------------------------------------------------
        | TOPIC CERTIFICATE
        |------------------------------------------------------------------
        */

        if ($type === 'topic') {

            $query->where(
                'topic_id',
                $context->id
            );
        }

        /*
        |------------------------------------------------------------------
        | ALREADY EXISTS
        |------------------------------------------------------------------
        */

        if ($query->exists()) {

            return null;
        }

        /*
        |------------------------------------------------------------------
        | 📊 FETCH ANSWERS
        |------------------------------------------------------------------
        */

        $answers = AssessmentAnswer::where(
            'attempt_id',
            $attempt->id
        )->get();

        $totalQuestions = $attempt->attemptQuestions()
            ->count();

        $attempted = $answers
            ->whereNotNull('selected_option_id')
            ->count();

        $correct = $answers
            ->where('is_correct', true)
            ->count();

        $incorrect = $answers
            ->where('is_correct', false)
            ->whereNotNull('selected_option_id')
            ->count();

        $skipped = $answers
            ->whereNull('selected_option_id')
            ->count();

        /*
        |------------------------------------------------------------------
        | 📊 MARKS
        |------------------------------------------------------------------
        */

        $totalMarks = $answers->sum(
            'marks_snapshot'
        );

        $obtainedMarks = $answers->sum(
            'marks_obtained'
        );

        $passingMarks = $attempt->assessment->passing_score ?? null;

        /*
        |------------------------------------------------------------------
        | 🧠 CERTIFICATE ID
        |------------------------------------------------------------------
        */

        $certificateId = 'CERT-'
            . date('Y')
            . '-'
            . strtoupper(Str::random(6));

        /*
        |------------------------------------------------------------------
        | 🧾 META
        |------------------------------------------------------------------
        */

        $meta = [

            /*
            |------------------------------------------------------------------
            | 👤 USER
            |------------------------------------------------------------------
            */

            'user' => [

                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'employee_id' => $user->employee_id,
            ],

            /*
            |------------------------------------------------------------------
            | 📚 CONTEXT
            |------------------------------------------------------------------
            */

            'context' => [

                'type' => $type,

                'title' => $context->title,

                'program_id' => $context->program_id ?? null,

                'level_id' => $type === 'level'
                    ? $context->id
                    : ($context->level_id ?? null),

                'module_id' => $type === 'module'
                    ? $context->id
                    : ($context->module_id ?? null),

                'chapter_id' => $type === 'chapter'
                    ? $context->id
                    : ($context->chapter_id ?? null),

                'topic_id' => $type === 'topic'
                    ? $context->id
                    : ($context->topic_id ?? null),
            ],

            /*
            |------------------------------------------------------------------
            | 📊 RESULT
            |------------------------------------------------------------------
            */

            'result' => [

                'score' => $attempt->score,

                'percentage' => $attempt->percentage,

                'passing_score' => $passingMarks,

                'status' => $attempt->status,
            ],

            /*
            |------------------------------------------------------------------
            | 📊 QUESTIONS
            |------------------------------------------------------------------
            */

            'questions' => [

                'total' => $totalQuestions,

                'attempted' => $attempted,

                'correct' => $correct,

                'incorrect' => $incorrect,

                'skipped' => $skipped,
            ],

            /*
            |------------------------------------------------------------------
            | 💯 MARKS
            |------------------------------------------------------------------
            */

            'marks' => [

                'total_marks' => $totalMarks,

                'obtained_marks' => $obtainedMarks,

                'passing_marks' => $passingMarks,
            ],

            /*
            |------------------------------------------------------------------
            | ⏱ TIME
            |------------------------------------------------------------------
            */

            'time' => [

                'started_at' => $attempt->started_at,

                'submitted_at' => $attempt->submitted_at,

                'time_taken_seconds' => $attempt->time_taken,
            ],

            /*
            |------------------------------------------------------------------
            | 🔁 ATTEMPT
            |------------------------------------------------------------------
            */

            'attempt' => [

                'attempt_id' => $attempt->id,

                'submit_type' => $attempt->submit_type,
            ],
        ];

        /*
        |------------------------------------------------------------------
        | 💾 CREATE CERTIFICATE
        |------------------------------------------------------------------
        */

        $certificate = Certification::create([

            'user_id' => $user->id,

            'program_id' => $context->program_id ?? null,

            'level_id' => $type === 'level'
                ? $context->id
                : ($context->level_id ?? null),

            'module_id' => $type === 'module'
                ? $context->id
                : ($context->module_id ?? null),

            'chapter_id' => $type === 'chapter'
                ? $context->id
                : ($context->chapter_id ?? null),

            'topic_id' => $type === 'topic'
                ? $context->id
                : ($context->topic_id ?? null),

            'type' => $type,

            'assessment_attempt_id' => $attempt->id,

            'certificate_id' => $certificateId,

            'score' => $attempt->score,

            'percentage' => $attempt->percentage,

            'issued_at' => now(),

            'meta' => $meta
        ]);

        /*
        |------------------------------------------------------------------
        | 🔔 USER NOTIFICATION
        |------------------------------------------------------------------
        */

        app(NotificationService::class)->send(
            $user,
            'CERTIFICATE_GENERATED',
            [

                'title' => 'Certificate Generated',

                'message' => "Certificate generated for {$context->title}",

                'screen' => 'CertificateDetails',

                'model' => $certificate,

                'meta' => [

                    'certificate_id' => $certificate->id,

                    'certificate_code' => $certificate->certificate_id,

                    'type' => $type,
                ]
            ],
            ['db', 'push']
        );

        /*
        |------------------------------------------------------------------
        | 🛡 ADMIN MESSAGE
        |------------------------------------------------------------------
        */

        $adminMessage = "{$user->name} earned a certificate for {$context->title}";

        /*
        |------------------------------------------------------------------
        | 🛡 ADMIN NOTIFICATION
        |------------------------------------------------------------------
        */

        app(NotificationService::class)->sendToRole(
            'admin',
            'CERTIFICATE_GENERATED',
            [

                'title' => 'Certificate Generated',

                'message' => $adminMessage,

                'screen' => 'CertificateReview',

                'model' => $certificate,

                'meta' => [

                    'user_id' => $user->id,

                    'certificate_id' => $certificate->id,

                    'certificate_code' => $certificate->certificate_id,

                    'type' => $type,
                ]
            ],
            ['db', 'push']
        );

        /*
        |------------------------------------------------------------------
        | 👑 SUPERADMIN NOTIFICATION
        |------------------------------------------------------------------
        */

        app(NotificationService::class)->sendToRole(
            'superadmin',
            'CERTIFICATE_GENERATED',
            [

                'title' => 'Certificate Generated',

                'message' => $adminMessage,

                'screen' => 'CertificateReview',

                'model' => $certificate,

                'meta' => [

                    'user_id' => $user->id,

                    'certificate_id' => $certificate->id,

                    'certificate_code' => $certificate->certificate_id,

                    'type' => $type,
                ]
            ],
            ['db', 'push']
        );

        return $certificate;
    }
}
