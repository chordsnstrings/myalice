<?php

namespace App\Http\Controllers;

use App\Channels\ChannelManager;
use App\Events\MessageCreated;
use App\Jobs\SendOutboundMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\QuickReply;
use App\Models\Tag;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * The unified inbox (B3). Tenant-scoped conversations + messages render the
     * 3-pane workspace. Live channel ingestion lands in Phase 3.
     */
    public function index(): Response
    {
        $agents = User::where('workspace_id', Tenancy::id())->orderBy('name')->get(['id', 'name']);
        $names = $agents->pluck('name', 'id');

        $conversations = Conversation::with(['contact', 'tags:id,name,color'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'contact' => [
                    'id' => $c->contact->id,
                    'name' => $c->contact->name,
                    'channel' => $c->contact->channel,
                    'lifecycle' => $c->contact->lifecycle_stage,
                ],
                'last_message' => $c->last_message,
                'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                'unread' => $c->unread,
                'channel' => $c->channel,
                'status' => $c->status,
                'assignee' => $c->assignee_id ? ['id' => $c->assignee_id, 'name' => $names[$c->assignee_id] ?? 'Agent'] : null,
                'sla_breaching' => $c->sla_breaching,
                'window_open' => $c->window_open,
                'ai_status' => $c->ai_status,
                'tags' => $c->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()->all(),
            ])->values();

        $messages = Message::orderBy('sent_at')
            ->get()
            ->groupBy('conversation_id')
            ->map(fn ($group) => $group->map(fn (Message $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'author' => $m->author,
                'body' => $m->body,
                'sent_at' => $m->sent_at->toIso8601String(),
                'status' => $m->status,
            ])->values()->all());

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'messages' => $messages,
            'agents' => $agents->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'templates' => MessageTemplate::where('approval_status', 'approved')
                ->orderBy('name')->get(['id', 'name', 'body'])->all(),
            'quickReplies' => QuickReply::orderBy('shortcut')->get(['id', 'shortcut', 'body'])->all(),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color'])->all(),
        ]);
    }

    /**
     * Send a human agent reply. Inside the 24h window free text is allowed; outside
     * it, WhatsApp only permits an approved template, so free text is blocked.
     */
    public function reply(Request $request, Conversation $conversation): JsonResponse
    {
        // Validate explicitly and return JSON 422 — this is an AJAX endpoint, and the
        // app only auto-renders JSON exceptions for api/* routes (bootstrap/app.php).
        if ($conversation->window_open) {
            $v = Validator::make($request->all(), ['body' => ['required', 'string', 'max:4096']]);
            if ($v->fails()) {
                return response()->json(['errors' => $v->errors()], 422);
            }
            $body = (string) $request->input('body');
        } else {
            $v = Validator::make($request->all(), ['template_id' => ['required', 'integer']]);
            if ($v->fails()) {
                return response()->json(['errors' => $v->errors()], 422);
            }
            $template = MessageTemplate::where('approval_status', 'approved')->find($request->integer('template_id'));
            if (! $template) {
                return response()->json([
                    'errors' => ['template_id' => ['Outside the 24-hour window you can only send an approved template.']],
                ], 422);
            }
            $body = $template->body;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'agent',
            'body' => $body,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Deliver through the channel connector when one exists (web widget is in-app).
        $contact = $conversation->contact;
        $to = $contact->phone ?? $contact->email;
        $channels = app(ChannelManager::class);
        if ($to && $channels->supports($conversation->channel)) {
            $message->update(['status' => 'queued']);
            SendOutboundMessage::dispatch($message->id, $conversation->channel, $to);
        }

        // A human reply reopens a resolved chat and takes the conversation off the AI.
        $conversation->update([
            'status' => $conversation->status === 'resolved' ? 'open' : $conversation->status,
            'last_message' => $body,
            'last_message_at' => now(),
            'ai_status' => 'suppressed',
        ]);

        MessageCreated::dispatch($message);

        return response()->json(['message' => [
            'id' => $message->id,
            'direction' => 'out',
            'author' => 'agent',
            'body' => $message->body,
            'sent_at' => $message->sent_at->toIso8601String(),
            'status' => $message->status,
        ]]);
    }

    /** Resolve an open conversation, or reopen a resolved one. */
    public function resolve(Conversation $conversation): RedirectResponse
    {
        $reopening = $conversation->status === 'resolved';
        $conversation->update(['status' => $reopening ? 'open' : 'resolved']);

        if ($reopening) {
            $conversation->increment('reopened_count');
        }

        return back();
    }

    /** Hand a conversation back to the AI (re-arm after a handoff / human takeover). */
    public function resumeAi(Conversation $conversation): RedirectResponse
    {
        $conversation->update(['ai_status' => 'active', 'ai_resumed_at' => now()]);

        return back()->with('success', 'AI resumed — it will handle the next message.');
    }

    /** Tag a conversation with a topic (existing tag id, or create one by name). */
    public function addTag(Request $request, Conversation $conversation): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:40', 'required_without:tag_id'],
            'tag_id' => ['nullable', 'integer', 'required_without:name'],
        ]);

        $tag = isset($data['tag_id'])
            ? Tag::findOrFail($data['tag_id'])
            : Tag::firstOrCreate(['name' => trim((string) $data['name'])], ['color' => 'accent']);

        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['tag' => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color]]);
    }

    /** Remove a topic tag from a conversation. */
    public function removeTag(Conversation $conversation, Tag $tag): JsonResponse
    {
        $conversation->tags()->detach($tag->id);

        return response()->json(['ok' => true]);
    }

    /** Assign (or unassign) a conversation to a teammate in this workspace. */
    public function assign(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['assignee_id' => ['nullable', 'integer']]);
        $assigneeId = $data['assignee_id'] ?? null;

        if ($assigneeId !== null) {
            $inWorkspace = User::where('id', $assigneeId)->where('workspace_id', Tenancy::id())->exists();
            abort_unless($inWorkspace, 422);
        }

        $conversation->update(['assignee_id' => $assigneeId]);

        return back();
    }
}
