<template>
    <div class="min-h-dvh">
        <header class="border-b border-line bg-panel">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-3 sm:px-6">
                <a
                    href="/"
                    class="rounded-sm text-sm font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand"
                >
                    <span class="mr-2 inline-block size-2 rounded-sm bg-brand" aria-hidden="true"></span>
                    Issue Tracker
                </a>
                <slot name="actions" />
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
            <slot />
        </main>
    </div>
</template>
