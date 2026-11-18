<script setup lang="ts">
import AttentionFlag from './AttentionFlag.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { issueRequest } from '../../api';
import { categories, priorities, statuses, type Issue } from './issue';

interface IssuePage {
    data: Issue[];
    meta: { current_page: number; last_page: number; from: number | null; to: number | null; total: number };
}

const filterFields = [
    { name: 'status', label: 'Status', all: 'All statuses', options: statuses },
    { name: 'priority', label: 'Priority', all: 'All priorities', options: priorities },
    { name: 'category', label: 'Category', all: 'All categories', options: categories },
] as const;

const currentQuery = new URLSearchParams(window.location.search);
const filters = reactive({
    status: currentQuery.get('status') ?? '',
    priority: currentQuery.get('priority') ?? '',
    category: currentQuery.get('category') ?? '',
});

for (const field of filterFields) {
    if (!Object.hasOwn(field.options, filters[field.name])) {
        filters[field.name] = '';
    }
}

const results = ref<IssuePage | null>(null);
const loading = ref(true);
const error = ref('');
const hasFilters = computed(() => Object.values(filters).some(Boolean));
let activeRequest: AbortController | undefined;
let requestedPage = 1;

function listQuery(page: number): string {
    const query = new URLSearchParams();
    for (const [name, value] of Object.entries(filters)) {
        if (value) {
            query.set(name, value);
        }
    }

    if (page > 1) {
        query.set('page', String(page));
    }

    const queryString = query.toString();
    return queryString ? '?' + queryString : '';
}

async function load(page: number): Promise<void> {
    activeRequest?.abort();
    const request = new AbortController();
    activeRequest = request;
    requestedPage = page;

    const query = listQuery(page);
    window.history.replaceState(null, '', '/' + query);
    loading.value = true;
    error.value = '';

    try {
        const pageData = await issueRequest<IssuePage>('/api/issues' + query, { signal: request.signal });
        if (!request.signal.aborted) {
            if (page > pageData.meta.last_page) {
                return load(pageData.meta.last_page);
            }
            results.value = pageData;
        }
    } catch (failure) {
        if (!request.signal.aborted) {
            error.value = failure instanceof Error ? failure.message : 'Could not load issues.';
        }
    } finally {
        if (!request.signal.aborted) {
            loading.value = false;
        }
    }
}

function clearFilters() {
    Object.assign(filters, { status: '', priority: '', category: '' });
    void load(1);
}

onMounted(() => {
    const page = Number(currentQuery.get('page') ?? '1');
    void load(Number.isSafeInteger(page) && page > 0 ? page : 1);
});
onBeforeUnmount(() => activeRequest?.abort());
</script>

<template>
    <section aria-labelledby="inbox-title">
        <div class="mb-7 flex flex-wrap items-center justify-between gap-4">
            <h1 id="inbox-title" class="text-3xl font-semibold tracking-tight">Issue inbox</h1>
            <a class="button-primary gap-2" href="/issues/new">
                <span aria-hidden="true" class="text-lg leading-none">+</span> New issue
            </a>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <label v-for="field in filterFields" :key="field.name" class="grid gap-1.5 text-sm font-medium">
                {{ field.label }}
                <select v-model="filters[field.name]" class="form-field" @change="load(1)">
                    <option value="">{{ field.all }}</option>
                    <option v-for="(label, value) in field.options" :key="value" :value="value">{{ label }}</option>
                </select>
            </label>
        </div>

        <div class="overflow-hidden rounded-xl border border-line bg-panel" :aria-busy="loading">
            <p v-if="loading" class="px-5 py-10 text-center text-sm text-muted" role="status">Loading issues…</p>
            <div v-else-if="error" class="grid justify-items-center gap-3 px-5 py-10">
                <p class="text-sm text-danger" role="alert">{{ error }}</p>
                <button type="button" class="button-secondary" @click="load(requestedPage)">Try again</button>
            </div>
            <div v-else-if="!results?.data.length" class="grid justify-items-center gap-3 px-5 py-12 text-center">
                <h2 class="font-medium">{{ hasFilters ? 'No issues match these filters' : 'No issues yet' }}</h2>
                <button v-if="hasFilters" type="button" class="button-secondary" @click="clearFilters">Clear filters</button>
                <a v-else class="button-secondary" href="/issues/new">Create an issue</a>
            </div>
            <template v-else>
                <table class="w-full table-fixed text-left text-sm sm:table-auto">
                    <caption class="sr-only">Issues</caption>
                    <thead class="border-b border-line text-xs text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium sm:px-5">Issue</th>
                            <th scope="col" class="hidden px-3 py-3 font-medium sm:table-cell">Status</th>
                            <th scope="col" class="w-22 px-3 py-3 font-medium sm:w-auto">Priority</th>
                            <th scope="col" class="hidden px-3 py-3 font-medium sm:table-cell">Category</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="issue in results.data" :key="issue.id" class="hover:bg-raised/45">
                            <td class="px-4 py-3.5 sm:px-5">
                                <div class="my-1 text-xs text-muted">
                                    <span>Ticket: {{ issue.id }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <AttentionFlag v-if="issue.needs_attention" />
                                    <a :href="'/issues/' + issue.id" class="wrap-anywhere rounded-sm font-medium hover:text-brand focus-visible:outline-2 focus-visible:outline-brand">
                                        {{ issue.title }}
                                    </a>
                                </div>
                                <div class="mt-1 text-xs text-muted">
                                    <span class="sm:hidden"> · {{ statuses[issue.status] }} · {{ categories[issue.category] }}</span>
                                </div>
                            </td>
                            <td class="hidden px-3 py-3 sm:table-cell">
                                <span class="rounded-full bg-raised px-2.5 py-1 text-xs" :class="{ 'text-brand': issue.status === 'open' }">
                                    {{ statuses[issue.status] }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span v-if="issue.needs_attention" class="rounded-full bg-attention/10 px-2.5 py-1 text-xs font-medium text-attention">
                                    {{ priorities[issue.priority] }}
                                </span>
                                <span v-else class="text-muted">{{ priorities[issue.priority] }}</span>
                            </td>
                            <td class="hidden px-3 py-3 text-muted sm:table-cell">{{ categories[issue.category] }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 sm:px-5">
                    <p class="text-sm text-muted">{{ results.meta.from }}–{{ results.meta.to }} of {{ results.meta.total }}</p>
                    <nav v-if="results.meta.last_page > 1" aria-label="Issue pages" class="flex items-center gap-2">
                        <button type="button" class="button-secondary" :disabled="results.meta.current_page <= 1" @click="load(results.meta.current_page - 1)">
                            Previous
                        </button>
                        <button type="button" class="button-secondary" :disabled="results.meta.current_page >= results.meta.last_page" @click="load(results.meta.current_page + 1)">
                            Next
                        </button>
                    </nav>
                </div>
            </template>
        </div>
    </section>
</template>
