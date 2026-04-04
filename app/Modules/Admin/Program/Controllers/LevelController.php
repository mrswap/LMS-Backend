<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\LevelTranslation;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LevelController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/levels/';

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

        $query = Level::with([
            'creator:id,name',
            'program:id,title',
            'translations'
        ]);

        /*
        |-----------------------------
        | FILTER: PROGRAM
        |-----------------------------
        */
        if ($request->filled('program_id')) {

            if (!Program::find($request->program_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Program not found'
                ], 404);
            }

            $query->where('program_id', $request->program_id);
        }

        /*
        |-----------------------------
        | SEARCH
        |-----------------------------
        */
        if ($request->filled('search')) {

            $search = $request->search;

            if ($lang === 'en') {

                $query->where('title', 'like', "%{$search}%")
                    ->where('title', '!=', 'BASE_RECORD');
            } else {

                $query->whereHas('translations', function ($q) use ($lang, $search) {
                    $q->where('language_code', $lang)
                        ->where('title', 'like', "%{$search}%");
                });
            }
        }

        /*
        |-----------------------------
        | BASE RECORD FILTER
        |-----------------------------
        */
        if ($lang === 'en' && !$request->filled('search')) {
            $query->where('title', '!=', 'BASE_RECORD');
        }

        /*
        |-----------------------------
        | STATUS
        |-----------------------------
        */
        if ($request->has('status')) {
            if ($request->status !== 'all') {
                $query->where('status', (bool) $request->status);
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
        $limit = (int) $request->get('limit', 10);
        $limit = ($limit > 0 && $limit <= 100) ? $limit : 10;

        $levels = $query->paginate($limit);

        /*
        |-----------------------------
        | TRANSFORM
        |-----------------------------
        */
        $levels->getCollection()->transform(function ($level) use ($lang) {

            if ($lang === 'en') {
                return [
                    'id' => $level->id,
                    'language_code' => 'en',
                    'title' => $level->title,
                    'description' => $level->description,
                    'thumbnail' => $level->thumbnail,
                    'status' => (bool) $level->status,
                    'program' => $level->program,
                    'creator' => $level->creator,
                    'created_at' => $level->created_at,
                ];
            }

            $translation = $level->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $level->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $level->thumbnail,
                'status' => (bool) $level->status,
                'program' => $level->program,
                'creator' => $level->creator,
                'created_at' => $level->created_at,
            ];
        });

        $levels->setCollection(
            $levels->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $levels
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
            'program_id' => 'required|exists:programs,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);
            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $level = Level::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);
        } else {

            $level = Level::create([
                'program_id' => $validated['program_id'],
                'title' => 'BASE_RECORD',
                'description' => null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'created_by' => auth()->id(),
            ]);

            LevelTranslation::create([
                'level_id' => $level->id,
                'language_code' => $lang,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        $level->load(['creator:id,name', 'program:id,title']);

        return response()->json([
            'success' => true,
            'data' => $level
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

        $level = Level::with(['creator:id,name', 'program:id,title', 'translations'])
            ->findOrFail($id);

        if ($lang === 'en') {

            if ($level->title === 'BASE_RECORD') {
                return response()->json([
                    'success' => false,
                    'message' => 'English content not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $level->id,
                    'language_code' => 'en',
                    'title' => $level->title,
                    'description' => $level->description,
                    'thumbnail' => $level->thumbnail,
                    'status' => (bool) $level->status,
                    'program' => $level->program,
                    'creator' => $level->creator,
                ]
            ]);
        }

        $translation = $level->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not available'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $level->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $level->thumbnail,
                'status' => (bool) $level->status,
                'program' => $level->program,
                'creator' => $level->creator,
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

        $level = Level::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            if ($level->thumbnail && file_exists(public_path($level->getRawOriginal('thumbnail')))) {
                unlink(public_path($level->getRawOriginal('thumbnail')));
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $level->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? $level->thumbnail,
            ]);
        } else {

            $level->translations()->updateOrCreate(
                ['language_code' => $lang],
                [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $level
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $level = Level::findOrFail($id);

        if ($level->thumbnail && file_exists(public_path($level->getRawOriginal('thumbnail')))) {
            unlink(public_path($level->getRawOriginal('thumbnail')));
        }

        $level->delete();

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
        $level = Level::findOrFail($id);

        $level->update([
            'status' => !$level->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $level->id,
                'status' => (bool) $level->status
            ]
        ]);
    }
}
