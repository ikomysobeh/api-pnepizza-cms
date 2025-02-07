<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Gregwar\Image\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;


class MediaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/media",
     *     summary="Get paginated list of media files",
     *     tags={"Media"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(response=200, description="Paginated list of media files"),
     *     @OA\Header(
     *         header="Accept",
     *         description="application/json only",
     *         @OA\Schema(type="string", example="application/json")
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 10);
            $media = Media::paginate($perPage);
            return response()->json($media);
        } catch (\Exception $exception) {
            return response()->json([
                'msg' => $exception->getMessage(),
            ], 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/v1/media",
     *     summary="Upload a media file",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"user_id", "file"},
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Media uploaded successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Header(
     *         header="Accept",
     *         description="application/json only",
     *         @OA\Schema(type="string", example="application/json")
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'file' => 'required|file|mimes:jpg,jpeg,png,gif',
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('uploads', $fileName, 'public');
        $thumbnailPath = 'uploads/thumbnails/thumb_' . $fileName;

        Storage::disk('public')->makeDirectory('uploads/thumbnails');

        Image::open($file->getPathname())
            ->resize(200, 200)
            ->save(public_path('storage/' . $thumbnailPath));

        $media = Media::create([
            'user_id' => $request->user_id,
            'file_name' => $fileName,
            'file_path' => '/storage/' . $filePath,
            'thumbnail_path' => '/storage/' . $thumbnailPath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json($media, 201);

        } catch (\Exception $exception) {
            return response()->json([
                'msg' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/media/{id}",
     *     summary="Get media details",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Media ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Media details"),
     *     @OA\Response(response=404, description="Media not found"),
     *     @OA\Header(
     *         header="Accept",
     *         description="application/json only",
     *         @OA\Schema(type="string", example="application/json")
     *     )
     * )
     */
    public function show($id)
    {
        try {
        return response()->json(Media::findOrFail($id));

        } catch (\Exception $exception) {
            return response()->json([
                'msg' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/media/{id}",
     *     summary="Update a media file",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Media ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Media updated successfully"),
     *     @OA\Response(response=404, description="Media not found"),
     *     @OA\Header(
     *         header="Accept",
     *         description="application/json only",
     *         @OA\Schema(type="string", example="application/json")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {

        $media = Media::findOrFail($id);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $data = ['user_id' => $request->user_id];

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete([
                str_replace('/storage/', '', $media->file_path),
                str_replace('/storage/', '', $media->thumbnail_path)
            ]);

            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads', $fileName, 'public');
            $thumbnailPath = 'uploads/thumbnails/thumb_' . $fileName;

            Storage::disk('public')->makeDirectory('uploads/thumbnails');

            Image::open($file->getPathname())
                ->resize(150, 150)
                ->save(public_path('storage/' . $thumbnailPath));

            $data['file_name'] = $fileName;
            $data['file_path'] = '/storage/' . $filePath;
            $data['thumbnail_path'] = '/storage/' . $thumbnailPath;
            $data['file_size'] = $file->getSize();
            $data['mime_type'] = $file->getMimeType();
        }

        $media->update($data);

        return response()->json($media);

        } catch (\Exception $exception) {
            return response()->json([
                'msg' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/media/{id}",
     *     summary="Delete a media file",
     *     tags={"Media"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Media ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Media deleted"),
     *     @OA\Response(response=404, description="Media not found"),
     *     @OA\Header(
     *         header="Accept",
     *         description="application/json only",
     *         @OA\Schema(type="string", example="application/json")
     *     )
     * )
     */
    public function destroy($id)
    {
        try {

        Media::findOrFail($id)->delete();
        return response()->json(null, 204);

        } catch (\Exception $exception) {
            return response()->json([
                'msg' => $exception->getMessage(),
            ]);
        }
    }
}
