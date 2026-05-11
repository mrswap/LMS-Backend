<?php

namespace App\Modules\Admin\ContentManagement\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
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

        // 🔹 Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 🔹 Type Filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // 🔹 Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('shortcode', 'like', "%{$search}%")
                    ->orWhere('file', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        $orderBy = $request->input('order_by', 'created_at');

        $orderDir = strtolower(
            $request->input('order_dir', 'desc')
        ) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id',
            'title',
            'created_at'
        ];

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

        return response()->json(
            $query->paginate($limit)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(MediaRequest $request)
    {
        $filePath = null;

        if ($request->hasFile('file')) {

            $uploadPath = public_path(Media::UPLOAD_PATH);

            // Create directory if not exists
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $file = $request->file('file');

            $fileName = uniqid() . '.' .
                $file->getClientOriginalExtension();

            // Move file to public folder
            $file->move($uploadPath, $fileName);

            // Save relative path
            $filePath = Media::UPLOAD_PATH . '/' . $fileName;
        }

        $media = Media::create([
            'title'         => $request->title,
            'description'   => $request->description,
            'type'          => $request->type,
            'file'          => $filePath,
            'external_url'  => $request->external_url,
            'shortcode'     => $this->generateShortcode(),
            'disk'          => 'public',
            'created_by'    => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Media uploaded successfully',
            'data'    => $media
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

        /*
        |--------------------------------------------------------------------------
        | REPLACE FILE
        |--------------------------------------------------------------------------
        */
        if ($request->hasFile('file')) {

            // Delete old file
            $oldFilePath = public_path($media->file);

            if ($media->file && file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            $uploadPath = public_path(Media::UPLOAD_PATH);

            // Create directory if not exists
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $file = $request->file('file');

            $fileName = uniqid() . '.' .
                $file->getClientOriginalExtension();

            // Move new file
            $file->move($uploadPath, $fileName);

            // Save new path
            $media->file = Media::UPLOAD_PATH . '/' . $fileName;
            $media->disk = 'public';

            $media->save();
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE OTHER FIELDS
        |--------------------------------------------------------------------------
        */
        $data = $request->only([
            'title',
            'description',
            'type',
            'external_url'
        ]);

        $media->update(
            array_filter($data, function ($value) {
                return !is_null($value);
            })
        );

        return response()->json([
            'message' => 'Media updated successfully',
            'data'    => $media->fresh()
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

        // Delete physical file
        $filePath = public_path($media->file);

        if ($media->file && file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete DB record
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
            'message' => 'Status updated successfully',
            'status'  => $media->status
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
