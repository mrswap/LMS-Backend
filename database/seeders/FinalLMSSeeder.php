<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

use App\Models\{
    Program,
    ProgramTranslation,
    Level,
    LevelTranslation,
    Module,
    ModuleTranslation,
    Chapter,
    ChapterTranslation,
    Topic,
    TopicTranslation,
    TopicContent,
    TopicContentTranslation,
    Faq,
    FaqTranslation,
    Media,
    Assessment,
    AssessmentQuestion,
    AssessmentOption
};

class FinalLMSSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach (
            [
                AssessmentOption::class,
                AssessmentQuestion::class,
                Assessment::class,
                FaqTranslation::class,
                Faq::class,
                TopicContentTranslation::class,
                TopicContent::class,
                TopicTranslation::class,
                Topic::class,
                ChapterTranslation::class,
                Chapter::class,
                ModuleTranslation::class,
                Module::class,
                LevelTranslation::class,
                Level::class,
                ProgramTranslation::class,
                Program::class,
                Media::class,
            ] as $model
        ) {
            $model::truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $user = User::first();
        if (!$user) throw new \Exception('No users found');
        $createdBy = $user->id;

        // ================= PROGRAM =================
        $program = Program::create([
            'title' => 'Pace Maker',
            'description' => 'Training system',
            'status' => 1,
            'created_by' => $createdBy
        ]);

        // ================= LEVEL =================
        $level = Level::create([
            'program_id' => $program->id,
            'title' => 'Level 1',
            'status' => 1,
            'created_by' => $createdBy
        ]);

        // ================= MEDIA =================
        $media = Media::create([
            'title' => "Media",
            'file' => "uploads/media/sample.jpg",
            'shortcode' => "media-1",
            'created_by' => $createdBy
        ]);

        // ================= MODULE LOOP (2) =================
        for ($m = 1; $m <= 2; $m++) {

            $module = Module::create([
                'program_id' => $program->id,
                'level_id' => $level->id,
                'title' => "Module $m",
                'status' => 1,
                'created_by' => $createdBy
            ]);

            // ================= CHAPTER LOOP (2) =================
            for ($c = 1; $c <= 2; $c++) {

                $chapter = Chapter::create([
                    'program_id' => $program->id,
                    'level_id' => $level->id,
                    'module_id' => $module->id,
                    'title' => "Chapter $c",
                    'status' => 1,
                    'created_by' => $createdBy
                ]);

                // ================= TOPIC LOOP (2) =================
                for ($t = 1; $t <= 2; $t++) {

                    $topic = Topic::create([
                        'program_id' => $program->id,
                        'level_id' => $level->id,
                        'module_id' => $module->id,
                        'chapter_id' => $chapter->id,
                        'title' => "Topic $t",
                        'estimated_duration' => 10,
                        'status' => 1,
                        'created_by' => $createdBy
                    ]);

                    // ---------- CONTENT ----------
                    TopicContent::create([
                        'topic_id' => $topic->id,
                        'type' => 'text',
                        'title' => "Intro",
                        'content' => "<p>Topic content</p>",
                        'order' => 1,
                        'status' => 1,
                        'created_by' => $createdBy
                    ]);

                    TopicContent::create([
                        'topic_id' => $topic->id,
                        'type' => 'media',
                        'title' => 'Media',
                        'meta' => ['shortcode' => $media->shortcode],
                        'order' => 2,
                        'status' => 1,
                        'created_by' => $createdBy
                    ]);

                    // ---------- FAQ ----------
                    $faq = Faq::create([
                        'faqable_id' => $topic->id,
                        'faqable_type' => Topic::class,
                        'status' => 1,
                        'created_by' => $createdBy
                    ]);

                    FaqTranslation::create([
                        'faq_id' => $faq->id,
                        'language_code' => 'en',
                        'question' => 'What is this?',
                        'answer' => 'Answer'
                    ]);

                    // ================= QUIZ (5 QUESTIONS) =================
                    $quiz = Assessment::create([
                        'assessmentable_id' => $topic->id,
                        'assessmentable_type' => Topic::class,
                        'type' => 'topic',
                        'title' => "Quiz",
                        'passing_score' => 60,
                        'duration' => 10,
                        'total_marks' => 0, // REQUIRED
                        'status' => 1,
                        'created_by' => $createdBy
                    ]);

                    $totalMarks = 0;

                    for ($q = 1; $q <= 5; $q++) {

                        $marks = 1; // changeable later

                        $question = AssessmentQuestion::create([
                            'assessment_id' => $quiz->id,
                            'question_text' => "Question $q",
                            'question_type' => 'mcq',
                            'marks' => $marks,
                            'order' => $q
                        ]);

                        $totalMarks += $marks;

                        foreach (['A', 'B', 'C', 'D'] as $k => $opt) {
                            AssessmentOption::create([
                                'question_id' => $question->id,
                                'option_text' => "Option $opt",
                                'is_correct' => $k === 0
                            ]);
                        }
                    }

                    // FINAL UPDATE
                    $quiz->update([
                        'total_marks' => $totalMarks
                    ]);
                }
            }
        }

        // ================= LEVEL EXAM (5 QUESTIONS) =================
        $exam = Assessment::create([
            'assessmentable_id' => $level->id,
            'assessmentable_type' => Level::class,
            'type' => 'level',
            'title' => 'Final Exam',
            'duration' => 20,
            'passing_score' => 80,
            'total_marks' => 0, // REQUIRED
            'status' => 1,
            'created_by' => $createdBy
        ]);

        $totalMarks = 0;

        for ($i = 1; $i <= 5; $i++) {

            $marks = 1;

            $q = AssessmentQuestion::create([
                'assessment_id' => $exam->id,
                'question_text' => "Final Question $i",
                'question_type' => 'mcq',
                'marks' => $marks,
                'order' => $i
            ]);

            $totalMarks += $marks;

            foreach (['A', 'B', 'C', 'D'] as $k => $opt) {
                AssessmentOption::create([
                    'question_id' => $q->id,
                    'option_text' => "Option $opt",
                    'is_correct' => $k === 1
                ]);
            }
        }

        // FINAL UPDATE
        $exam->update([
            'total_marks' => $totalMarks
        ]);
    }
}
