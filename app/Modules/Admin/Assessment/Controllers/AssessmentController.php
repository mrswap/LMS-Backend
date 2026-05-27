<?php

namespace App\Modules\Admin\Assessment\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assessment;
use DB;
use Illuminate\Database\Eloquent\Relations\MorphTo;


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

            'questions:id,assessment_id',

            'creator:id,name,email',

            'assessmentable' => function (MorphTo $morphTo) {

                $morphTo->morphWith([

                    \App\Models\Topic::class => [
                        'chapter.module.level.program'
                    ],

                    \App\Models\Chapter::class => [
                        'module.level.program'
                    ],

                    \App\Models\Module::class => [
                        'level.program'
                    ],

                    \App\Models\Level::class => [
                        'program'
                    ],
                ]);
            }
        ]);

        /*
    |--------------------------------------------------
    | FILTER: TYPE
    |--------------------------------------------------
    */

        if ($request->filled('type')) {

            if (!in_array(
                $request->type,
                ['topic', 'chapter', 'module', 'level']
            )) {

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type'
                ], 422);
            }

            $query->where('type', $request->type);
        }

        /*
    |--------------------------------------------------
    | HIERARCHY FILTER
    | topic > chapter > module > level
    |--------------------------------------------------
    */

        if (
            $request->filled('topic_id') ||
            $request->filled('chapter_id') ||
            $request->filled('module_id') ||
            $request->filled('level_id')
        ) {

            $query->where(function ($q) use ($request) {

                /*
            |--------------------------------------------------
            | TOPIC
            |--------------------------------------------------
            */

                if ($request->filled('topic_id')) {

                    $q->where(function ($qq) use ($request) {

                        $qq->where(function ($x) use ($request) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Topic::class
                            )
                                ->where(
                                    'assessmentable_id',
                                    $request->topic_id
                                );
                        });
                    });

                    return;
                }

                /*
            |--------------------------------------------------
            | CHAPTER
            |--------------------------------------------------
            */

                if ($request->filled('chapter_id')) {

                    $q->where(function ($qq) use ($request) {

                        $topicIds = \App\Models\Topic::where(
                            'chapter_id',
                            $request->chapter_id
                        )->pluck('id');

                        /*
                    |------------------------------------------
                    | TOPIC ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($topicIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Topic::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | CHAPTER ASSESSMENT
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($request) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Chapter::class
                            )
                                ->where(
                                    'assessmentable_id',
                                    $request->chapter_id
                                );
                        });
                    });

                    return;
                }

                /*
            |--------------------------------------------------
            | MODULE
            |--------------------------------------------------
            */

                if ($request->filled('module_id')) {

                    $q->where(function ($qq) use ($request) {

                        $chapterIds = \App\Models\Chapter::where(
                            'module_id',
                            $request->module_id
                        )->pluck('id');

                        $topicIds = \App\Models\Topic::whereIn(
                            'chapter_id',
                            $chapterIds
                        )->pluck('id');

                        /*
                    |------------------------------------------
                    | TOPIC ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($topicIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Topic::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | CHAPTER ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($chapterIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Chapter::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $chapterIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | MODULE ASSESSMENT
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($request) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Module::class
                            )
                                ->where(
                                    'assessmentable_id',
                                    $request->module_id
                                );
                        });
                    });

                    return;
                }

                /*
            |--------------------------------------------------
            | LEVEL
            |--------------------------------------------------
            */

                if ($request->filled('level_id')) {

                    $moduleIds = \App\Models\Module::where(
                        'level_id',
                        $request->level_id
                    )->pluck('id');

                    $chapterIds = \App\Models\Chapter::whereIn(
                        'module_id',
                        $moduleIds
                    )->pluck('id');

                    $topicIds = \App\Models\Topic::whereIn(
                        'chapter_id',
                        $chapterIds
                    )->pluck('id');

                    $q->where(function ($qq) use (
                        $request,
                        $moduleIds,
                        $chapterIds,
                        $topicIds
                    ) {

                        /*
                    |------------------------------------------
                    | TOPIC ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($topicIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Topic::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $topicIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | CHAPTER ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($chapterIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Chapter::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $chapterIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | MODULE ASSESSMENTS
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($moduleIds) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Module::class
                            )
                                ->whereIn(
                                    'assessmentable_id',
                                    $moduleIds
                                );
                        });

                        /*
                    |------------------------------------------
                    | LEVEL ASSESSMENT
                    |------------------------------------------
                    */

                        $qq->orWhere(function ($x) use ($request) {

                            $x->where(
                                'assessmentable_type',
                                \App\Models\Level::class
                            )
                                ->where(
                                    'assessmentable_id',
                                    $request->level_id
                                );
                        });
                    });
                }
            });
        }

        /*
    |--------------------------------------------------
    | SEARCH
    |--------------------------------------------------
    */

        if ($request->filled('search')) {

            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where(
                    'title',
                    'like',
                    "%{$search}%"
                )
                    ->orWhereHas('assessmentable', function ($q2) use ($search) {

                        $q2->where(
                            'title',
                            'like',
                            "%{$search}%"
                        );
                    })
                    ->orWhereHas('questions', function ($q3) use ($search) {

                        $q3->where(
                            'question_text',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        /*
    |--------------------------------------------------
    | STATUS
    |--------------------------------------------------
    */

        if ($request->has('status')) {

            if ($request->status !== 'all') {

                $query->where(
                    'status',
                    (bool)$request->status
                );
            }
        } else {

            $query->where('status', true);
        }

        /*
    |--------------------------------------------------
    | SORTING
    |--------------------------------------------------
    */

        $sortByMap = [

            'createdAt' => 'created_at',

            'title' => 'title',

            'duration' => 'duration',
        ];

        $sortBy = $request->get(
            'sortBy',
            'createdAt'
        );

        $order = strtolower(
            $request->get('order', 'desc')
        ) === 'asc'
            ? 'asc'
            : 'desc';

        $query->orderBy(
            $sortByMap[$sortBy] ?? 'created_at',
            $order
        );

        /*
    |--------------------------------------------------
    | PAGINATION
    |--------------------------------------------------
    */

        $limit = (int)$request->get('limit', 10);

        $limit = (
            $limit > 0 &&
            $limit <= 100
        )
            ? $limit
            : 10;

        $assessments = $query->paginate($limit);

        /*
    |--------------------------------------------------
    | TRANSFORM
    |--------------------------------------------------
    */

        $assessments->getCollection()->transform(function ($assessment) {

            $hierarchy = null;

            /*
        |--------------------------------------------------
        | TOPIC
        |--------------------------------------------------
        */

            if (
                $assessment->assessmentable_type ===
                \App\Models\Topic::class
            ) {

                $t = $assessment->assessmentable;

                $hierarchy = [

                    'type' => 'topic',

                    'topic' => [
                        'id' => $t->id,
                        'title' => $t->title
                    ],

                    'chapter' => [
                        'id' => $t->chapter->id ?? null,
                        'title' => $t->chapter->title ?? null
                    ],

                    'module' => [
                        'id' => $t->chapter->module->id ?? null,
                        'title' => $t->chapter->module->title ?? null
                    ],

                    'level' => [
                        'id' => $t->chapter->module->level->id ?? null,
                        'title' => $t->chapter->module->level->title ?? null
                    ],

                    'program' => [
                        'id' => $t->chapter->module->level->program->id ?? null,
                        'title' => $t->chapter->module->level->program->title ?? null
                    ],
                ];
            }

            /*
        |--------------------------------------------------
        | CHAPTER
        |--------------------------------------------------
        */

            if (
                $assessment->assessmentable_type ===
                \App\Models\Chapter::class
            ) {

                $c = $assessment->assessmentable;

                $hierarchy = [

                    'type' => 'chapter',

                    'chapter' => [
                        'id' => $c->id,
                        'title' => $c->title
                    ],

                    'module' => [
                        'id' => $c->module->id ?? null,
                        'title' => $c->module->title ?? null
                    ],

                    'level' => [
                        'id' => $c->module->level->id ?? null,
                        'title' => $c->module->level->title ?? null
                    ],

                    'program' => [
                        'id' => $c->module->level->program->id ?? null,
                        'title' => $c->module->level->program->title ?? null
                    ],
                ];
            }

            /*
        |--------------------------------------------------
        | MODULE
        |--------------------------------------------------
        */

            if (
                $assessment->assessmentable_type ===
                \App\Models\Module::class
            ) {

                $m = $assessment->assessmentable;

                $hierarchy = [

                    'type' => 'module',

                    'module' => [
                        'id' => $m->id,
                        'title' => $m->title
                    ],

                    'level' => [
                        'id' => $m->level->id ?? null,
                        'title' => $m->level->title ?? null
                    ],

                    'program' => [
                        'id' => $m->level->program->id ?? null,
                        'title' => $m->level->program->title ?? null
                    ],
                ];
            }

            /*
        |--------------------------------------------------
        | LEVEL
        |--------------------------------------------------
        */

            if (
                $assessment->assessmentable_type ===
                \App\Models\Level::class
            ) {

                $l = $assessment->assessmentable;

                $hierarchy = [

                    'type' => 'level',

                    'level' => [
                        'id' => $l->id,
                        'title' => $l->title
                    ],

                    'program' => [
                        'id' => $l->program->id ?? null,
                        'title' => $l->program->title ?? null
                    ],
                ];
            }

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

                'creator' => [

                    'id' => $assessment->creator->id ?? null,

                    'name' => $assessment->creator->name ?? null,

                    'email' => $assessment->creator->email ?? null,
                ],

                'hierarchy' => $hierarchy,

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

            'type' => 'required|in:topic,chapter,module,level',

            'title' => 'required|string',

            'passing_score' => 'required|integer|min:0|max:100',

            'total_marks' => 'required|integer|min:1',

            'assessmentable_id' => 'required|integer',

            'assessmentable_type' => [
                'required',
                'string',
                'in:App\Models\Topic,App\Models\Chapter,App\Models\Module,App\Models\Level'
            ],

            'file' => 'nullable|file|max:2048'
        ]);

        $mapping = [

            'topic' => \App\Models\Topic::class,

            'chapter' => \App\Models\Chapter::class,

            'module' => \App\Models\Module::class,

            'level' => \App\Models\Level::class,
        ];

        if (
            !isset($mapping[$request->type]) ||
            $mapping[$request->type] !== $request->assessmentable_type
        ) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid assessment mapping'
            ], 422);
        }

        $modelClass = $request->assessmentable_type;

        $exists = $modelClass::find($request->assessmentable_id);

        if (!$exists) {

            return response()->json([
                'success' => false,
                'message' => 'Parent not found'
            ], 404);
        }

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
        $assessment = Assessment::with([
            'questions.options'
        ])->findOrFail($id);

        $parent = null;

        /*
    |--------------------------------------------------
    | TOPIC
    |--------------------------------------------------
    */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Topic::class
        ) {

            $topic = \App\Models\Topic::with([
                'chapter.module.level.program'
            ])->find($assessment->assessmentable_id);

            if ($topic) {

                $parent = [

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
        }

        /*
        |--------------------------------------------------
        | CHAPTER
        |--------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Chapter::class
        ) {

            $chapter = \App\Models\Chapter::with([
                'module.level.program'
            ])->find($assessment->assessmentable_id);

            if ($chapter) {

                $parent = [

                    'type' => 'chapter',

                    'chapter' => [
                        'id' => $chapter->id,
                        'title' => $chapter->title,
                    ],

                    'module' => [
                        'id' => $chapter->module->id ?? null,
                        'title' => $chapter->module->title ?? null,
                    ],

                    'level' => [
                        'id' => $chapter->module->level->id ?? null,
                        'title' => $chapter->module->level->title ?? null,
                    ],

                    'program' => [
                        'id' => $chapter->module->level->program->id ?? null,
                        'title' => $chapter->module->level->program->title ?? null,
                    ],
                ];
            }
        }

        /*
        |--------------------------------------------------
        | MODULE
        |--------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Module::class
        ) {

            $module = \App\Models\Module::with([
                'level.program'
            ])->find($assessment->assessmentable_id);

            if ($module) {

                $parent = [

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
        }

        /*
        |--------------------------------------------------
        | LEVEL
        |--------------------------------------------------
        */

        if (
            $assessment->assessmentable_type ===
            \App\Models\Level::class
        ) {

            $level = \App\Models\Level::with([
                'program'
            ])->find($assessment->assessmentable_id);

            if ($level) {

                $parent = [

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
        }

        return response()->json([

            'assessment' => $assessment,

            'hierarchy' => $parent
        ]);
    }



    public function update(Request $request, $id)
    {
        $assessment = Assessment::findOrFail($id);

        $request->validate([

            /*
        |--------------------------------------------------
        | BASIC
        |--------------------------------------------------
        */
            'type' => 'sometimes|in:topic,chapter,module,level',

            'title' => 'sometimes|string',

            'description' => 'nullable|string',

            'duration' => 'sometimes|integer|min:1',

            'passing_score' => 'sometimes|integer|min:0|max:100',

            'total_marks' => 'sometimes|integer|min:1',

            'status' => 'sometimes|boolean',

            /*
        |--------------------------------------------------
        | PARENT
        |--------------------------------------------------
        */
            'assessmentable_id' => 'sometimes|integer',

            'assessmentable_type' => [
                'sometimes',
                'string',
                'in:App\Models\Topic,App\Models\Chapter,App\Models\Module,App\Models\Level'
            ],

            /*
        |--------------------------------------------------
        | FILE
        |--------------------------------------------------
        */
            'file' => 'nullable|file|max:2048'
        ]);

        return DB::transaction(function () use (
            $request,
            $assessment
        ) {

            /*
        |--------------------------------------------------
        | TYPES
        |--------------------------------------------------
        */
            $type = $request->type
                ?? $assessment->type;

            $assessmentableType = $request->assessmentable_type
                ?? $assessment->assessmentable_type;

            /*
        |--------------------------------------------------
        | TYPE MAP
        |--------------------------------------------------
        */
            $mapping = [

                'topic' => \App\Models\Topic::class,

                'chapter' => \App\Models\Chapter::class,

                'module' => \App\Models\Module::class,

                'level' => \App\Models\Level::class,
            ];

            /*
        |--------------------------------------------------
        | VALIDATE MAPPING
        |--------------------------------------------------
        */
            if (
                !isset($mapping[$type]) ||
                $mapping[$type] !== $assessmentableType
            ) {

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid assessment mapping'
                ], 422);
            }

            /*
        |--------------------------------------------------
        | PARENT ID
        |--------------------------------------------------
        */
            $assessmentableId = $request->assessmentable_id
                ?? $assessment->assessmentable_id;

            /*
        |--------------------------------------------------
        | CHECK PARENT EXISTS
        |--------------------------------------------------
        */
            $exists = $assessmentableType::find(
                $assessmentableId
            );

            if (!$exists) {

                return response()->json([
                    'success' => false,
                    'message' => 'Parent not found'
                ], 404);
            }

            /*
        |--------------------------------------------------
        | DUPLICATE PREVENTION
        |--------------------------------------------------
        */
            $duplicate = Assessment::where(
                'assessmentable_type',
                $assessmentableType
            )
                ->where(
                    'assessmentable_id',
                    $assessmentableId
                )
                ->where(
                    'id',
                    '!=',
                    $assessment->id
                )
                ->exists();

            if ($duplicate) {

                return response()->json([
                    'success' => false,
                    'message' => 'Assessment already exists for this parent'
                ], 422);
            }

            /*
        |--------------------------------------------------
        | FILE UPDATE
        |--------------------------------------------------
        */
            $filePath = $assessment->getRawOriginal('file');

            if ($request->hasFile('file')) {

                if (
                    $assessment->getRawOriginal('file') &&
                    file_exists(
                        public_path(
                            $assessment->getRawOriginal('file')
                        )
                    )
                ) {

                    @unlink(
                        public_path(
                            $assessment->getRawOriginal('file')
                        )
                    );
                }

                $filePath = $this->uploadFile(
                    $request->file('file')
                );
            }

            /*
        |--------------------------------------------------
        | OLD MARKS
        |--------------------------------------------------
        */
            $oldTotalMarks = $assessment->total_marks;

            /*
        |--------------------------------------------------
        | UPDATE
        |--------------------------------------------------
        */
            $assessment->update([

                'assessmentable_id' => $assessmentableId,

                'assessmentable_type' => $assessmentableType,

                'type' => $type,

                'title' => $request->title
                    ?? $assessment->title,

                'description' => $request->description
                    ?? $assessment->description,

                'file' => $filePath,

                'duration' => $request->duration
                    ?? $assessment->duration,

                'passing_score' => $request->passing_score
                    ?? $assessment->passing_score,

                'total_marks' => $request->total_marks
                    ?? $assessment->total_marks,

                'status' => $request->has('status')
                    ? $request->status
                    : $assessment->status,
            ]);

            /*
        |--------------------------------------------------
        | RECALCULATE QUESTION MARKS
        |--------------------------------------------------
        */
            if (
                $request->filled('total_marks') &&
                $oldTotalMarks != $assessment->total_marks
            ) {

                $assessment->recalculateQuestionMarks();
            }

            /*
        |--------------------------------------------------
        | RESPONSE
        |--------------------------------------------------
        */
            return response()->json([

                'success' => true,

                'message' => 'Assessment updated successfully',

                'data' => $assessment->fresh([
                    'questions.options',
                    'assessmentable'
                ])
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

    private function resolveAssessmentable(string $type)
    {
        return match ($type) {

            'topic' => \App\Models\Topic::class,
            'chapter' => \App\Models\Chapter::class,
            'module' => \App\Models\Module::class,
            'level' => \App\Models\Level::class,

            default => null
        };
    }
}
