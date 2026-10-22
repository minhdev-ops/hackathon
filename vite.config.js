import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.jsx',
                'resources/js/quorum.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        // Dev không cần CORS: Vite proxy sang `php artisan serve`.
        proxy: {
            '/api': {
                target: process.env.VITE_API_URL || 'http://127.0.0.1:8000',
                changeOrigin: false,
            },
            '/login': process.env.VITE_API_URL || 'http://127.0.0.1:8000',
            '/logout': process.env.VITE_API_URL || 'http://127.0.0.1:8000',
            '/up': process.env.VITE_API_URL || 'http://127.0.0.1:8000',
        },
    },
});
