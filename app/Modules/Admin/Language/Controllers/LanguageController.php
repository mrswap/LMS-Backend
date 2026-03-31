<?php

namespace App\Modules\Admin\Language\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Language;

class LanguageController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET ALL
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Language::latest()->get()
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:10|unique:languages,code',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'translation_file' => 'nullable|file|mimes:json|max:2048',
        ]);

        // Handle default language
        if ($request->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $code = strtolower($request->code);
        $filePath = null;

        // 🔹 File Upload
        if ($request->hasFile('translation_file')) {

            $file = $request->file('translation_file');
            $filename = $code . '.json';

            $directory = public_path('language');

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $file->move($directory, $filename);

            $filePath = 'language/' . $filename;
        }

        $language = Language::create([
            'name' => $request->name,
            'code' => $code,
            'is_default' => $request->is_default ?? false,
            'is_active' => $request->is_active ?? true,
            'translation_file' => $filePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Language created successfully',
            'data' => $language
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $language = Language::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $language
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $language = Language::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:100',
            'code' => "sometimes|string|max:10|unique:languages,code,$id",
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'translation_file' => 'nullable|file|mimes:json|max:2048',
        ]);

        // Handle default
        if ($request->is_default) {
            Language::where('is_default', true)->update(['is_default' => false]);
        }

        $code = isset($request->code)
            ? strtolower($request->code)
            : $language->code;

        $filePath = $language->translation_file;

        // 🔹 File Upload / Replace
        if ($request->hasFile('translation_file')) {

            $file = $request->file('translation_file');
            $filename = $code . '.json';

            $directory = public_path('language');

            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $file->move($directory, $filename);

            $filePath = 'language/' . $filename;
        }

        $language->update([
            'name' => $request->name ?? $language->name,
            'code' => $code,
            'is_default' => $request->is_default ?? $language->is_default,
            'is_active' => $request->is_active ?? $language->is_active,
            'translation_file' => $filePath,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully',
            'data' => $language
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $language = Language::findOrFail($id);

        if ($language->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Default language cannot be deleted'
            ], 422);
        }

        // 🔹 Delete file if exists
        if ($language->translation_file && file_exists(public_path($language->translation_file))) {
            unlink(public_path($language->translation_file));
        }

        $language->delete();

        return response()->json([
            'success' => true,
            'message' => 'Language deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $language = Language::findOrFail($id);

        $language->is_active = !$language->is_active;
        $language->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated',
            'data' => $language
        ]);
    }
}
