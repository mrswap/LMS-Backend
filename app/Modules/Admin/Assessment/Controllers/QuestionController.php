<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentQuestion;
use App\Models\Assessment;

class QuestionController extends Controller
{
    protected $uploadPath = 'uploads/assessment/';

    protected function uploadFile($file)
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($this->uploadPath), $filename);
        return $this->uploadPath . $filename;
    }

    public function index($assessment_id)
    {
        $assessment = Assessment::select('id', 'type')
            ->findOrFail($assessment_id);

        $questions = AssessmentQuestion::with('options')
            ->where('assessment_id', $assessment_id)
            ->orderBy('order')
            ->get();

        return response()->json([
            'assessment_id' => $assessment->id,
            'assessment_type' => $assessment->type,
            'questions' => $questions
        ]);
    }

    public function store(Request $request, $assessment_id)
    {
        $assessment = Assessment::findOrFail($assessment_id);

        /*
        |--------------------------------------------------------------------------
        | 🔐 VALIDATION
        |--------------------------------------------------------------------------
        */
        $request->validate([

            'question_text' => 'required|string',

            'marks' => 'nullable|integer|min:0',

            'order' => 'required|integer|min:1',

            'file' => 'nullable|file|max:2048',

            /*
            |--------------------------------------------------------------------------
            | CASE STUDY
            |--------------------------------------------------------------------------
            */

            'is_case' => 'nullable|boolean',

            'case_title' => 'nullable|string',

            'case_text' => 'nullable|string',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CASE TYPE VALIDATION
        |--------------------------------------------------------------------------
        */
        $caseEnabledFor = config(
            'assessment.case_based.enabled_for',
            []
        );

        if (
            $request->boolean('is_case') &&
            !in_array($assessment->type, $caseEnabledFor)
        ) {

            return response()->json([
                'message' => 'Case questions are not allowed for this assessment type'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | CASE CONTENT VALIDATION
        |--------------------------------------------------------------------------
        */
        if ($request->boolean('is_case')) {

            if (
                config(
                    'assessment.case_based.require_case_content',
                    true
                )
            ) {

                if (!$request->filled('case_title')) {

                    return response()->json([
                        'message' => 'case_title is required for case questions'
                    ], 422);
                }

                if (!$request->filled('case_text')) {

                    return response()->json([
                        'message' => 'case_text is required for case questions'
                    ], 422);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | FILE UPLOAD
        |--------------------------------------------------------------------------
        */
        $filePath = null;

        if ($request->hasFile('file')) {

            $filePath = $this->uploadFile(
                $request->file('file')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | AUTO CASE ORDER
        |--------------------------------------------------------------------------
        |
        | Automatically maintain serial order
        | No manual case_order required
        |
        */
        $caseOrder = null;

        if ($request->boolean('is_case')) {

            $lastCaseOrder = AssessmentQuestion::where(
                'assessment_id',
                $assessment_id
            )
                ->where('is_case', true)
                ->max('case_order');

            $caseOrder = ($lastCaseOrder ?? 0) + 1;
        }

        /*
        |--------------------------------------------------------------------------
        | CREATE QUESTION
        |--------------------------------------------------------------------------
        */
        $question = AssessmentQuestion::create([

            'assessment_id' => $assessment_id,

            'question_text' => $request->question_text,

            'file' => $filePath,

            /*
            |--------------------------------------------------------------------------
            | MARKS AUTO MANAGED
            |--------------------------------------------------------------------------
            */
            'marks' => 0,

            'order' => $request->order,

            /*
            |--------------------------------------------------------------------------
            | CASE STUDY
            |--------------------------------------------------------------------------
            */
            'is_case' => $request->boolean('is_case'),

            'case_title' => $request->case_title,

            'case_text' => $request->case_text,

            'case_order' => $caseOrder,
        ]);

        /*
        |--------------------------------------------------------------------------
        | RECALCULATE MARKS
        |--------------------------------------------------------------------------
        */
        $question->assessment
            ->recalculateQuestionMarks();

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */
        return $question->fresh();
    }

    public function update(Request $request, $id)
    {
        $question = AssessmentQuestion::findOrFail($id);

        $assessment = $question->assessment;

        /*
            |--------------------------------------------------------------------------
            | 🔐 VALIDATION
            |--------------------------------------------------------------------------
            */
        $request->validate([

            'question_text' => 'sometimes|string',

            'marks' => 'nullable|integer|min:0',

            'order' => 'sometimes|integer|min:1',

            'file' => 'nullable|file|max:2048',

            /*
                |--------------------------------------------------------------------------
                | CASE STUDY
                |--------------------------------------------------------------------------
                */

            'is_case' => 'nullable|boolean',

            'case_title' => 'nullable|string',

            'case_text' => 'nullable|string',
        ]);

        /*
            |--------------------------------------------------------------------------
            | CASE TYPE VALIDATION
            |--------------------------------------------------------------------------
            */
        $caseEnabledFor = config(
            'assessment.case_based.enabled_for',
            []
        );

        /*
            |--------------------------------------------------------------------------
            | CURRENT / NEW CASE STATE
            |--------------------------------------------------------------------------
            */
        $isCase = $request->has('is_case')
            ? $request->boolean('is_case')
            : $question->is_case;

        if (
            $isCase &&
            !in_array($assessment->type, $caseEnabledFor)
        ) {

            return response()->json([
                'message' => 'Case questions are not allowed for this assessment type'
            ], 422);
        }

        /*
            |--------------------------------------------------------------------------
            | CASE CONTENT VALIDATION
            |--------------------------------------------------------------------------
            */
        if ($isCase) {

            if (
                config(
                    'assessment.case_based.require_case_content',
                    true
                )
            ) {

                $caseTitle = $request->has('case_title')
                    ? $request->case_title
                    : $question->case_title;

                $caseText = $request->has('case_text')
                    ? $request->case_text
                    : $question->case_text;

                if (!$caseTitle) {

                    return response()->json([
                        'message' => 'case_title is required for case questions'
                    ], 422);
                }

                if (!$caseText) {

                    return response()->json([
                        'message' => 'case_text is required for case questions'
                    ], 422);
                }
            }
        }

        /*
            |--------------------------------------------------------------------------
            | SAFE UPDATE DATA
            |--------------------------------------------------------------------------
            */
        $data = $request->only([
            'question_text',
            'marks',
            'order',
        ]);

        /*
            |--------------------------------------------------------------------------
            | CASE FIELDS
            |--------------------------------------------------------------------------
            */
        if ($request->has('is_case')) {

            $data['is_case'] = $isCase;
        }

        if ($request->has('case_title')) {

            $data['case_title'] = $request->case_title;
        }

        if ($request->has('case_text')) {

            $data['case_text'] = $request->case_text;
        }

        /*
            |--------------------------------------------------------------------------
            | AUTO CASE ORDER MANAGEMENT
            |--------------------------------------------------------------------------
            |
            | Automatically maintain serial sequence
            |
            */

        /*
            |--------------------------------------------------------------------------
            | CASE ENABLED
            |--------------------------------------------------------------------------
            */
        if ($isCase) {

            /*
                |--------------------------------------------------------------------------
                | IF QUESTION WAS NOT CASE BEFORE
                |--------------------------------------------------------------------------
                */
            if (!$question->is_case) {

                $lastCaseOrder = AssessmentQuestion::where(
                    'assessment_id',
                    $assessment->id
                )
                    ->where('is_case', true)
                    ->max('case_order');

                $data['case_order'] = ($lastCaseOrder ?? 0) + 1;
            }

            /*
                |--------------------------------------------------------------------------
                | KEEP EXISTING ORDER
                |--------------------------------------------------------------------------
                */ else {

                $data['case_order'] = $question->case_order;
            }
        }

        /*
            |--------------------------------------------------------------------------
            | CASE DISABLED
            |--------------------------------------------------------------------------
            */ else {

            $data['case_order'] = null;

            $data['case_title'] = null;

            $data['case_text'] = null;
        }

        /*
            |--------------------------------------------------------------------------
            | FILE UPLOAD
            |--------------------------------------------------------------------------
            */
        if ($request->hasFile('file')) {

            $data['file'] = $this->uploadFile(
                $request->file('file')
            );
        }

        /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */
        $question->update($data);

        /*
            |--------------------------------------------------------------------------
            | RE-SEQUENCE CASE ORDERS
            |--------------------------------------------------------------------------
            */
        $caseQuestions = AssessmentQuestion::where(
            'assessment_id',
            $assessment->id
        )
            ->where('is_case', true)
            ->orderBy('case_order')
            ->orderBy('id')
            ->get();

        foreach ($caseQuestions as $index => $caseQuestion) {

            $newOrder = $index + 1;

            if ($caseQuestion->case_order != $newOrder) {

                $caseQuestion->updateQuietly([
                    'case_order' => $newOrder
                ]);
            }
        }

        /*
            |--------------------------------------------------------------------------
            | RECALCULATE MARKS
            |--------------------------------------------------------------------------
            */
        $question->assessment
            ->recalculateQuestionMarks();

        /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */
        return response()->json([
            'message' => 'Updated'
        ]);
    }



    public function show($id)
    {
        $question = \App\Models\AssessmentQuestion::with([
            'options',
            'assessment'
        ])->findOrFail($id);

        $assessment = $question->assessment;

        $hierarchy = null;

        /*
        |--------------------------------------------------------------------------
        | TOPIC BASED ASSESSMENT
        |--------------------------------------------------------------------------
        */
        if (
            $assessment->assessmentable_type
            === \App\Models\Topic::class
        ) {

            $topic = \App\Models\Topic::with([
                'chapter.module.level.program'
            ])->find($assessment->assessmentable_id);

            $hierarchy = [

                'type' => 'topic',

                'topic' => [
                    'id' => $topic->id,
                    'title' => $topic->title,
                ],

                'chapter' => [
                    'id' => $topic->chapter->id ?? null,
                    'title' => $topic->chapter->title ?? null,
                ],

                'module' => [
                    'id' => $topic->chapter->module->id ?? null,
                    'title' => $topic->chapter->module->title ?? null,
                ],

                'level' => [
                    'id' => $topic->chapter->module->level->id ?? null,
                    'title' => $topic->chapter->module->level->title ?? null,
                ],

                'program' => [
                    'id' => $topic->chapter->module->level->program->id ?? null,
                    'title' => $topic->chapter->module->level->program->title ?? null,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | MODULE BASED ASSESSMENT
        |--------------------------------------------------------------------------
        */
        if (
            $assessment->assessmentable_type
            === \App\Models\Module::class
        ) {

            $module = \App\Models\Module::with([
                'level.program'
            ])->find($assessment->assessmentable_id);

            $hierarchy = [

                'type' => 'module',

                'module' => [
                    'id' => $module->id,
                    'title' => $module->title,
                ],

                'level' => [
                    'id' => $module->level->id ?? null,
                    'title' => $module->level->title ?? null,
                ],

                'program' => [
                    'id' => $module->level->program->id ?? null,
                    'title' => $module->level->program->title ?? null,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RESERVED LEVEL ASSESSMENT
        |--------------------------------------------------------------------------
        */
        if (
            $assessment->assessmentable_type
            === \App\Models\Level::class
        ) {

            $level = \App\Models\Level::with(
                'program'
            )->find($assessment->assessmentable_id);

            $hierarchy = [

                'type' => 'level',

                'level' => [
                    'id' => $level->id,
                    'title' => $level->title,
                ],

                'program' => [
                    'id' => $level->program->id ?? null,
                    'title' => $level->program->title ?? null,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'question' => $question,
            'hierarchy' => $hierarchy
        ]);
    }

    public function destroy($id)
    {
        $question = AssessmentQuestion::with('options')->findOrFail($id);

        // 🚫 Block if options exist
        if ($question->options()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete question. Options exist under this question.'
            ], 422);
        }

        // Optional: delete file
        if ($question->file && file_exists(public_path($question->file))) {
            unlink(public_path($question->file));
        }

        $assessment = $question->assessment;

        $question->delete();

        $assessment->recalculateQuestionMarks();


        return response()->json([
            'message' => 'Question deleted successfully'
        ]);
    }
}
