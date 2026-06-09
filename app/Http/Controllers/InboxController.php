<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    /**
     * The unified inbox (B3). Seeded demo data renders the 3-pane workspace;
     * the live data layer (channels, conversations, messages) lands in Phase 3–4.
     */
    public function index(): Response
    {
        $conversations = [
            $this->conversation(1, 'Layla Hassan', 'whatsapp', 'Is the abaya available in navy?', 2, true, 'open', 'VIP'),
            $this->conversation(2, 'Marcus Reed', 'instagram', 'Thanks! Order received 🙌', 0, true, 'open', 'Returning'),
            $this->conversation(3, 'Priya Nair', 'messenger', 'My tracking link isn’t working', 1, true, 'pending', 'Customer', true),
            $this->conversation(4, 'João Silva', 'whatsapp', 'Do you ship to Lisbon?', 0, false, 'open', 'Lead'),
            $this->conversation(5, 'Sara Khan', 'web', 'Can I change my delivery address?', 3, true, 'open', 'Customer'),
            $this->conversation(6, 'Daniel Cohen', 'telegram', 'Resolved — thank you!', 0, true, 'resolved', 'Customer'),
        ];

        $messages = [
            1 => [
                $this->msg(1, 'in', 'customer', 'Hi! Do you have the linen abaya in navy?', '-2 hours'),
                $this->msg(2, 'out', 'bot', 'Hi Layla 👋 Let me check that for you.', '-118 minutes', 'read'),
                $this->msg(3, 'system', 'system', 'Bot handed off to you', '-117 minutes'),
                $this->msg(4, 'out', 'agent', 'Yes — navy is back in stock in S, M and L.', '-115 minutes', 'read'),
                $this->msg(5, 'in', 'customer', 'Is the abaya available in navy?', '-3 minutes'),
            ],
            2 => [
                $this->msg(10, 'out', 'agent', 'Your order #1182 is confirmed!', '-1 day', 'read'),
                $this->msg(11, 'in', 'customer', 'Thanks! Order received 🙌', '-20 minutes'),
            ],
            3 => [
                $this->msg(20, 'in', 'customer', 'My tracking link isn’t working', '-30 minutes'),
            ],
            4 => [
                $this->msg(30, 'in', 'customer', 'Do you ship to Lisbon?', '-2 days'),
            ],
            5 => [
                $this->msg(40, 'in', 'customer', 'Can I change my delivery address?', '-10 minutes'),
            ],
            6 => [
                $this->msg(50, 'in', 'customer', 'Resolved — thank you!', '-3 days'),
            ],
        ];

        return Inertia::render('Inbox/Index', [
            'conversations' => $conversations,
            'messages' => $messages,
        ]);
    }

    /** @return array<string, mixed> */
    private function conversation(int $id, string $name, string $channel, string $last, int $unread, bool $window, string $status, string $lifecycle, bool $sla = false): array
    {
        return [
            'id' => $id,
            'contact' => ['id' => $id, 'name' => $name, 'channel' => $channel, 'lifecycle' => $lifecycle],
            'last_message' => $last,
            'last_message_at' => now()->subMinutes($id * 7)->toIso8601String(),
            'unread' => $unread,
            'channel' => $channel,
            'status' => $status,
            'assignee' => $id % 2 === 0 ? ['id' => 1, 'name' => 'You'] : null,
            'sla_breaching' => $sla,
            'window_open' => $window,
        ];
    }

    /** @return array<string, mixed> */
    private function msg(int $id, string $direction, string $author, string $body, string $when, ?string $status = null): array
    {
        return [
            'id' => $id,
            'direction' => $direction,
            'author' => $author,
            'body' => $body,
            'sent_at' => now()->modify($when)->toIso8601String(),
            'status' => $status,
        ];
    }
}
