<?php

namespace App\Services;

use App\Models\Certification;
use App\Models\AssessmentAnswer;
use Illuminate\Support\Str;

class CertificationService
{
    public function generate($user, $context, $attempt, $type = 'level')
    {
        /*
        |--------------------------------------------------
        | ❌ DUPLICATE PREVENTION
        |--------------------------------------------------
        */
        $query = Certification::where('user_id', $user->id)
            ->where('type', $type);

        if ($type === 'level') {
            $query->where('level_id', $context->id);
        }

        if ($type === 'topic') {
            $query->where('topic_id', $context->id);
        }

        if ($query->exists()) {
            return null;
        }

        /*
        |--------------------------------------------------
        | 📊 FETCH ANSWERS
        |--------------------------------------------------
        */
        $answers = AssessmentAnswer::where('attempt_id', $attempt->id)->get();

        $totalQuestions = $answers->count();

        $attempted = $answers->whereNotNull('selected_option_id')->count();

        $correct = $answers->where('is_correct', true)->count();

        $incorrect = $answers->where('is_correct', false)
            ->whereNotNull('selected_option_id')
            ->count();

        $skipped = $answers->whereNull('selected_option_id')->count();

        /*
        |--------------------------------------------------
        | 📊 MARKS CALCULATION
        |--------------------------------------------------
        */
        $totalMarks = $answers->sum('marks_snapshot');

        $obtainedMarks = $answers->sum('marks_obtained');

        $passingMarks = $attempt->assessment->passing_score ?? null;

        /*
        |--------------------------------------------------
        | 🧠 CERTIFICATE ID
        |--------------------------------------------------
        */
        $certificateId = 'CERT-' . date('Y') . '-' . strtoupper(Str::random(6));

        /*
        |--------------------------------------------------
        | 🧾 META SNAPSHOT (FULL 🔥)
        |--------------------------------------------------
        */
        $meta = [

            // 👤 USER
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'employee_id' => $user->employee_id,
            ],

            // 📚 CONTEXT
            'context' => [
                'type' => $type,
                'title' => $context->title,
                'program_id' => $context->program_id ?? null,
                'level_id' => $type === 'level' ? $context->id : $context->level_id ?? null,
                'topic_id' => $type === 'topic' ? $context->id : null,
            ],

            // 📊 RESULT
            'result' => [
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
                'passing_score' => $passingMarks,
                'status' => $attempt->status,
            ],

            // 📊 QUESTIONS ANALYTICS
            'questions' => [
                'total' => $totalQuestions,
                'attempted' => $attempted,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'skipped' => $skipped,
            ],

            // 💯 MARKS
            'marks' => [
                'total_marks' => $totalMarks,
                'obtained_marks' => $obtainedMarks,
                'passing_marks' => $passingMarks,
            ],

            // ⏱ TIME DATA
            'time' => [
                'started_at' => $attempt->started_at,
                'submitted_at' => $attempt->submitted_at,
                'time_taken_seconds' => $attempt->time_taken,
            ],

            // 🔁 ATTEMPT INFO
            'attempt' => [
                'attempt_id' => $attempt->id,
                'submit_type' => $attempt->submit_type,
            ],
        ];

        /*
        |--------------------------------------------------
        | 💾 SAVE CERTIFICATE
        |--------------------------------------------------
        */
        return Certification::create([
            'user_id' => $user->id,
            'program_id' => $context->program_id ?? null,
            'level_id' => $type === 'level' ? $context->id : $context->level_id ?? null,
            'topic_id' => $type === 'topic' ? $context->id : null,
            'type' => $type,
            'assessment_attempt_id' => $attempt->id,
            'certificate_id' => $certificateId,
            'score' => $attempt->score,
            'percentage' => $attempt->percentage,
            'issued_at' => now(),
            'meta' => $meta
        ]);
    }
}
