<script setup lang="ts">
import AttentionFlag from './AttentionFlag.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { ApiError, issueRequest } from '../../api';
import IssueForm from './IssueForm.vue';
import {
    categories, formatDate, priorities, statuses,
    type AuditValue, type Comment, type Issue, type IssueDetailData, type Resource,
} from './issue';

const props = defineProps<{ issueId: string }>();
const endpoint = '/api/issues/' + props.issueId;
const issue = ref<IssueDetailData | null>(null);
const loading = ref(true);
const loadError = ref('');
const editing = ref(false);
const commentBody = ref('');
const posting = ref(false);
const commentError = ref('');
const summaryPending = computed(() => issue.value?.summary_status === 'pending' || issue.value?.summary_status === 'processing');
let activeRequest: AbortController | undefined;
let timer: number | undefined;
let disposed = false;

const auditLabels: Record<string, string> = {
    title: 'Title',
    description: 'Description',
    category: 'Category',
    priority: 'Priority',
    status: 'Status',
    needs_attention: 'Needs attention',
};

function auditValue(field: string, value: AuditValue): string {
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'string') {
        if (field === 'category') return categories[value as keyof typeof categories] ?? value;
        if (field === 'priority') return priorities[value as keyof typeof priorities] ?? value;
        if (field === 'status') return statuses[value as keyof typeof statuses] ?? value;
    }

    return value === null ? '—' : String(value);
}

async function load() {
    window.clearTimeout(timer);
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;

    try {
        const { data } = await issueRequest<Resource<IssueDetailData>>(endpoint, { signal: request.signal });
        if (!request.signal.aborted) {
            issue.value = data;
            loadError.value = '';
        }
    } catch (failure) {
        if (!request.signal.aborted) {
            loadError.value = failure instanceof ApiError && failure.status === 404
                ? 'Issue not found.'
                : failure instanceof Error ? failure.message : 'Could not load the issue.';
        }
    } finally {
        if (!request.signal.aborted) {
            loading.value = false;
            if (summaryPending.value && !disposed) {
                timer = window.setTimeout(() => void load(), 2500);
            }
        }
    }
}

function applyEdit(updated: Issue) {
    if (issue.value) {
        issue.value = { ...issue.value, ...updated };
    }

    editing.value = false;
    void load();
}

async function postComment() {
    const body = commentBody.value.trim();
    if (!body) {
        commentError.value = 'Enter a comment.';
        return;
    }

    commentError.value = '';
    posting.value = true;

    try {
        const { data } = await issueRequest<Resource<Comment>>(endpoint + '/comments', {
            method: 'POST',
            body: { body },
        });

        if (issue.value && !issue.value.comments.some((comment) => comment.id === data.id)) {
            issue.value.comments.push(data);
        }

        commentBody.value = '';
        void load();
    } catch (failure) {
        commentError.value = failure instanceof ApiError && failure.fields.body
            ? failure.fields.body[0]
            : failure instanceof Error ? failure.message : 'Could not post the comment.';
    } finally {
        posting.value = false;
    }
}

onMounted(load);
onBeforeUnmount(() => {
    disposed = true;
    window.clearTimeout(timer);
    activeRequest?.abort();
});
</script>

<template>
    <section aria-labelledby="issue-title">
        <a href="/" class="mb-5 inline-block rounded-sm text-sm text-brand hover:underline focus-visible:outline-2 focus-visible:outline-brand">
            ← Back to inbox
        </a>

        <p v-if="loading" class="text-sm text-muted" role="status">Loading issue…</p>
        <div v-if="loadError" class="mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-danger/30 bg-danger/10 p-3">
            <p class="text-sm text-danger" role="alert">{{ loadError }}</p>
            <button v-if="loadError !== 'Issue not found.'" type="button" class="button-secondary" @click="load">Try again</button>
        </div>

        <template v-if="issue">
            <header class="mb-7 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="mb-2 text-sm text-muted">Issue #{{ issue.id }}</p>
                    <h1 id="issue-title" class="wrap-anywhere text-3xl font-semibold tracking-tight">{{ issue.title }}</h1>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-raised px-2.5 py-1">{{ statuses[issue.status] }}</span>
                        <span class="rounded-full bg-raised px-2.5 py-1 text-muted">{{ categories[issue.category] }}</span>
                        <span class="rounded-full px-2.5 py-1"
                            :class="issue.needs_attention ? 'bg-attention/10 text-attention' : 'bg-raised text-muted'">
                            {{ priorities[issue.priority] }} priority
                        </span>
                        <AttentionFlag v-if="issue.needs_attention" show-label />
                    </div>
                </div>
                <button v-if="!editing" type="button" class="button-secondary" @click="editing = true">Edit issue</button>
            </header>

            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <aside class="rounded-xl border border-line bg-panel p-5 lg:col-start-2 lg:row-start-1" aria-labelledby="summary-title">
                    <h2 id="summary-title" class="font-semibold">Generated summary</h2>
                    <div class="mt-3 space-y-4" aria-live="polite">
                        <p v-if="summaryPending" class="text-sm text-muted" role="status">Generating summary…</p>
                        <p v-else-if="issue.summary_status === 'failed'" class="text-sm text-danger">Summary unavailable.</p>
                        <template v-else>
                            <p class="wrap-anywhere text-sm leading-relaxed">{{ issue.summary }}</p>
                            <div v-if="issue.suggested_next_action" class="border-t border-line pt-4">
                                <h3 class="text-sm font-medium">Suggested next action</h3>
                                <p class="mt-1.5 wrap-anywhere text-sm leading-relaxed text-brand">{{ issue.suggested_next_action }}</p>
                            </div>
                        </template>
                    </div>
                </aside>

                <div class="min-w-0 space-y-5 lg:col-start-1 lg:row-start-1">
                    <section class="rounded-xl border border-line bg-panel p-5" aria-labelledby="report-title">
                        <h2 id="report-title" class="mb-3 font-semibold">{{ editing ? 'Edit issue' : 'Description' }}</h2>
                        <IssueForm v-if="editing" :issue="issue" @saved="applyEdit" @cancel="editing = false" />
                        <p v-else class="wrap-anywhere whitespace-pre-wrap text-sm leading-relaxed">{{ issue.description }}</p>
                    </section>

                    <section class="rounded-xl border border-line bg-panel p-5" aria-labelledby="comments-title">
                        <h2 id="comments-title" class="font-semibold">Comments</h2>
                        <ul v-if="issue.comments.length" class="mt-2 divide-y divide-line">
                            <li v-for="comment in issue.comments" :key="comment.id" class="py-3">
                                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                                    <span class="font-medium">{{ comment.author_name }}</span>
                                    <time :datetime="comment.created_at" class="text-xs text-muted">{{ formatDate(comment.created_at) }}</time>
                                </div>
                                <p class="mt-1 wrap-anywhere whitespace-pre-wrap text-sm leading-relaxed">{{ comment.body }}</p>
                            </li>
                        </ul>
                        <p v-else class="my-3 text-sm text-muted">No comments yet.</p>

                        <form class="mt-3 grid gap-2" @submit.prevent="postComment">
                            <label for="comment-body" class="text-sm font-medium">Add a comment</label>
                            <textarea id="comment-body" v-model="commentBody" class="form-field" rows="3" maxlength="600" required
                                :aria-invalid="Boolean(commentError)" :aria-describedby="commentError ? 'comment-error' : undefined" />
                            <p v-if="commentError" id="comment-error" class="text-sm text-danger" role="alert">{{ commentError }}</p>
                            <div><button type="submit" class="button-secondary" :disabled="posting">{{ posting ? 'Posting…' : 'Post comment' }}</button></div>
                        </form>
                    </section>

                    <details class="rounded-xl border border-line bg-panel p-5">
                        <summary class="rounded-sm font-semibold focus-visible:outline-2 focus-visible:outline-brand">Audit history</summary>
                        <p v-if="!issue.logs.length" class="mt-3 text-sm text-muted">No audit entries yet.</p>
                        <ul v-else class="mt-2 divide-y divide-line">
                            <li v-for="log in issue.logs" :key="log.id" class="py-3 text-sm">
                                <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                    <span class="font-medium capitalize">{{ log.action.replaceAll('_', ' ') }}</span>
                                    <span v-if="log.actor" class="text-muted">by {{ log.actor.name }}</span>
                                    <time :datetime="log.created_at" class="text-xs text-muted">{{ formatDate(log.created_at) }}</time>
                                </div>
                                <dl v-if="log.details?.changes" class="mt-2 grid gap-2">
                                    <div v-for="(change, field) in log.details.changes" :key="field" class="wrap-anywhere">
                                        <dt class="font-medium">{{ auditLabels[field] ?? field }}</dt>
                                        <dd class="mt-0.5 whitespace-pre-wrap text-muted">
                                            {{ auditValue(field, change.from) }} → {{ auditValue(field, change.to) }}
                                        </dd>
                                    </div>
                                </dl>
                                <p v-else-if="log.details?.message" class="mt-1 wrap-anywhere text-muted">{{ log.details.message }}</p>
                            </li>
                        </ul>
                    </details>
                </div>
            </div>
        </template>
    </section>
</template>
