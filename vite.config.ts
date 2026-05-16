import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import i18n from 'laravel-react-i18n/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        i18n(),
    ],
    resolve: {
        // laravel-react-i18n declares `react` as a hard dependency (^18),
        // so the package manager installs a second React copy. Without
        // dedupe, Vite bundles that copy into laravel-react-i18n and its
        // hooks run against a different React instance ("Invalid hook
        // call" -> null dispatcher). Force a single React/ReactDOM.
        dedupe: ['react', 'react-dom'],
    },
});
