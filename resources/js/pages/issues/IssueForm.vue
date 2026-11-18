<script setup lang="ts">
import { reactive, ref } from 'vue';
import { ApiError, issueRequest } from '../../api';
import { categories, priorities, statuses, type Issue, type IssueFields, type Resource } from './issue';

const props = defineProps<{ issue?: Issue }>();
const emit = defineEmits<{ saved: [issue: Issue]; cancel: [] }>();

const original: IssueFields = {
    title: props.issue?.title ?? '',
    description: props.issue?.description ?? '',
    category: props.issue?.category ?? 'billing',
    priority: props.issue?.priority ?? 'medium',
    status: props.issue?.status ?? 'open',
};
const form = reactive({ ...original });
const saving = ref(false);
const error = ref('');
const fieldErrors = ref<Record<string, string[]>>({});

async function submit() {
    saving.value = true;
    error.value = '';
    fieldErrors.value = {};

    const body = props.issue
        ? Object.fromEntries(Object.entries(form).filter(([field, value]) => value !== original[field as keyof IssueFields]))
        : { title: form.title, description: form.description, category: form.category, priority: form.priority };

    try {
        const { data } = await issueRequest<Resource<Issue>>(props.issue ? '/api/issues/' + props.issue.id : '/api/issues', {
            method: props.issue ? 'PATCH' : 'POST',
            body,
        });
        emit('saved', data);
    } catch (failure) {
        fieldErrors.value = failure instanceof ApiError ? failure.fields : {};
        error.value = Object.keys(fieldErrors.value).length
            ? 'Check the highlighted fields.'
            : failure instanceof Error ? failure.message : 'Could not save the issue.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <form class="grid gap-5" @submit.prevent="submit">
        <p v-if="error" class="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger" role="alert">
            {{ error }}
        </p>

        <label class="grid gap-1.5 text-sm font-medium">
            Title
            <input v-model.trim="form.title" type="text" required maxlength="120" class="form-field"
                :aria-invalid="Boolean(fieldErrors.title)" :aria-describedby="fieldErrors.title ? 'issue-title-error' : undefined">
            <span v-if="fieldErrors.title" id="issue-title-error" class="text-danger">{{ fieldErrors.title[0] }}</span>
        </label>

        <label class="grid gap-1.5 text-sm font-medium">
            Description
            <textarea v-model.trim="form.description" required maxlength="10000" rows="5" class="form-field"
                :aria-invalid="Boolean(fieldErrors.description)" :aria-describedby="fieldErrors.description ? 'issue-description-error' : undefined"
                placeholder="What happened and who was affected?" />
            <span v-if="fieldErrors.description" id="issue-description-error" class="text-danger">{{ fieldErrors.description[0] }}</span>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="grid content-start gap-1.5 text-sm font-medium">
                Category
                <select v-model="form.category" class="form-field"
                    :aria-invalid="Boolean(fieldErrors.category)" :aria-describedby="fieldErrors.category ? 'issue-category-error' : undefined">
                    <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
                </select>
                <span v-if="fieldErrors.category" id="issue-category-error" class="text-danger">{{ fieldErrors.category[0] }}</span>
            </label>

            <label class="grid content-start gap-1.5 text-sm font-medium">
                Priority
                <select v-model="form.priority" class="form-field"
                    :aria-invalid="Boolean(fieldErrors.priority)" :aria-describedby="fieldErrors.priority ? 'issue-priority-error' : undefined">
                    <option v-for="(label, value) in priorities" :key="value" :value="value">{{ label }}</option>
                </select>
                <span v-if="fieldErrors.priority" id="issue-priority-error" class="text-danger">{{ fieldErrors.priority[0] }}</span>
            </label>

            <label v-if="issue" class="grid content-start gap-1.5 text-sm font-medium">
                Status
                <select v-model="form.status" class="form-field"
                    :aria-invalid="Boolean(fieldErrors.status)" :aria-describedby="fieldErrors.status ? 'issue-status-error' : undefined">
                    <option v-for="(label, value) in statuses" :key="value" :value="value">{{ label }}</option>
                </select>
                <span v-if="fieldErrors.status" id="issue-status-error" class="text-danger">{{ fieldErrors.status[0] }}</span>
            </label>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="button-primary" :disabled="saving">
                {{ saving ? 'Saving…' : issue ? 'Save changes' : 'Create issue' }}
            </button>
            <button type="button" class="button-secondary" :disabled="saving" @click="emit('cancel')">Cancel</button>
        </div>
    </form>
</template>
