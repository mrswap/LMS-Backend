<?php

namespace App\Modules\Admin\FAQ\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\Level;
use App\Models\Module;
use App\Models\Chapter;
use App\Models\Topic;
use Illuminate\Support\Str;

class FaqController extends Controller
{
    protected $uploadPath = 'uploads/curriculum/faq/';

    private function resolveLanguage(Request $request)
    {
        return $request->query('lang')
            ?? $request->header('Accept-Language')
            ?? 'en';
    }

    private function resolveModel($type)
    {
        return [
            'level'   => Level::class,
            'module'  => Module::class,
            'chapter' => Chapter::class,
            'topic'   => Topic::class,
        ][$type] ?? null;
    }

    /*
    |-------------------------------------------------------------
    | INDEX
    |-------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $request->validate([
            'type' => 'required|in:level,module,chapter,topic',
            'id'   => 'required|integer'
        ]);

        $modelClass = $this->resolveModel($request->type);
        $model = $modelClass::find($request->id);

        if (!$model) {
            return response()->json(['success' => false], 404);
        }

        $faqs = $model->faqs()->with('translations')->get();

        $data = $faqs->map(function ($faq) use ($lang) {

            $translation = $faq->translations
                ->where('language_code', $lang)
                ->first();

            if (!$translation) return null;

            return [
                'id' => $faq->id,
                'question' => $translation->question,
                'answer' => $translation->answer,
                'image' => $faq->image,
                'status' => $faq->status,
            ];
        })->filter()->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /*
    |-------------------------------------------------------------
    | STORE
    |-------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $lang = $this->resolveLanguage($request);

        $validated = $request->validate([
            'type' => 'required|in:level,module,chapter,topic',
            'id'   => 'required|integer',
            'question' => 'required|string',
            'answer'   => 'required|string',
            'image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $modelClass = $this->resolveModel($validated['type']);
        $model = $modelClass::find($validated['id']);

        if (!$model) {
            return response()->json(['success' => false], 404);
        }

        // 🔥 Upload Image
        if ($request->hasFile('image')) {

            if (!file_exists(public_path($this->uploadPath))) {
                mkdir(public_path($this->uploadPath), 0777, true);
            }

            $file = $request->file('image');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['image'] = $this->uploadPath . $filename;
        }

        $faq = Faq::create([
            'faqable_id'   => $model->id,
            'faqable_type' => $modelClass,
            'image'        => $validated['image'] ?? null,
            'created_by'   => auth()->id(),
        ]);

        $faq->translations()->create([
            'language_code' => $lang,
            'question' => $validated['question'],
            'answer'   => $validated['answer'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $faq->load('translations')
        ]);
    }

    /*
    |-------------------------------------------------------------
    | UPDATE
    |-------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $faq = Faq::with('translations')->findOrFail($id);

        $validated = $request->validate([
            'question' => 'required|string',
            'answer'   => 'required|string',
            'image'    => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'status'   => 'nullable|boolean',
        ]);

        // 🔥 Upload new image
        if ($request->hasFile('image')) {

            if (!file_exists(public_path($this->uploadPath))) {
                mkdir(public_path($this->uploadPath), 0777, true);
            }

            $file = $request->file('image');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($this->uploadPath), $filename);

            $validated['image'] = $this->uploadPath . $filename;
        }

        $faq->update([
            'image'  => $validated['image'] ?? $faq->image,
            'status' => $validated['status'] ?? $faq->status,
        ]);

        $faq->translations()->updateOrCreate(
            ['language_code' => $lang],
            [
                'question' => $validated['question'],
                'answer'   => $validated['answer'],
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $faq->load('translations')
        ]);
    }

    /*
    |-------------------------------------------------------------
    | DELETE
    |-------------------------------------------------------------
    */
    public function destroy($id)
    {
        Faq::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    /*
    |-------------------------------------------------------------
    | TOGGLE STATUS
    |-------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $faq = Faq::findOrFail($id);

        $faq->update(['status' => !$faq->status]);

        return response()->json(['success' => true, 'data' => $faq]);
    }


    /*
|-------------------------------------------------------------
| SHOW
|-------------------------------------------------------------
*/
    public function show(Request $request, $id)
    {
        $lang = $this->resolveLanguage($request);

        $faq = Faq::with('translations')->findOrFail($id);

        $translation = $faq->translations
            ->where('language_code', $lang)
            ->first();

        if (!$translation) {
            return response()->json([
                'success' => false,
                'message' => 'Translation not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $faq->id,
                'question' => $translation->question,
                'answer' => $translation->answer,
                'image' => $faq->image,
                'status' => $faq->status,
                'created_at' => $faq->created_at,
            ]
        ]);
    }
}
