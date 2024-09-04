<?php

namespace App\Http\Controllers;

use App\Helper;
use App\Models\Media;
use App\Models\Stories;
use App\Models\Updates;
use App\Models\Messages;
use App\Events\NewPostEvent;
use App\Models\MediaStories;
use Illuminate\Http\Request;
use App\Models\MediaMessages;
use App\Models\MediaWelcomeMessage;
use App\Models\Notifications;
use Illuminate\Support\Facades\Storage;

final class WebhookCoconutController extends Controller
{
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function webhook()
    {
        $body = file_get_contents("php://input");
        $webhook = json_decode($body, true);

        $post = Updates::whereId($this->request->resourceId)->where('status', '!=', 'active')->first();

        if (!$post) {
            return;
        }

        // Status final post
        $statusPost = $post->schedule ? 'schedule' : 'active';

        if ($webhook['event'] == 'job.completed') {
            $duration = (int) $webhook['data']['input']['metadata']['streams'][0]['duration'] ?? null;
            $width = $webhook['data']['input']['metadata']['streams'][0]['width'] ?? null;

            if ($duration) {
                $durationVideo = round($duration, 0, PHP_ROUND_HALF_DOWN);
            }

            // Update name video on Media table
            Media::whereId($this->request->mediaId)->update([
                'encoded' => 'yes',
                'duration_video' => $durationVideo ? Helper::getDurationInMinutes($durationVideo) : null,
                'quality_video' => $width ? Helper::getResolutionVideo($width) : null
            ]);

            // Check if there are other videos that have not been encoded
            $videos = Media::whereUpdatesId($this->request->resourceId)
                ->whereType('video')
                ->whereEncoded('no')
                ->get();

            if ($videos->count() == 0) {
                // Update date the post and status
                $post->update([
                    'date' => now(),
                    'status' => config('settings.auto_approve_post') == 'on' ? $statusPost : 'pending'
                ]);

                // Notify to user - destination, author, type, target
                Notifications::send($post->user_id, $post->user_id, 9, $post->id);

                // Send notification via Email
                $this->newPostEvent($post);
            }
        } else {
            $post->update([
                'date' => now(),
                'status' => config('settings.auto_approve_post') == 'on' ? $statusPost : 'pending'
            ]);

            // Send notification via Email
            $this->newPostEvent($post);

            // Delete Media
            $mediaError = Media::find($this->request->mediaId);

            // Delete file
            $this->deleteFile($mediaError->video);

            $mediaError->delete();

            // Notify to user (ERROR) - destination, author, type, target
            Notifications::send($post->user_id, $post->user_id, 20, $post->id);
        }
    }

    public function webhookMessage()
    {
        $body = file_get_contents("php://input");
        $webhook = json_decode($body, true);

        if ($webhook['event'] == 'job.completed') {
            $duration = (int) $webhook['data']['input']['metadata']['streams'][0]['duration'] ?? null;
            $width = $webhook['data']['input']['metadata']['streams'][0]['width'] ?? null;

            if ($duration) {
                $durationVideo = round($duration, 0, PHP_ROUND_HALF_DOWN);
            }

            $message = Messages::whereId($this->request->resourceId)->where('mode', '!=', 'active')->first();

            if (!$message) {
                return;
            }

            // Update name video on Media table
            MediaMessages::whereId($this->request->mediaId)->update([
                'encoded' => 'yes',
                'duration_video' => $durationVideo ? Helper::getDurationInMinutes($durationVideo) : null,
                'quality_video' => $width ? Helper::getResolutionVideo($width) : null
            ]);

            // Check if there are other videos that have not been encoded
            $videos = MediaMessages::whereMessagesId($this->request->resourceId)
                ->whereType('video')
                ->whereEncoded('no')
                ->get();

            if ($videos->count() == 0) {
                // Update date the post and status
                $message->update([
                    'created_at' => now(),
                    'updated_at' => now(),
                    'mode' => 'active'
                ]);

                // Notify to user - destination, author, type, target
                Notifications::send($message->user()->id, $message->user()->id, 10, $message->id);
            }
        } else {
            $message->update([
                'created_at' => now(),
                'updated_at' => now(),
                'mode' => 'active'
            ]);

            // Delete Media
            $mediaError = MediaMessages::find($this->request->mediaId);

            // Delete file
            $this->deleteFile($mediaError->file);

            $mediaError->delete();

            // Notify to user (ERROR) - destination, author, type, target
            Notifications::send($message->user()->id, $message->user()->id, 21, $message->id);
        }
    }

    public function webhookStory()
    {
        $body = file_get_contents("php://input");
        $webhook = json_decode($body, true);

        if ($webhook['event'] == 'job.completed') {
            $duration = (int) $webhook['data']['input']['metadata']['streams'][0]['duration'] ?? null;

            if ($duration) {
                $durationVideo = round($duration, 0, PHP_ROUND_HALF_DOWN);
            }

            $story = Stories::with(['user'])->whereId($this->request->resourceId)->where('status', '!=', 'active')->first();

            if (!$story) {
                return;
            }

            // Update name video on Media table
            MediaStories::whereId($this->request->mediaId)->update([
                'video_length' => $durationVideo ? $durationVideo : null,
                'status' => true,
            ]);

            // Update date the story and status
            $story->update([
                'created_at' => now(),
                'status' => 'active'
            ]);

            // Notify to user - destination, author, type, target
            Notifications::send($story->user->id, $story->user->id, 17, 0);
        } else {
            $story->delete();

            // Delete Media
            $mediaError = MediaStories::find($this->request->mediaId);

            // Delete file
            $this->deleteFile($mediaError->name);

            $mediaError->delete();

            // Notify to user (ERROR) - destination, author, type, target
            Notifications::send($story->user->id, $story->user->id, 22, 0);
        }
    }

    public function webhookWelcomeMessage()
    {
        $body = file_get_contents("php://input");
        $webhook = json_decode($body, true);

        if ($webhook['event'] == 'job.completed') {
            $duration = (int) $webhook['data']['input']['metadata']['streams'][0]['duration'] ?? null;
            $width = $webhook['data']['input']['metadata']['streams'][0]['width'] ?? null;

            if ($duration) {
                $durationVideo = round($duration, 0, PHP_ROUND_HALF_DOWN);
            }

            $message = MediaWelcomeMessage::with(['creator:id'])->whereId($this->request->mediaId)->first();

            // Update name video on Media table
            MediaWelcomeMessage::whereId($this->request->mediaId)->update([
                'encoded' => 'yes',
                'status' => 'active',
                'duration_video' => $durationVideo ? Helper::getDurationInMinutes($durationVideo) : null,
                'quality_video' => $width ? Helper::getResolutionVideo($width) : null
            ]);

            // Notify to user - destination, author, type, target
            Notifications::send($message->creator->id, $message->creator->id, 24, $message->id);
        } else {

            // Delete Media
            $mediaError = MediaWelcomeMessage::find($this->request->mediaId);

            // Delete file
            $this->deleteFile($mediaError->file);

            $mediaError->delete();

            // Notify to user (ERROR) - destination, author, type, target
            Notifications::send($message->creator->id, $message->creator->id, 25, $message->id);
        }
    }

    protected function newPostEvent($post)
    {
        if (!config('settings.disable_new_post_notification')) {
            event(new NewPostEvent($post));
        }
    }

    protected function deleteFile($file): void
    {
        $localFile = 'temp/' . $file;
        Storage::disk('default')->delete($localFile);
    }
}
