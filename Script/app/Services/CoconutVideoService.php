<?php

namespace App\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;

class CoconutVideoService
{
    public static function handle(Model $model, string $type)
    {
        switch ($type) {
            case 'post':
                $urlMedia = $model->video;
                $urlStorage = url('webhook/storage', $model->id);
                $urlWebhook = route('webhook.coco', ['mediaId' => $model->id, 'resourceId' => $model->updates_id]);

                self::job($model, $urlMedia, $urlStorage, $urlWebhook);
                break;

            case 'message':
                $urlMedia = $model->file;
                $urlStorage = url('webhook/storage/message', $model->id);
                $urlWebhook = route('webhook.message.coco', ['mediaId' => $model->id, 'resourceId' => $model->messages_id]);

                self::job($model, $urlMedia, $urlStorage, $urlWebhook);
                break;

            case 'welcomeMessage':
                $urlMedia = $model->file;
                $urlStorage = url('webhook/storage/welcome/message', $model->id);
                $urlWebhook = route('webhook.welcome.message.coco', ['mediaId' => $model->id]);

                self::job($model, $urlMedia, $urlStorage, $urlWebhook);
                break;

            case 'story':
                $urlMedia = $model->name;
                $urlStorage = url('webhook/storage/story', $model->id);
                $urlWebhook = route('webhook.story.coco', ['mediaId' => $model->id, 'resourceId' => $model->stories_id]);

                self::job($model, $urlMedia, $urlStorage, $urlWebhook);
                break;
        }
    }

    public static function job($model, $urlMedia, $urlStorage, $urlWebhook)
    {
        $coconut = new \Coconut\Client(config('settings.coconut_key'));
        $url = url('public/temp', $urlMedia);
        $videoName = strtolower(auth()->user()->username . '-' . auth()->id() . time());

        $coconut->notification = [
            'type' => 'http',
            'url' => $urlWebhook,
            'metadata' => true
        ];

        $coconut->storage = [
            'url' => $urlStorage
        ];

        if (self::getRegion()) {
            $coconut->region = self::getRegion();
        }

        try {
            $job = $coconut->job->create([
                'settings' => [
                    'ultrafast' => true
                ],
                'input' => ['url' => $url],
                'outputs' => [
                    'jpg' => [
                        'path' => "/thumbnail-{$videoName}.jpg",
                        'offsets' => [1]
                    ],

                    'mp4:::quality=5' => [
                        'path' => "/{$videoName}.mp4",
                        'watermark' => config('settings.watermark_on_videos') == 'on' ? [
                            'url' => url('public/img', config('settings.watermak_video')),
                            'position' => config('settings.watermark_position')
                        ] : false,
                    ]

                ]
            ]);

            $model->whereId($model->id)->update([
                'job_id' => $job->id
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function getRegion()
    {
        return match (config('settings.coconut_region')) {
            'Virginia' => false,
            'Oregon' => 'us-west-2',
            'Ireland' => 'eu-west-1',
        };
    }
}
