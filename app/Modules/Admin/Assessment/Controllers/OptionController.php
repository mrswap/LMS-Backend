<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AssessmentOption;

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

        if ($request->is_correct) {
            AssessmentOption::where('question_id', $option->question_id)
                ->update(['is_correct' => false]);
        }

        $data = $request->only(['option_text', 'is_correct']);

        if ($request->hasFile('file')) {
            $data['file'] = $this->uploadFile($request->file('file'));
        }

        $option->update($data);

        return response()->json(['message' => 'Updated']);
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
}
