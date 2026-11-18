export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly fields: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

export async function getCsrfToken(): Promise<string> {
    const response = await fetch('/sanctum/csrf-cookie', {
        headers: { Accept: 'application/json' },
    });
    const token = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.slice('XSRF-TOKEN='.length);

    if (!response.ok || !token) {
        throw new Error('Could not start a secure session. Try again.');
    }

    return decodeURIComponent(token);
}

export async function issueRequest<T>(
    url: string,
    options: { method?: 'POST' | 'PATCH'; body?: object; signal?: AbortSignal } = {},
): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (options.method) {
        headers['Content-Type'] = 'application/json';
        headers['X-XSRF-TOKEN'] = await getCsrfToken();
    }

    const response = await fetch(url, {
        method: options.method ?? 'GET',
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
        signal: options.signal,
    });

    if (!response.ok) {
        if (response.status === 401) {
            window.dispatchEvent(new Event('issue-tracker:session-expired'));
        }

        const error = await response.json().catch(() => ({}));
        const message = response.status === 419
            ? 'Your session could not be verified. Try again.'
            : response.status >= 500
                ? 'The server could not complete the request. Try again.'
                : error.message || 'Could not complete the request. Try again.';

        throw new ApiError(response.status, message, error.errors || {});
    }

    return response.json();
}
