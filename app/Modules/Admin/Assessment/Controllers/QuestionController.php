<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentQuestion;

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
        return AssessmentQuestion::with('options')
            ->where('assessment_id', $assessment_id)
            ->orderBy('order')
            ->get();
    }

    public function store(Request $request, $assessment_id)
    {
        $request->validate([
            'question_text' => 'required|string',
            'marks' => 'required|integer|min:1',
            'order' => 'required|integer|min:1',
            'file' => 'nullable|file|max:2048',
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $this->uploadFile($request->file('file'));
        }

        return AssessmentQuestion::create([
            'assessment_id' => $assessment_id,
            'question_text' => $request->question_text,
            'file' => $filePath,
            'marks' => $request->marks,
            'order' => $request->order
        ]);
    }

    public function update(Request $request, $id)
    {
        $question = AssessmentQuestion::findOrFail($id);

        $request->validate([
            'question_text' => 'sometimes|string',
            'marks' => 'sometimes|integer|min:1',
            'order' => 'sometimes|integer|min:1',
            'file' => 'nullable|file|max:2048',
        ]);

        $data = $request->only(['question_text', 'marks', 'order']);

        if ($request->hasFile('file')) {
            $data['file'] = $this->uploadFile($request->file('file'));
        }

        $question->update($data);

        return response()->json(['message' => 'Updated']);
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

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully'
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

        // 🔹 CASE 1: Topic based assessment
        if ($assessment->assessmentable_type === \App\Models\Topic::class) {

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
                    'id' => $topic->chapter->id,
                    'title' => $topic->chapter->title,
                ],

                'module' => [
                    'id' => $topic->chapter->module->id,
                    'title' => $topic->chapter->module->title,
                ],

                'level' => [
                    'id' => $topic->chapter->module->level->id,
                    'title' => $topic->chapter->module->level->title,
                ],

                'program' => [
                    'id' => $topic->chapter->module->level->program->id,
                    'title' => $topic->chapter->module->level->program->title,
                ],
            ];
        }

        // 🔹 CASE 2: Level based assessment
        if ($assessment->assessmentable_type === \App\Models\Level::class) {

            $level = \App\Models\Level::with('program')
                ->find($assessment->assessmentable_id);

            $hierarchy = [
                'type' => 'level',

                'level' => [
                    'id' => $level->id,
                    'title' => $level->title,
                ],

                'program' => [
                    'id' => $level->program->id,
                    'title' => $level->program->title,
                ],
            ];
        }

        return response()->json([
            'question' => $question,
            'hierarchy' => $hierarchy
        ]);
    }
}
