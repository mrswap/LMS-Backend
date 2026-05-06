<?php

namespace App\Modules\Admin\Program\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProgramController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/programs/';

    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $programs = Program::with(['creator:id,name', 'translations'])
            ->when($lang === 'en', function ($q) {
                $q->where('title', '!=', 'BASE_RECORD');
            })
            ->latest()
            ->paginate(10);

        $programs->getCollection()->transform(function ($program) use ($lang) {

            if ($lang === 'en') {
                return [
                    'id' => $program->id,
                    'language_code' => 'en',
                    'title' => $program->title,
                    'description' => $program->description,
                    'thumbnail' => $program->thumbnail,
                    'status' => (bool) $program->status,
                    'publish_status' => $program->publish_status,
                    'creator' => $program->creator,
                    'created_at' => $program->created_at,
                ];
            }

            $translation = $program->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $program->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $program->thumbnail,
                'status' => (bool) $program->status,
                'publish_status' => $program->publish_status,
                'creator' => $program->creator,
                'created_at' => $program->created_at,
            ];
        });

        $programs->setCollection(
            $programs->getCollection()->filter()->values()
        );

        return response()->json([
            'success' => true,
            'data' => $programs
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

            $program = Program::create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'created_by' => auth()->id(),
            ]);
        } else {

            $program = Program::create([
                'title' => 'BASE_RECORD',
                'description' => null,
                'thumbnail' => $validated['thumbnail'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $program->translations()->create([
                'language_code' => $lang,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);
        }

        $program->load('creator:id,name');

        return response()->json([
            'success' => true,
            'data' => $program
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

        $program = Program::with(['creator:id,name', 'translations'])
            ->findOrFail($id);

        if ($lang === 'en') {

            if ($program->title === 'BASE_RECORD') {
                return response()->json([
                    'success' => false,
                    'message' => 'English content not available'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $program->id,
                    'language_code' => 'en',
                    'title' => $program->title,
                    'description' => $program->description,
                    'thumbnail' => $program->thumbnail,
                    'status' => (bool) $program->status,
                    'publish_status' => $program->publish_status,
                    'creator' => $program->creator,
                    'created_at' => $program->created_at,
                ]
            ]);
        }

        $translation = $program->translations
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
                'id' => $program->id,
                'translation_id' => $translation->id,
                'language_code' => $lang,
                'title' => $translation->title,
                'description' => $translation->description,
                'thumbnail' => $program->thumbnail,
                'status' => (bool) $program->status,
                'publish_status' => $program->publish_status,
                'creator' => $program->creator,
                'created_at' => $program->created_at,
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

        $program = Program::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {

            if ($program->thumbnail && file_exists(public_path($program->thumbnail))) {
                unlink(public_path($program->thumbnail));
            }

            $file = $request->file('thumbnail');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['thumbnail'] = $this->uploadPath . $filename;
        }

        if ($lang === 'en') {

            $program->update([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'thumbnail' => $validated['thumbnail'] ?? $program->thumbnail,
            ]);
        } else {

            $program->translations()->updateOrCreate(
                ['language_code' => $lang],
                [
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $program
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $program = Program::findOrFail($id);

        if ($program->thumbnail && file_exists(public_path($program->thumbnail))) {
            unlink(public_path($program->thumbnail));
        }

        $program->delete();

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
        $program = Program::findOrFail($id);

        $program->update([
            'status' => !$program->status
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $program->id,
                'status' => (bool) $program->status,
                'publish_status' => $program->publish_status,

            ]
        ]);
    }
}
