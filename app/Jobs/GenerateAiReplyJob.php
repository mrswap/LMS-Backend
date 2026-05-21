<?php

namespace App\Jobs;

use App\Models\SupportThread;
use App\Services\AI\AiSupportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAiReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $threadId;
    public int $tries = 3;
    public int $timeout = 180;


    public function __construct($threadId)
    {
        $this->threadId = $threadId;
    }

    public function handle(): void
    {
        try {

            Log::channel('ai')->info(
                'AI Job Started',
                [
                    'thread_id' => $this->threadId,
                ]
            );

            $thread = SupportThread::find(
                $this->threadId
            );

            if (! $thread) {

                Log::channel('ai')->warning(
                    'Thread Not Found',
                    [
                        'thread_id' => $this->threadId,
                    ]
                );

                return;
            }

            app(AiSupportService::class)
                ->generateReply($thread);
        } catch (\Throwable $e) {

            Log::channel('ai')->error(
                'AI Job Error',
                [
                    'thread_id' => $this->threadId,
                    'message' => $e->getMessage(),
                ]
            );
        }
    }
}
