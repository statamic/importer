import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        statamic(),
        tailwindcss(),
        laravel({
            hotFile: 'dist/hot',
            publicDirectory: 'dist',
            input: ['resources/css/cp.css', 'resources/js/cp.js'],
        }),
    ],
});
