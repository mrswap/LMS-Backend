<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Modules\Admin\ContentManagement\Requests\MediaRequest;

class MediaController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST + FILTER
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query = Media::query();

        // 🔹 Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 🔹 Type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // 🔹 Search (title, shortcode, file)
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('shortcode', 'like', "%$search%")
                    ->orWhere('file', 'like', "%$search%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        $orderBy = $request->input('order_by', 'created_at');
        $orderDir = strtolower($request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['id', 'title', 'created_at'];

        if (!in_array($orderBy, $allowedSorts)) {
            $orderBy = 'created_at';
        }

        $query->orderBy($orderBy, $orderDir);

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $limit = (int) $request->input('limit', 10);
        $limit = $limit > 100 ? 100 : $limit;

        return response()->json($query->paginate($limit));
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(MediaRequest $request)
    {
        $filePath = null;
        $disk = config('filesystems.default'); // future S3 ready

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')
                ->store(Media::UPLOAD_PATH, $disk);
        }

        $media = Media::create([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'file' => $filePath,
            'external_url' => $request->external_url,
            'shortcode' => $this->generateShortcode(),
            'disk' => $disk,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Media uploaded successfully',
            'data' => $media
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        return response()->json(
            Media::findOrFail($id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(MediaRequest $request, $id)
    {
        $media = Media::findOrFail($id);
        $disk = config('filesystems.default');

        // 🔹 Replace file if uploaded
        if ($request->hasFile('file')) {

            if ($media->file && Storage::disk($media->disk)->exists($media->file)) {
                Storage::disk($media->disk)->delete($media->file);
            }

            $filePath = $request->file('file')
                ->store(Media::UPLOAD_PATH, $disk);

            $media->file = $filePath;
            $media->disk = $disk;
        }

        // 🔹 Only update provided fields
        $data = $request->only([
            'title',
            'description',
            'type',
            'external_url'
        ]);

        $media->update(array_filter($data, function ($value) {
            return !is_null($value);
        }));

        return response()->json([
            'message' => 'Media updated successfully',
            'data' => $media
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $media = Media::findOrFail($id);

        if ($media->file && Storage::disk($media->disk)->exists($media->file)) {
            Storage::disk($media->disk)->delete($media->file);
        }

        $media->delete();

        return response()->json([
            'message' => 'Media deleted successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TOGGLE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleStatus($id)
    {
        $media = Media::findOrFail($id);

        $media->status = !$media->status;
        $media->save();

        return response()->json([
            'message' => 'Status updated successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHORTCODE GENERATOR
    |--------------------------------------------------------------------------
    */
    private function generateShortcode()
    {
        return '[media_' . uniqid() . ']';
    }
}
