<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaStories;
use Illuminate\Http\Request;
use App\Models\MediaMessages;
use Illuminate\Http\Response;
use App\Models\MediaWelcomeMessage;
use App\Services\CoconutUploadService;

final class StorageCoconutController extends Controller
{
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function storage(): Response
    {
        $media = Media::select(['id', 'video'])->whereId($this->request->id)->first();
        $this->upload($media, 'video', $media->video, config('path.videos'), $this->request);

        return response('success', 200);
    }

    public function storageMessage(): Response
    {
        $media = MediaMessages::select(['id', 'file'])->whereId($this->request->id)->first();
        $this->upload($media, 'file', $media->file, config('path.messages'), $this->request);

        return response('success', 200);
    }

    public function storageStory(): Response
    {
        $media = MediaStories::select(['id', 'name'])->whereId($this->request->id)->first();
        $this->upload($media, 'name', $media->name, config('path.stories'), $this->request);

        return response('success', 200);
    }

    public function storageWelcomeMessage(): Response
    {
        $media = MediaWelcomeMessage::select(['id', 'file'])->whereId($this->request->id)->first();
        $this->upload($media, 'file', $media->file, config('path.welcome_messages'), $this->request);

        return response('success', 200);
    }

    protected function upload($media, $field, $filePath, $storage, $request): void
    {
        CoconutUploadService::video($media, $field, $filePath, $storage, $request);
        CoconutUploadService::poster($media, $storage, $request);
    }
}
