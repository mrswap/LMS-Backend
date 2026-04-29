<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

use App\Models\{
    Program, ProgramTranslation,
    Level, LevelTranslation,
    Module, ModuleTranslation,
    Chapter, ChapterTranslation,
    Topic, TopicTranslation,
    TopicContent, TopicContentTranslation,
    Faq, FaqTranslation,
    Media,
    Assessment, AssessmentQuestion, AssessmentOption
};

class FinalLMSSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ([
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
        ] as $model) {
            $model::truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $user = User::first();
        if (!$user) throw new \Exception('No users found');
        $createdBy = $user->id;

        /*
        |--------------------------------------------------------------------------
        | PROGRAM
        |--------------------------------------------------------------------------
        */

        $program = Program::create([
            'title' => 'Pace Maker',
            'description' => 'Complete pacemaker training system',
            'status' => 1,
            'created_by' => $createdBy
        ]);

        ProgramTranslation::insert([
            ['program_id'=>$program->id,'language_code'=>'en','title'=>'Pace Maker','description'=>'Pacemaker training'],
            ['program_id'=>$program->id,'language_code'=>'hi','title'=>'पेसमेकर','description'=>'पेसमेकर प्रशिक्षण'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | LEVEL
        |--------------------------------------------------------------------------
        */

        $level = Level::create([
            'program_id'=>$program->id,
            'title'=>'Level 1',
            'description'=>'Basic pacemaker understanding',
            'status'=>1,
            'created_by'=>$createdBy
        ]);

        LevelTranslation::insert([
            ['level_id'=>$level->id,'language_code'=>'en','title'=>'Level 1','description'=>'Basic level'],
            ['level_id'=>$level->id,'language_code'=>'hi','title'=>'स्तर 1','description'=>'मूल स्तर'],
        ]);

        /*
        |--------------------------------------------------------------------------
        | MEDIA (GLOBAL)
        |--------------------------------------------------------------------------
        */

        $mediaItems = [];
        for ($i=1;$i<=5;$i++) {
            $mediaItems[] = Media::create([
                'title'=>"Media $i",
                'file'=>"uploads/media/$i.jpg",
                'shortcode'=>"media-$i",
                'created_by'=>$createdBy
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE LOOP (3 modules)
        |--------------------------------------------------------------------------
        */

        for ($m=1;$m<=3;$m++) {

            $module = Module::create([
                'program_id'=>$program->id,
                'level_id'=>$level->id,
                'title'=>"Module $m",
                'description'=>"Module $m description",
                'status'=>1,
                'created_by'=>$createdBy
            ]);

            ModuleTranslation::insert([
                ['module_id'=>$module->id,'language_code'=>'en','title'=>"Module $m",'description'=>'Details'],
                ['module_id'=>$module->id,'language_code'=>'hi','title'=>"मॉड्यूल $m",'description'=>'विवरण'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | CHAPTER LOOP (3 each)
            |--------------------------------------------------------------------------
            */

            for ($c=1;$c<=3;$c++) {

                $chapter = Chapter::create([
                    'program_id'=>$program->id,
                    'level_id'=>$level->id,
                    'module_id'=>$module->id,
                    'title'=>"Chapter $c",
                    'description'=>"Chapter $c description",
                    'status'=>1,
                    'created_by'=>$createdBy
                ]);

                ChapterTranslation::insert([
                    ['chapter_id'=>$chapter->id,'language_code'=>'en','title'=>"Chapter $c",'description'=>'Details'],
                    ['chapter_id'=>$chapter->id,'language_code'=>'hi','title'=>"अध्याय $c",'description'=>'विवरण'],
                ]);

                /*
                |--------------------------------------------------------------------------
                | TOPIC LOOP (3 each)
                |--------------------------------------------------------------------------
                */

                for ($t=1;$t<=3;$t++) {

                    $topic = Topic::create([
                        'program_id'=>$program->id,
                        'level_id'=>$level->id,
                        'module_id'=>$module->id,
                        'chapter_id'=>$chapter->id,
                        'title'=>"Topic $t",
                        'description'=>"Topic $t explanation",
                        'estimated_duration'=>10,
                        'status'=>1,
                        'created_by'=>$createdBy
                    ]);

                    TopicTranslation::insert([
                        ['topic_id'=>$topic->id,'language_code'=>'en','title'=>"Topic $t",'description'=>'Topic details'],
                        ['topic_id'=>$topic->id,'language_code'=>'hi','title'=>"विषय $t",'description'=>'विवरण'],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | CONTENT (MATCH CONTROLLER)
                    |--------------------------------------------------------------------------
                    */

                    // TEXT
                    for ($i=1;$i<=2;$i++) {

                        $text = TopicContent::create([
                            'topic_id'=>$topic->id,
                            'type'=>'text',
                            'title'=>"Text $i",
                            'content'=>"<p>Content $i for topic $t</p>",
                            'order'=>$i,
                            'status'=>1,
                            'created_by'=>$createdBy
                        ]);

                        TopicContentTranslation::create([
                            'topic_content_id'=>$text->id,
                            'language_code'=>'hi',
                            'title'=>"टेक्स्ट $i",
                            'content'=>"<p>विषय $t कंटेंट $i</p>"
                        ]);
                    }

                    // MEDIA
                    $media = $mediaItems[array_rand($mediaItems)];

                    TopicContent::create([
                        'topic_id'=>$topic->id,
                        'type'=>'media',
                        'title'=>'Media Block',
                        'meta'=>['shortcode'=>$media->shortcode],
                        'order'=>3,
                        'status'=>1,
                        'created_by'=>$createdBy
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | FAQ
                    |--------------------------------------------------------------------------
                    */

                    $faq = Faq::create([
                        'faqable_id'=>$topic->id,
                        'faqable_type'=>Topic::class,
                        'status'=>1,
                        'created_by'=>$createdBy
                    ]);

                    FaqTranslation::insert([
                        ['faq_id'=>$faq->id,'language_code'=>'en','question'=>'What is this?','answer'=>'Explanation'],
                        ['faq_id'=>$faq->id,'language_code'=>'hi','question'=>'यह क्या है?','answer'=>'विवरण'],
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | QUIZ
                    |--------------------------------------------------------------------------
                    */

                    $quiz = Assessment::create([
                        'assessmentable_id'=>$topic->id,
                        'assessmentable_type'=>Topic::class,
                        'type'=>'topic',
                        'title'=>"Quiz",
                        'passing_score'=>60,
                        'total_marks'=>5,
                        'duration'=>10,
                        'status'=>1,
                        'created_by'=>$createdBy
                    ]);

                    for ($q=1;$q<=5;$q++) {

                        $question = AssessmentQuestion::create([
                            'assessment_id'=>$quiz->id,
                            'question_text'=>"Question $q",
                            'question_type'=>'mcq',
                            'marks'=>1,
                            'order'=>$q
                        ]);

                        foreach (['A','B','C','D'] as $k=>$opt) {
                            AssessmentOption::create([
                                'question_id'=>$question->id,
                                'option_text'=>"Option $opt",
                                'is_correct'=>$k===0
                            ]);
                        }
                    }
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | LEVEL EXAM
        |--------------------------------------------------------------------------
        */

        $exam = Assessment::create([
            'assessmentable_id'=>$level->id,
            'assessmentable_type'=>Level::class,
            'type'=>'level',
            'title'=>'Final Exam',
            'duration'=>30,
            'passing_score'=>80,
            'total_marks'=>15,
            'status'=>1,
            'created_by'=>$createdBy
        ]);

        for ($i=1;$i<=15;$i++) {

            $q = AssessmentQuestion::create([
                'assessment_id'=>$exam->id,
                'question_text'=>"Final Question $i",
                'question_type'=>'mcq',
                'marks'=>1,
                'order'=>$i
            ]);

            foreach (['A','B','C','D'] as $k=>$opt) {
                AssessmentOption::create([
                    'question_id'=>$q->id,
                    'option_text'=>"Option $opt",
                    'is_correct'=>$k===1
                ]);
            }
        }
    }
}