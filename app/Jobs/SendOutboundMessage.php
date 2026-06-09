<?php

namespace App\Jobs;

use App\Channels\ChannelManager;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends an outbound message via the channel connector and reconciles delivery
 * status (M2 / C-06). Queued so the request cycle stays light (§3).
 */
class SendOutboundMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId, public string $channel, public string $to) {}

    public function handle(ChannelManager $channels): void
    {
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }

        try {
            $channels->for($this->channel)->send($this->to, [
                'type' => 'text',
                'text' => ['body' => $message->body],
            ]);

            $message->update(['status' => 'sent']);
        } catch (Throwable $e) {
            $message->update(['status' => 'failed']);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Message::where('id', $this->messageId)->update(['status' => 'failed']);
    }
}
