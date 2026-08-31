<?php

namespace App\Modules\Trainee\Reports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Reports\CertificationReportService;
use App\Models\Certification;
use App\Models\CertificateSetting;
use App\Services\Certificate\CertificateRenderService;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use App\Models\Topic;
use App\Models\UserProgress;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\UserContentProgress;


class CertificationReportController extends Controller {
    public function index(Request $request) {
        $userId = auth()->id();

        $data = (new CertificationReportService())
            ->getReport($request, $userId);

        return response()->json([
            'status' => true,
            'message' => 'Your certifications fetched successfully',
            'data' => $data
        ]);
    }

    /*
    |--------------------------------------------------
    | 🎓 GET CERTIFICATE BY ATTEMPT
    |--------------------------------------------------
    */
    public function show($attemptId) {
        $userId = auth()->id();

        $certificate = Certification::where('assessment_attempt_id', $attemptId)
            ->where('user_id', $userId)
            ->first();

        if (!$certificate) {
            return response()->json([
                'status' => false,
                'message' => 'Certificate not found'
            ], 404);
        }

        /*
        |--------------------------------------------------
        | ⚙️ SETTINGS
        |--------------------------------------------------
        */
        $setting = CertificateSetting::first();

        /*
        |--------------------------------------------------
        | 🔥 RENDER CONTENT
        |--------------------------------------------------
        */
        $renderedContent = CertificateRenderService::render(
            $setting->content,
            $certificate->meta,
            $certificate
        );
        $context = $certificate->meta['context'] ?? [];

        $contextDetails = [
            'program' => null,
            'level' => null,
            'module' => null,
            'chapter' => null,
            'topic' => null,
        ];

        if (!empty($context['program_id'])) {

            $program = Program::find($context['program_id']);

            if ($program) {
                $contextDetails['program'] = [
                    'id' => $program->id,
                    'title' => $program->title,
                    'thumbnail' => $program->thumbnail,
                ];
            }
        }

        if (!empty($context['level_id'])) {

            $level = Level::find($context['level_id']);

            if ($level) {
                $contextDetails['level'] = [
                    'id' => $level->id,
                    'title' => $level->title,
                    'thumbnail' => $level->thumbnail,
                ];
            }
        }

        if (!empty($context['module_id'])) {

            $module = Module::find($context['module_id']);

            if ($module) {
                $contextDetails['module'] = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'thumbnail' => $module->thumbnail,
                ];
            }
        }

        if (!empty($context['chapter_id'])) {

            $chapter = Chapter::find($context['chapter_id']);

            if ($chapter) {
                $contextDetails['chapter'] = [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'thumbnail' => $chapter->thumbnail,
                ];
            }
        }

        if (!empty($context['topic_id'])) {

            $topic = Topic::find($context['topic_id']);

            if ($topic) {
                $contextDetails['topic'] = [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'thumbnail' => $topic->thumbnail,
                ];
            }
        }
        /*
        |--------------------------------------------------
        | 🔗 SHARE LINKS
        |--------------------------------------------------
        */
        $shareText = urlencode("I have successfully completed {$certificate->meta['context']['title']} 🎓");

        $shareUrl = url("/certificate/{$certificate->certificate_id}");

        $shareLinks = [
            'whatsapp' => "https://wa.me/?text={$shareText}%20{$shareUrl}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$shareUrl}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$shareUrl}",
        ];

        /*
        |--------------------------------------------------
        | 📦 RESPONSE
        |--------------------------------------------------
        */
        return response()->json([
            'status' => true,

            'data' => [

                // 🧾 certificate basic
                'certificate_id' => $certificate->certificate_id,
                'issued_at' => $certificate->issued_at,

                // 🧠 rendered content
                'content' => $renderedContent,



                // 🎨 design settings
                'design' => [
                    'company_name' => $setting->company_name,
                    'company_logo' => $setting->company_logo_url,
                    'tagline' => $setting->tagline,
                    'heading' => $setting->certificate_heading,

                    'signer_name' => $setting->signer_name,
                    'signer_designation' => $setting->signer_designation,
                    'signer_signature' => $setting->signer_signature_url,

                    'footer_text' => $setting->footer_text,
                ],

                // 📊 meta (optional but useful)
                'meta' => $certificate->meta,

                // 🔗 share
                'share_links' => $shareLinks,

                'context_details' => $contextDetails,
            ]
        ]);
    }

    public function moduleLearningStatus() {
        $userId = auth()->id();


        $current = UserProgress::where('user_id', $userId)
            ->where('is_unlocked', true)
            ->where('is_completed', false)
            ->whereNotNull('topic_id')
            ->with('topic.module')
            ->first();

        if (!$current || !$current->topic || !$current->topic->module) {

            return response()->json([
                'status' => false,
                'message' => 'No active learning module found.',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Module learning status fetched successfully.',
            'data' => $this->getModuleLearningStatus(
                $current->topic->module,
                $userId
            ),
        ]);
    }


    private function getModuleLearningStatus(Module $module, int $userId): array {
        /*
    |--------------------------------------------------------------------------
    | LOAD MODULE RELATIONS
    |--------------------------------------------------------------------------
    */

        $module->load([
            'program',
            'level',
            'chapters.topics',
        ]);

        /*
    |--------------------------------------------------------------------------
    | ALL TOPIC IDS
    |--------------------------------------------------------------------------
    */

        $topicIds = $module->chapters
            ->flatMap->topics
            ->pluck('id')
            ->values();

        /*
    |--------------------------------------------------------------------------
    | MODULE ASSESSMENT
    |--------------------------------------------------------------------------
    */

        $moduleAssessment = Assessment::where('assessmentable_type', Module::class)
            ->where('assessmentable_id', $module->id)
            ->where('status', true)
            ->first();

        /*
    |--------------------------------------------------------------------------
    | LAST MODULE ATTEMPT
    |--------------------------------------------------------------------------
    */

        $lastModuleAttempt = null;

        if ($moduleAssessment) {

            $lastModuleAttempt = AssessmentAttempt::where('assessment_id', $moduleAssessment->id)
                ->where('user_id', $userId)
                ->latest('submitted_at')
                ->first();
        }

        /*
    |--------------------------------------------------------------------------
    | USER PROGRESS
    |--------------------------------------------------------------------------
    */

        $userProgress = UserProgress::where('user_id', $userId)
            ->whereIn('topic_id', $topicIds)
            ->get()
            ->keyBy('topic_id');

        /*
    |--------------------------------------------------------------------------
    | TOPIC ASSESSMENTS
    |--------------------------------------------------------------------------
    */

        $topicAssessments = Assessment::where('assessmentable_type', Topic::class)
            ->whereIn('assessmentable_id', $topicIds)
            ->where('status', true)
            ->get()
            ->keyBy('assessmentable_id');

        /*
    |--------------------------------------------------------------------------
    | PASSED TOPIC ATTEMPTS
    |--------------------------------------------------------------------------
    */

        $assessmentAttempts = AssessmentAttempt::where('user_id', $userId)
            ->whereHas('assessment', function ($q) use ($topicIds) {

                $q->where('type', 'topic')
                    ->whereIn('assessmentable_id', $topicIds);
            })
            ->latest('submitted_at')
            ->get()
            ->unique('assessment_id')
            ->keyBy('assessment_id');

        /*
    |--------------------------------------------------------------------------
    | DEFAULT VARIABLES
    |--------------------------------------------------------------------------
    */

        $totalTopics = 0;

        $completedTopics = 0;

        $remainingTopics = 0;

        $nextTopic = null;

        $chapterData = [];

        $moduleProgress = [];

        $exam = [];
        /*
    |--------------------------------------------------------------------------
    | CHAPTERS
    |--------------------------------------------------------------------------
    */

        foreach ($module->chapters as $chapter) {

            $chapterCompleted = true;

            $chapterCompletedTopics = 0;

            $chapterTotalTopics = 0;

            $chapterTopics = [];

            foreach ($chapter->topics as $topic) {

                $totalTopics++;
                $chapterTotalTopics++;

                /*
            |--------------------------------------------------------------------------
            | USER PROGRESS
            |--------------------------------------------------------------------------
            */

                $progress = $userProgress->get($topic->id);

                /*
            |--------------------------------------------------------------------------
            | TOPIC ASSESSMENT
            |--------------------------------------------------------------------------
            */

                $topicAssessment = $topicAssessments->get($topic->id);

                /*
            |--------------------------------------------------------------------------
            | LAST ATTEMPT
            |--------------------------------------------------------------------------
            */

                $lastAttempt = null;

                if ($topicAssessment) {

                    $lastAttempt = $assessmentAttempts->get(
                        $topicAssessment->id
                    );
                }

                /*
            |--------------------------------------------------------------------------
            | QUIZ STATUS
            |--------------------------------------------------------------------------
            */

                $quizStatus = $lastAttempt?->status ?? 'pending';

                /*
            |--------------------------------------------------------------------------
            | TOPIC STATUS
            |--------------------------------------------------------------------------
            */

                $isUnlocked = $progress?->is_unlocked ?? false;

                // Dashboard business rule
                $isCompleted = $lastAttempt?->status === 'passed';

                if ($isCompleted) {

                    $completedTopics++;

                    $chapterCompletedTopics++;
                } else {

                    $remainingTopics++;

                    $chapterCompleted = false;

                    if (!$nextTopic && $isUnlocked) {

                        $nextTopic = [

                            'chapter_id' => $chapter->id,

                            'chapter_title' => $chapter->title,

                            'chapter_thumbnail' => $chapter->thumbnail,

                            'topic_id' => $topic->id,

                            'topic_title' => $topic->title,

                            'topic_thumbnail' => $topic->thumbnail,

                        ];
                    }
                }

                /*
                    |--------------------------------------------------------------------------
                    | TOPIC CONTENT STATUS
                    |--------------------------------------------------------------------------
                    */

                $totalContents = $topic->contents()->count();

                $readContents = UserContentProgress::where('user_id', $userId)
                    ->whereIn(
                        'topic_content_id',
                        $topic->contents()->pluck('id')
                    )
                    ->where('is_read', true)
                    ->count();

                $isAllContentsRead = $totalContents > 0
                    && $totalContents === $readContents;

                /*
                |--------------------------------------------------------------------------
                | TOPIC ARRAY
                |--------------------------------------------------------------------------
                */




                $chapterTopics[] = [

                    'id' => $topic->id,

                    'title' => $topic->title,

                    'description' => $topic->description,

                    'thumbnail' => $topic->thumbnail,

                    'estimated_duration' => $topic->estimated_duration,

                    'is_unlocked' => $isUnlocked,

                    'is_completed' => $isCompleted,

                    // NEW
                    'is_all_contents_read' => $isAllContentsRead,

                    'quiz_status' => $quizStatus,

                    'assessment' => $topicAssessment ? [

                        'id' => $topicAssessment->id,

                        'title' => $topicAssessment->title,

                        'description' => $topicAssessment->description,

                        'duration' => $topicAssessment->duration,

                        'total_marks' => $topicAssessment->total_marks,

                        'passing_score' => $topicAssessment->passing_score,

                    ] : null,

                    'last_attempt' => $lastAttempt ? [

                        'id' => $lastAttempt->id,

                        'status' => $lastAttempt->status,

                        'score' => $lastAttempt->score,

                        'total_score' => $topicAssessment->total_marks,

                        'percentage' => $lastAttempt->percentage,

                        'submitted_at' => $lastAttempt->submitted_at,

                        'time_taken' => $lastAttempt->time_taken,

                    ] : null,

                ];
            }

            /*
        |--------------------------------------------------------------------------
        | CHAPTER ARRAY
        |--------------------------------------------------------------------------
        */

            $chapterData[] = [

                'id' => $chapter->id,

                'title' => $chapter->title,

                'description' => $chapter->description,

                'thumbnail' => $chapter->thumbnail,

                'progress' => [

                    'total_topics' => $chapterTotalTopics,

                    'completed_topics' => $chapterCompletedTopics,

                    'remaining_topics' => $chapterTotalTopics - $chapterCompletedTopics,

                    'percentage' => $chapterTotalTopics > 0
                        ? round(
                            ($chapterCompletedTopics / $chapterTotalTopics) * 100,
                            2
                        )
                        : 0,

                ],

                'is_completed' => $chapterCompleted,

                'topics' => $chapterTopics,

            ];
        }

        /*
    |--------------------------------------------------------------------------
    | MODULE PROGRESS
    |--------------------------------------------------------------------------
    */

        $moduleProgress = [

            'total_topics' => $totalTopics,

            'completed_topics' => $completedTopics,

            'remaining_topics' => $remainingTopics,

            'percentage' => $totalTopics > 0
                ? round(($completedTopics / $totalTopics) * 100, 2)
                : 0,

        ];

        /*
    |--------------------------------------------------------------------------
    | MODULE EXAM UNLOCK
    |--------------------------------------------------------------------------
    */

        $examUnlocked = $totalTopics > 0
            && $completedTopics === $totalTopics;

        /*
    |--------------------------------------------------------------------------
    | MODULE EXAM STATUS
    |--------------------------------------------------------------------------
    */

        if (!$moduleAssessment) {

            $examStatus = 'not_found';
        } elseif (!$examUnlocked) {

            $examStatus = 'locked';
        } elseif (!$lastModuleAttempt) {

            $examStatus = 'ready';
        } else {

            $examStatus = match ($lastModuleAttempt->status) {

                'passed' => 'passed',

                'failed' => 'failed',

                default => 'ready',
            };
        }

        /*
    |--------------------------------------------------------------------------
    | MODULE EXAM
    |--------------------------------------------------------------------------
    */

        $exam = [

            'available' => $moduleAssessment !== null,

            'unlocked' => $examUnlocked,

            'status' => $examStatus,

            'reason' => match (true) {

                !$moduleAssessment =>
                'Module assessment not found.',

                !$examUnlocked =>
                'Pass all topic quizzes to unlock module exam.',

                default => null,
            },

            'assessment' => $moduleAssessment ? [

                'id' => $moduleAssessment->id,

                'title' => $moduleAssessment->title,

                'description' => $moduleAssessment->description,

                'thumbnail' => $moduleAssessment->file,

                'duration' => $moduleAssessment->duration,

                'total_marks' => $moduleAssessment->total_marks,

                'passing_score' => $moduleAssessment->passing_score,

            ] : null,

            'last_attempt' => $lastModuleAttempt ? [

                'id' => $lastModuleAttempt->id,

                'status' => $lastModuleAttempt->status,

                'score' => $lastModuleAttempt->score,

                'total_score' => $moduleAssessment->total_marks,

                'percentage' => $lastModuleAttempt->percentage,

                'submitted_at' => $lastModuleAttempt->submitted_at,

                'time_taken' => $lastModuleAttempt->time_taken,

            ] : null,

        ];

        /*
    |--------------------------------------------------------------------------
    | NEXT LEARNING
    |--------------------------------------------------------------------------
    */

        $nextLearning = null;

        if ($nextTopic) {

            $nextLearning = [

                'chapter' => [

                    'id' => $nextTopic['chapter_id'],

                    'title' => $nextTopic['chapter_title'],

                    'thumbnail' => $nextTopic['chapter_thumbnail'],

                ],

                'topic' => [

                    'id' => $nextTopic['topic_id'],

                    'title' => $nextTopic['topic_title'],

                    'thumbnail' => $nextTopic['topic_thumbnail'],

                ],

            ];
        }

        /*
    |--------------------------------------------------------------------------
    | RETURN
    |--------------------------------------------------------------------------
    */

        return [

            'program' => [

                'id' => $module->program?->id,

                'title' => $module->program?->title,

                'thumbnail' => $module->program?->thumbnail,

            ],

            'level' => [

                'id' => $module->level?->id,

                'title' => $module->level?->title,

                'thumbnail' => $module->level?->thumbnail,

            ],

            'module' => [

                'id' => $module->id,

                'title' => $module->title,

                'description' => $module->description,

                'thumbnail' => $module->thumbnail,

                'estimated_duration' => $module->estimated_duration,

                'is_exam_unlocked' => $examUnlocked,

                'is_completed' => $examStatus === 'passed',

            ],

            'content' => [

                'heading' => 'Learning in Progress',

                'title' => 'Complete Your Module to Unlock Certification',

                'description' => 'Continue studying the remaining topics and pass every topic quiz. Once all learning requirements are completed, your module certification exam will become available automatically.',

                'cta' => $examUnlocked
                    ? 'Start Certification Exam'
                    : 'Continue Learning',

                'footer' => 'Complete all required topics and quizzes to unlock the certification exam.',

            ],

            'progress' => $moduleProgress,

            'checklist' => [

                [

                    'title' => 'Topic Quizzes Passed',

                    'completed' => $completedTopics === $totalTopics
                        && $totalTopics > 0,

                    'completed_count' => $completedTopics,

                    'total_count' => $totalTopics,

                ],

                [

                    'title' => 'Remaining Topics',

                    'completed' => $remainingTopics === 0,

                    'remaining' => $remainingTopics,

                ],

                [

                    'title' => 'Certification Exam',

                    'completed' => $examStatus === 'passed',

                    'unlocked' => $examUnlocked,

                ],

            ],

            'next_learning' => $nextLearning,

            'exam' => $exam,

            'chapters' => $chapterData,

        ];
    }
}
