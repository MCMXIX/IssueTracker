export const statuses = { open: 'Open', in_progress: 'In progress', resolved: 'Resolved' } as const;
export const priorities = { low: 'Low', medium: 'Medium', high: 'High' } as const;
export const categories = { billing: 'Billing', access: 'Access', operations: 'Operations' } as const;

export type IssueFields = {
    title: string;
    description: string;
    category: keyof typeof categories;
    priority: keyof typeof priorities;
    status: keyof typeof statuses;
};

export type Comment = {
    id: number;
    author_name: string;
    body: string;
    created_at: string;
};

export type AuditValue = string | boolean | number | null;

export type IssueLog = {
    id: number;
    action: string;
    details: {
        message?: string;
        changes?: Record<string, { from: AuditValue; to: AuditValue }>;
    } | null;
    created_at: string;
    actor: { id: number; name: string } | null;
};

export type Issue = IssueFields & {
    id: number;
    summary: string | null;
    suggested_next_action: string | null;
    summary_status: 'pending' | 'processing' | 'success' | 'failed';
    needs_attention: boolean;
    created_at: string;
    updated_at: string;
};

export type IssueDetailData = Issue & { comments: Comment[]; logs: IssueLog[] };
export type Resource<T> = { data: T };

const dateTime = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });

export function formatDate(value: string): string {
    return dateTime.format(new Date(value));
}
