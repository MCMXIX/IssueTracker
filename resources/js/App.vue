<script setup lang="ts">
import { onMounted, ref } from 'vue';
import AppShell from './layouts/AppShell.vue';

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
    const csrf = await fetch('/sanctum/csrf-cookie', {
        headers: { Accept: 'application/json' },
    });
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    if (!csrf.ok || !token) {
        throw new Error('Could not start a secure session. Try again.');
    }

    const response = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(token),
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
    try {
        await loadUser();
    } catch {
        error.value = 'Could not check your session. Reload to try again.';
    } finally {
        loading.value = false;
    }
});

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
            <button type="button" class="button-secondary" :disabled="pending" @click="signOut">
                {{ pending ? 'Signing out…' : 'Sign out' }}
            </button>
        </template>

        <section aria-labelledby="account-title">
            <h1 id="account-title" class="text-2xl font-semibold tracking-tight">{{ user.name }}</h1>
            <p class="mt-1 text-sm text-muted">{{ user.email }}</p>
            <p v-if="error" class="mt-4 text-sm text-danger" role="alert">{{ error }}</p>
        </section>
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
