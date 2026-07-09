<?php

namespace App\Modules\Admin\Import\Jobs;

use App\Models\TopicContent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class CleanupEmptyTopicContentsJob implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function handle(): void {
        Log::info('CleanupEmptyTopicContentsJob Started');

        TopicContent::query()
            ->get()
            ->filter(function ($content) {
                return trim(strip_tags($content->content ?? '')) === '';
            })
            ->each(function ($content) {

                Log::info('Deleting TopicContent', [
                    'id' => $content->id,
                    'content' => $content->content,
                ]);

                $content->delete();
            });

        Log::info('CleanupEmptyTopicContentsJob Finished');
    }
}
