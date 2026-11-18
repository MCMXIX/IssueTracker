<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { getCsrfToken } from './api';
import AppShell from './layouts/AppShell.vue';
import IssueInbox from './pages/issues/IssueInbox.vue';
import IssueDetail from './pages/issues/IssueDetail.vue';
import IssueForm from './pages/issues/IssueForm.vue';
import type { Issue } from './pages/issues/issue';

interface User {
    id: number;
    name: string;
    email: string;
}

const user = ref<User | null>(null);
const email = ref('');
const password = ref('');
const loading = ref(true);
const pending = ref(false);
const error = ref('');
const pathname = window.location.pathname.replace(/\/+$/, '');
const isCreate = pathname === '/issues/new';
const issueId = pathname.match(/^\/issues\/(\d+)$/)?.[1];

function sessionExpired() {
    user.value = null;
    error.value = 'Your session expired. Sign in to continue.';
}

function openIssue(issue: Issue) {
    window.location.assign('/issues/' + issue.id);
}

function openInbox() {
    window.location.assign('/');
}

async function loadUser() {
    const response = await fetch('/api/me', {
        headers: { Accept: 'application/json' },
    });

    if (response.status === 401) {
        user.value = null;
        return;
    }

    if (!response.ok) {
        throw new Error('Could not check your session. Reload to try again.');
    }

    user.value = await response.json();
}

async function postSession(path: string, body?: Record<string, string>) {
    const token = await getCsrfToken();

    const response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': token,
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (response.status === 401 && path === '/logout') {
        return;
    }

    if (response.status === 422) {
        const payload = await response.json();
        throw new Error(payload.message || 'Check your email and password.');
    }

    if (response.status === 429) {
        throw new Error('Too many sign-in attempts. Try again in a minute.');
    }

    if (!response.ok) {
        throw new Error('Could not complete your request. Try again.');
    }
}

onMounted(async () => {
    window.addEventListener('issue-tracker:session-expired', sessionExpired);
    try {
        await loadUser();
    } catch {
        error.value = 'Could not check your session. Reload to try again.';
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => window.removeEventListener('issue-tracker:session-expired', sessionExpired));

async function signIn() {
    pending.value = true;
    error.value = '';

    try {
        await postSession('/login', { email: email.value, password: password.value });
        password.value = '';
        await loadUser();
    } catch (failure) {
        error.value = failure instanceof Error ? failure.message : 'Could not sign in.';
    } finally {
        pending.value = false;
    }
}

async function signOut() {
    pending.value = true;
    error.value = '';

    try {
        await postSession('/logout');
        user.value = null;
    } catch (failure) {
        error.value = failure instanceof Error ? failure.message : 'Could not sign out.';
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <AppShell v-if="user">
        <template #actions>
            <div class="flex items-center gap-3">
                <span class="hidden text-sm text-muted sm:inline">{{ user.name }}</span>
                <button type="button" class="button-secondary" :disabled="pending" @click="signOut">
                    {{ pending ? 'Signing out…' : 'Sign out' }}
                </button>
            </div>
        </template>

        <p v-if="error" class="mb-4 text-sm text-danger" role="alert">{{ error }}</p>
        <section v-if="isCreate" aria-labelledby="new-issue-title" class="max-w-2xl">
            <a href="/" class="mb-5 inline-block rounded-sm text-sm text-brand hover:underline focus-visible:outline-2 focus-visible:outline-brand">← Back to inbox</a>
            <h1 id="new-issue-title" class="mb-5 text-3xl font-semibold tracking-tight">New issue</h1>
            <div class="rounded-xl border border-line bg-panel p-5 sm:p-6">
                <IssueForm @saved="openIssue" @cancel="openInbox" />
            </div>
        </section>
        <IssueDetail v-else-if="issueId" :issue-id="issueId" />
        <IssueInbox v-else />
    </AppShell>

    <main v-else class="grid min-h-dvh place-items-center px-4 py-10">
        <section
            aria-labelledby="sign-in-title"
            class="w-full max-w-sm rounded-2xl border border-line bg-panel p-6 shadow-xl shadow-black/15 sm:p-8"
        >
            <p class="flex items-center gap-2 text-sm font-medium text-muted">
                <span class="size-2 rounded-sm bg-brand" aria-hidden="true"></span>
                Issue Tracker
            </p>
            <h1 id="sign-in-title" class="mt-4 text-2xl font-semibold tracking-tight">Sign in</h1>

            <p v-if="loading" class="mt-4 text-sm text-muted" role="status">Checking your session…</p>

            <p v-if="error" class="mt-4 rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">
                {{ error }}
            </p>

            <form v-if="!loading" class="mt-6 space-y-4" @submit.prevent="signIn">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium">Email</label>
                    <input
                        id="email"
                        v-model.trim="email"
                        type="email"
                        autocomplete="username"
                        required
                        class="form-field"
                    />
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium">Password</label>
                    <input
                        id="password"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="form-field"
                    />
                </div>
                <button type="submit" class="button-primary mt-2 w-full" :disabled="pending">
                    {{ pending ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </section>
    </main>
</template>
