<?php

namespace App\Events;

use App\Models\SupportMessage;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SupportMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public SupportMessage $message;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message->load([
            'sender',
        ]);

        Log::channel('ai')->info(
            'Broadcasting Support Message',
            [
                'message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'is_ai' => $message->is_ai,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | CHANNEL
    |--------------------------------------------------------------------------
    */

    public function broadcastOn(): array
    {
        return [

            new PrivateChannel(
                'support.thread.' . $this->message->thread_id
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | EVENT NAME
    |--------------------------------------------------------------------------
    */

    public function broadcastAs(): string
    {
        return 'support.message.sent';
    }

    /*
    |--------------------------------------------------------------------------
    | PAYLOAD
    |--------------------------------------------------------------------------
    */

    public function broadcastWith(): array
    {
        return [

            'message' => $this->message,
        ];
    }
}
