<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Topic;
use App\Models\Program;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/topics/';

    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $query = Topic::with([
            'creator:id,name',
            'program:id,title',
            'level:id,title',
            'module:id,title',
            'chapter:id,title',
            'translations'
        ]);

        /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */
        if ($request->filled('program_id')) {
            if (!Program::find($request->program_id)) {
                return response()->json(['success' => false, 'message' => 'Program not found'], 404);
            }
            $query->where('program_id', $request->program_id);
        }

        if ($request->filled('level_id')) {
            if (!Level::find($request->level_id)) {
                return response()->json(['success' => false, 'message' => 'Level not found'], 404);
            }
            $query->where('level_id', $request->level_id);
        }

        if ($request->filled('module_id')) {
            if (!Module::find($request->module_id)) {
                return response()->json(['success' => false, 'message' => 'Module not found'], 404);
            }
            $query->where('module_id', $request->module_id);
        }

        if ($request->filled('chapter_id')) {
            if (!Chapter::find($request->chapter_id)) {
                return response()->json(['success' => false, 'message' => 'Chapter not found'], 404);
            }
            $query->where('chapter_id', $request->chapter_id);
        }

        /*
    |--------------------------------------------------------------------------
    | SEARCH (TOPIC + CHAPTER + MODULE + LEVEL + PROGRAM)
    |--------------------------------------------------------------------------
    */
        if ($request->filled('search')) {

            $search = $request->search;

            if ($lang === 'en') {

                $query->where(function ($q) use ($search) {

                    // Topic
                    $q->where('title', 'like', "%{$search}%")

                        // Chapter
                        ->orWhereHas('chapter', function ($q2) use ($search) {
                            $q2->where('title', 'like', "%{$search}%");
                        })

                        // Module
                        ->orWhereHas('module', function ($q3) use ($search) {
                            $q3->where('title', 'like', "%{$search}%");
                        })

                        // Level
                        ->orWhereHas('level', function ($q4) use ($search) {
                            $q4->where('title', 'like', "%{$search}%");
                        })

                        // Program
                        ->orWhereHas('program', function ($q5) use ($search) {
                            $q5->where('title', 'like', "%{$search}%");
                        });
                });
            } else {

                // 🔥 Translation-based search (Topic only)
                $query->whereHas('translations', function ($q) use ($lang, $search) {
                    $q->where('language_code', $lang)
                        ->where('title', 'like', "%{$search}%");
                });
            }
        }

        /*
    |--------------------------------------------------------------------------
    | HIDE BASE_RECORD (EN ONLY)
    |--------------------------------------------------------------------------
    */
        if ($lang === 'en' && !$request->filled('search')) {
            $query->where('title', '!=', 'BASE_RECORD');
        }

        $topics = $query->latest()->paginate(10);

        /*
    |--------------------------------------------------------------------------
    | TRANSFORM RESPONSE
    |--------------------------------------------------------------------------
    */
        $topics->getCollection()->transform(function ($topic) use ($lang) {

            if ($lang === 'en') {
                return [
                    'id' => $topic->id,
                    'language_code' => 'en',
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'thumbnail' => $topic->thumbnail,
                    'estimated_duration' => $topic->estimated_duration,
                    'status' => (bool) $topic->status,
                    'program' => $topic->program,
                    'level' => $topic->level,
                    'module' => $topic->module,
                    'chapter' => $topic->chapter,
                    'creator' => $topic->creator,
                    'created_at' => $topic->created_at,
                ];
            }

            $translation = $topic->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $topic->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $topic->thumbnail,
                'estimated_duration' => $topic->estimated_duration,
                'status' => (bool) $topic->status,
                'program' => $topic->program,
                'level' => $topic->level,
                'module' => $topic->module,
                'chapter' => $topic->chapter,
                'creator' => $topic->creator,
                'created_at' => $topic->created_at,
            ];
        });

        $topics->setCollection(
            $topics->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $topics
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $validated = $request->validate([
            'program_id' => 'required|integer',
            'level_id' => 'required|integer',
            'module_id' => 'required|integer',
            'chapter_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'estimated_duration' => 'nullable|integer|min:1',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // hierarchy validation (same as your code)
        $program = Program::find($validated['program_id']);
        $level   = Level::find($validated['level_id']);
        $module  = Module::find($validated['module_id']);
        $chapter = Chapter::find($validated['chapter_id']);

        if (!$program || !$level || !$module || !$chapter) {
            return response()->json(['success' => false, 'message' => 'Invalid hierarchy'], 404);
        }

        if (
            $level->program_id != $program->id ||
            $module->level_id != $level->id ||
            $chapter->module_id != $module->id
        ) {
            return response()->json(['success' => false, 'message' => 'Invalid hierarchy mapping'], 422);
        }

        // upload
        if ($request->hasFile('thumbnail')) {
            if (!file_exists(public_path($this->uploadPath))) {
                mkdir(public_path($this->uploadPath), 0777, true);
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $topic = Topic::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        } else {

            $topic = Topic::create([
                'program_id' => $validated['program_id'],
                'level_id' => $validated['level_id'],
                'module_id' => $validated['module_id'],
                'chapter_id' => $validated['chapter_id'],
                'title' => 'BASE_RECORD',
                'description' => null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'estimated_duration' => $validated['estimated_duration'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $topic->translations()->create([
                'language_code' => $lang,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $topic
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $topic = Topic::with(['translations'])->findOrFail($id);

        if ($lang === 'en') {
            if ($topic->title === 'BASE_RECORD') {
                return response()->json(['success' => false, 'message' => 'English content not available'], 404);
            }

            return response()->json(['success' => true, 'data' => $topic]);
        }

        $translation = $topic->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {
            return response()->json(['success' => false, 'message' => 'Translation not available'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $topic->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $topic->thumbnail,
                'estimated_duration' => $topic->estimated_duration,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $topic = Topic::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($lang === 'en') {

            $topic->update($validated);
        } else {

            $topic->translations()->updateOrCreate(
                ['language_code' => $lang],
                $validated
            );
        }

        return response()->json(['success' => true, 'data' => $topic]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $topic = Topic::findOrFail($id);

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $topic = Topic::findOrFail($id);

        $topic->update([
            'status' => !$topic->status
        ]);

        return response()->json([
            'success' => true,
            'data' => $topic
        ]);
    }
}
