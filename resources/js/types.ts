export type Channel = 'whatsapp' | 'instagram' | 'messenger' | 'telegram' | 'line' | 'viber' | 'web';

export interface SharedAuthUser {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'manager' | 'agent' | 'developer';
    avatar?: string | null;
}

export interface SharedWorkspace {
    id: number;
    name: string;
    plan: 'premium' | 'business' | 'enterprise';
    wallet_balance: number;
    currency: string;
}

export interface Capabilities {
    manage_billing?: boolean;
    manage_team?: boolean;
    manage_channels?: boolean;
    manage_api?: boolean;
    manage_bots?: boolean;
}

export interface PageProps {
    auth: { user: SharedAuthUser | null; workspace: SharedWorkspace | null; can: Capabilities; features: string[] };
    flash: { success?: string; error?: string };
    locale: string;
    translations?: Record<string, string>;
    [key: string]: unknown;
}

export interface Contact {
    id: number;
    name: string;
    channel: Channel;
    avatar?: string | null;
    lifecycle?: string;
}

export interface Conversation {
    id: number;
    contact: Contact;
    last_message: string;
    last_message_at: string;
    unread: number;
    channel: Channel;
    status: 'open' | 'pending' | 'resolved';
    assignee?: { id: number; name: string } | null;
    sla_breaching?: boolean;
    window_open?: boolean;
    ai_status?: 'active' | 'handed_off' | 'suppressed' | null;
}

export interface Message {
    id: number;
    direction: 'in' | 'out';
    author: 'customer' | 'agent' | 'bot' | 'system';
    body: string;
    sent_at: string;
    status?: 'sending' | 'sent' | 'delivered' | 'read' | 'failed' | 'queued' | 'draft';
}
