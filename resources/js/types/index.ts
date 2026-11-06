import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    can: { admin: boolean; facilitate: boolean };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null; invite_link?: { email: string; url: string } | null };
    registrationOpen: boolean;
    guide: GuidePayload | null;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Option {
    value: string;
    label: string;
}

export interface InitiativeSummary {
    id: number;
    name: string;
    scope: string | null;
    description: string | null;
    phase: string;
    phase_label: string;
    owner: string;
}

export interface WalkthroughActivity {
    key: string;
    step: number;
    phase: string;
    phase_label: string;
    title: string;
    route: string | null;
}

export interface GuidePayload {
    key: string;
    title: string;
    html: string;
    markdown: string | null;
    customized: boolean;
    canEdit: boolean;
    activities: WalkthroughActivity[];
    initiativeId: number | null;
    note: string | null;
    canEditNote: boolean;
    assistant: AssistantPayload;
}

export interface AssistantMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    html: string | null;
}

export interface AssistantPayload {
    configured: boolean;
    canConfigure: boolean;
    endpoint: string;
    clearEndpoint: string;
    messages: AssistantMessage[];
}
