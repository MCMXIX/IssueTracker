import tailwindcss from '@tailwindcss/vite';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ mode }) => {
    const viteOrigin = process.env.DEV_VITE_ORIGIN;
    const { APP_URL } = loadEnv(mode, process.cwd(), 'APP_URL');

    return {
        server: viteOrigin
            ? {
                  origin: viteOrigin,
                  cors: {
                      origin: [
                          ...(APP_URL ? [new URL(APP_URL).origin] : []),
                          /^http:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/,
                      ],
                  },
              }
            : undefined,
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                refresh: true,
            }),
            vue(),
            tailwindcss(),
        ],
    };
});
