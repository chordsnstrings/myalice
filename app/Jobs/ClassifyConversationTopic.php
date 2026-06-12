<?php

namespace App\Jobs;

use App\Ai\TopicClassifier;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Auto-tag a conversation's topic with one cheap LLM pass. tries=1 — a failed
 * classification simply leaves the chat untagged. SiteGround-safe queue job.
 */
class ClassifyConversationTopic implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $workspaceId, public int $conversationId) {}

    public function handle(TopicClassifier $classifier): void
    {
        $workspace = Workspace::find($this->workspaceId);
        if (! $workspace) {
            return;
        }

        Tenancy::set($workspace);

        try {
            $conversation = Conversation::find($this->conversationId);
            if ($conversation) {
                $classifier->classify($conversation);
            }
        } finally {
            Tenancy::clear();
        }
    }
}
