<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use DB;

class AssessmentController extends Controller
{
    protected $uploadPath = 'uploads/assessment/';

    protected function uploadFile($file)
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path($this->uploadPath), $filename);
        return $this->uploadPath . $filename;
    }

    public function index(Request $request)
    {
        $query = Assessment::with([
            'assessmentable',
            'questions:id,assessment_id'
        ]);

        /*
    |-----------------------------
    | FILTER: TYPE (topic / level)
    |-----------------------------
    */
        if ($request->filled('type')) {
            if (!in_array($request->type, ['topic', 'level'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type'
                ], 422);
            }

            $query->where('type', $request->type);
        }

        /*
    |-----------------------------
    | FILTER: TOPIC
    |-----------------------------
    */
        if ($request->filled('topic_id')) {

            if (!\App\Models\Topic::find($request->topic_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Topic not found'
                ], 404);
            }

            $query->where('assessmentable_type', \App\Models\Topic::class)
                ->where('assessmentable_id', $request->topic_id);
        }

        /*
    |-----------------------------
    | FILTER: LEVEL
    |-----------------------------
    */
        if ($request->filled('level_id')) {

            if (!\App\Models\Level::find($request->level_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Level not found'
                ], 404);
            }

            $query->where('assessmentable_type', \App\Models\Level::class)
                ->where('assessmentable_id', $request->level_id);
        }

        /*
    |-----------------------------
    | SEARCH
    |-----------------------------
    */
        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('title', 'like', "%{$search}%")

                    ->orWhereHas('assessmentable', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%{$search}%");
                    })

                    ->orWhereHas('questions', function ($q3) use ($search) {
                        $q3->where('question_text', 'like', "%{$search}%");
                    });
            });
        }

        /*
    |-----------------------------
    | STATUS
    |-----------------------------
    */
        if ($request->has('status')) {
            if ($request->status !== 'all') {
                $query->where('status', (bool)$request->status);
            }
        } else {
            $query->where('status', true);
        }

        /*
    |-----------------------------
    | SORTING
    |-----------------------------
    */
        $sortByMap = [
            'createdAt' => 'created_at',
            'title'     => 'title',
            'duration'  => 'duration',
        ];

        $sortBy = $request->get('sortBy', 'createdAt');
        $order  = strtolower($request->get('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortColumn = $sortByMap[$sortBy] ?? 'created_at';

        $query->orderBy($sortColumn, $order);

        /*
    |-----------------------------
    | PAGINATION
    |-----------------------------
    */
        $limit = (int)$request->get('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        $assessments = $query->paginate($limit);

        /*
    |-----------------------------
    | TRANSFORM
    |-----------------------------
    */
        $assessments->getCollection()->transform(function ($assessment) {

            return [
                'id' => $assessment->id,
                'type' => $assessment->type,
                'title' => $assessment->title,
                'description' => $assessment->description,
                'file' => $assessment->file,
                'duration' => $assessment->duration,
                'passing_score' => $assessment->passing_score,
                'total_marks' => $assessment->total_marks,
                'status' => (bool)$assessment->status,

                'assessmentable_type' => class_basename($assessment->assessmentable_type),
                'assessmentable_id' => $assessment->assessmentable_id,
                'assessmentable' => $assessment->assessmentable,

                'questions_count' => $assessment->questions->count(),

                'created_at' => $assessment->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $assessments
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:topic,level',
            'title' => 'required|string',
            'passing_score' => 'required|integer|min:0|max:100',
            'total_marks' => 'required|integer|min:1',
            'assessmentable_id' => 'required|integer',
            'assessmentable_type' => 'required|string',
            'file' => 'nullable|file|max:2048'
        ]);

        return DB::transaction(function () use ($request) {

            $filePath = null;

            if ($request->hasFile('file')) {
                $filePath = $this->uploadFile($request->file('file'));
            }

            $assessment = Assessment::create([
                'assessmentable_id' => $request->assessmentable_id,
                'assessmentable_type' => $request->assessmentable_type,
                'type' => $request->type,
                'title' => $request->title,
                'description' => $request->description,
                'file' => $filePath,
                'duration' => $request->duration,
                'passing_score' => $request->passing_score,
                'total_marks' => $request->total_marks,
                'created_by' => auth()->id(),
            ]);

            return response()->json($assessment);
        });
    }

    public function show($id)
    {
        return response()->json(
            Assessment::with('questions.options')->findOrFail($id)
        );
    }

    public function update(Request $request, $id)
    {
        $assessment = Assessment::findOrFail($id);

        $request->validate([
            'type' => 'sometimes|in:topic,level',
            'title' => 'sometimes|string',
            'passing_score' => 'sometimes|integer|min:0|max:100',
            'total_marks' => 'sometimes|integer|min:1',
            'assessmentable_id' => 'sometimes|integer',
            'assessmentable_type' => 'sometimes|string',
            'file' => 'nullable|file|max:2048'
        ]);

        return DB::transaction(function () use ($request, $assessment) {

            $filePath = $assessment->file; // keep old by default

            // ✅ If new file uploaded → replace old
            if ($request->hasFile('file')) {

                // delete old file (if exists)
                if ($assessment->file && file_exists(public_path($assessment->file))) {
                    @unlink(public_path($assessment->file));
                }

                $filePath = $this->uploadFile($request->file('file'));
            }

            $assessment->update([
                'assessmentable_id' => $request->assessmentable_id ?? $assessment->assessmentable_id,
                'assessmentable_type' => $request->assessmentable_type ?? $assessment->assessmentable_type,
                'type' => $request->type ?? $assessment->type,
                'title' => $request->title ?? $assessment->title,
                'description' => $request->description ?? $assessment->description,
                'file' => $filePath,
                'duration' => $request->duration ?? $assessment->duration,
                'passing_score' => $request->passing_score ?? $assessment->passing_score,
                'total_marks' => $request->total_marks ?? $assessment->total_marks,
            ]);

            return response()->json([
                'message' => 'Assessment updated successfully',
                'data' => $assessment->fresh()
            ]);
        });
    }

    public function destroy($id)
    {
        $assessment = Assessment::with('questions')->findOrFail($id);

        if ($assessment->questions()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete assessment. Questions exist under this assessment.'
            ], 422);
        }

        // Optional: delete file if exists
        if ($assessment->file && file_exists(public_path($assessment->file))) {
            unlink(public_path($assessment->file));
        }

        $assessment->delete();

        return response()->json([
            'message' => 'Assessment deleted successfully'
        ]);
    }

    
    public function toggleStatus($id)
    {
        $assessment = Assessment::findOrFail($id);
        $assessment->status = !$assessment->status;
        $assessment->save();

        return response()->json(['status' => $assessment->status]);
    }
}
