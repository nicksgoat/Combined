<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CoconutUploadService
{
    public static function video(Model $media, $field, $filePath, $storage, $request)
    {
        // Video Upload
        if ($request->hasFile('encoded_video') && $request->video_id) {
            $file = $request->file('encoded_video');
            $extension = $request->file('encoded_video')->getClientOriginalExtension();
            $fileName = 'converted-' . str_random(20) . uniqid() . now()->timestamp;
            $fileUpload = $fileName . '.' . $extension;

            // Video folder temp
            $videoPathDisk = 'temp/' . $filePath;

            $media->update([
                $field => $fileUpload,
            ]);

            $file->storePubliclyAs($storage, $fileUpload, config('filesystems.default'));

            // Delete old video
            Storage::disk('default')->delete($videoPathDisk);
        }
    }

    public static function poster(Model $media, $storage, $request)
    {
        if ($request->hasFile('encoded_video') && !$request->video_id) {
            $file = $request->file('encoded_video');
            $extension = $request->file('encoded_video')->getClientOriginalExtension();
            $fileName = 'poster-' . str_random(20) . uniqid() . now()->timestamp;
            $fileUpload = $fileName . '.' . $extension;

            $media->update([
                'video_poster' => $fileUpload,
            ]);

            $file->storePubliclyAs($storage, $fileUpload, config('filesystems.default'));
        }
    }
}
