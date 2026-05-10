<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssessmentAttempt;
use App\Modules\Trainee\Assessment\Services\AssessmentService;
use App\Modules\Trainee\Progress\Services\ProgressionService;
use App\Models\Topic;
use App\Models\Level;
use Illuminate\Support\Facades\DB;

class AutoSubmitExpiredAssessments extends Command
{
    protected $signature = 'assessment:auto-submit';

    protected $description = 'Auto submit expired assessment attempts';

    public function handle()
    {
        $service = app(AssessmentService::class);

        AssessmentAttempt::with([
            'assessment.questions',
            'answers'
        ])
            ->where('status', 'in_progress')
            ->chunkById(100, function ($attempts) use ($service) {

                foreach ($attempts as $attempt) {

                    DB::transaction(function () use ($attempt, $service) {

                        $assessment = $attempt->assessment;

                        // no timer
                        if (!$assessment || !$assessment->duration) {
                            return;
                        }

                        $expireAt = $attempt->started_at
                            ->copy()
                            ->addMinutes($assessment->duration);

                        // still active
                        if (now()->lt($expireAt)) {
                            return;
                        }

                        // already processed safety
                        if ($attempt->status !== 'in_progress') {
                            return;
                        }

                        /*
                    |--------------------------------------------------------------------------
                    | Evaluate
                    |--------------------------------------------------------------------------
                    */

                        $result = $service->evaluateAttempt($attempt);

                        $percentage = (float) $result['percentage'];

                        $passingPercentage =
                            (float) $assessment->passing_score;

                        $isPassed =
                            $percentage >= $passingPercentage;

                        /*
                    |--------------------------------------------------------------------------
                    | Update attempt
                    |--------------------------------------------------------------------------
                    */

                        $attempt->update([
                            'score' => $result['marks'],
                            'percentage' => $result['percentage'],
                            'submitted_at' => $expireAt,
                            'status' => $isPassed ? 'passed' : 'failed',
                            'submit_type' => 'timeout',
                            'time_taken' => $attempt->started_at
                                ->diffInSeconds($expireAt),
                        ]);

                        /*
                    |--------------------------------------------------------------------------
                    | Progression
                    |--------------------------------------------------------------------------
                    */

                        if ($isPassed) {

                            $progressionService =
                                app(ProgressionService::class);

                            if ($assessment->type === 'topic') {

                                $topic = Topic::find(
                                    $assessment->assessmentable_id
                                );

                                if ($topic) {

                                    $progressionService
                                        ->handleTopicCompletion(
                                            $attempt->user_id,
                                            $topic
                                        );
                                }
                            }

                            if ($assessment->type === 'level') {

                                $level = Level::find(
                                    $assessment->assessmentable_id
                                );

                                if ($level) {

                                    $progressionService
                                        ->handleLevelExamPass(
                                            $attempt->user_id,
                                            $level
                                        );
                                }
                            }
                        }

                        $this->info(
                            "Auto submitted attempt ID: {$attempt->id}"
                        );
                    });
                }
            });

        return Command::SUCCESS;
    }
}
