<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use App\Models\Assessment;
use App\Models\Topic;
use App\Models\Level;

class OptionController extends Controller
{
    protected $uploadPath = 'uploads/assessment/';

    protected function uploadFile($file)
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($this->uploadPath), $filename);
        return $this->uploadPath . $filename;
    }

    public function store(Request $request, $question_id)
    {
        $request->validate([
            'option_text' => 'required|string',
            'is_correct' => 'required|boolean',
            'file' => 'nullable|file|max:2048',
        ]);

        if ($request->is_correct) {
            AssessmentOption::where('question_id', $question_id)
                ->update(['is_correct' => false]);
        }

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $this->uploadFile($request->file('file'));
        }

        return AssessmentOption::create([
            'question_id' => $question_id,
            'option_text' => $request->option_text,
            'file' => $filePath,
            'is_correct' => $request->is_correct
        ]);
    }

    public function update(Request $request, $id)
    {
        $option = AssessmentOption::findOrFail($id);

        $request->validate([
            'option_text' => 'sometimes|string',
            'is_correct' => 'sometimes|boolean',
            'file' => 'nullable|file|max:2048',
        ]);

        $data = $request->only(['option_text']);

        if ($request->has('is_correct')) {

            if ($request->boolean('is_correct') === true) {

                AssessmentOption::where('question_id', $option->question_id)
                    ->update(['is_correct' => false]);

                $data['is_correct'] = true;
            } else {

                $data['is_correct'] = false;
            }
        }

        if ($request->hasFile('file')) {
            $data['file'] = $this->uploadFile($request->file('file'));
        }

        $option->update($data);

        return response()->json([
            'message' => 'Updated successfully'
        ]);
    }

    public function destroy($id)
    {
        $option = AssessmentOption::findOrFail($id);

        $count = AssessmentOption::where('question_id', $option->question_id)->count();

        if ($count <= 2) {
            return response()->json([
                'message' => 'At least 2 options are required. Cannot delete.'
            ], 422);
        }

        // Optional: delete file
        if ($option->file && file_exists(public_path($option->file))) {
            unlink(public_path($option->file));
        }

        $option->delete();

        return response()->json([
            'message' => 'Option deleted successfully'
        ]);
    }

    // 🔹 GET ALL OPTIONS (by question) + hierarchy
    public function index($question_id)
    {
        $question = AssessmentQuestion::with(['options', 'assessment'])
            ->findOrFail($question_id);

        $hierarchy = $this->buildHierarchy($question->assessment);

        return response()->json([
            'question' => [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'file' => $question->file,
            ],
            'options' => $question->options,
            'hierarchy' => $hierarchy
        ]);
    }

    // 🔹 GET SINGLE OPTION + hierarchy
    public function show($id)
    {
        $option = AssessmentOption::with('question.assessment')
            ->findOrFail($id);

        $question = $option->question;
        $assessment = $question->assessment;

        $hierarchy = $this->buildHierarchy($assessment);

        return response()->json([
            'option' => $option,
            'question' => [
                'id' => $question->id,
                'question_text' => $question->question_text,
            ],
            'hierarchy' => $hierarchy
        ]);
    }

    // 🔹 COMMON HIERARCHY BUILDER (IMPORTANT)
    private function buildHierarchy($assessment)
    {
        $hierarchy = null;

        // 👉 Topic based assessment
        if ($assessment->assessmentable_type === Topic::class) {

            $topic = Topic::with('chapter.module.level.program')
                ->find($assessment->assessmentable_id);

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

        // 👉 Level based assessment
        if ($assessment->assessmentable_type === Level::class) {

            $level = Level::with('program')
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

        return $hierarchy;
    }
}
