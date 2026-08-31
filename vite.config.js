import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
'resources/js/qz-tray-init.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
