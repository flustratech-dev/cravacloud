import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Paksa bind ke IPv4 supaya URL di public/hot berupa http://127.0.0.1:PORT,
        // bukan http://[::1]:PORT — beberapa browser Windows gagal load asset
        // dari address IPv6 bracketed.
        host: '127.0.0.1',
        // strictPort mencegah Vite geser ke port lain (5174) kalau 5173 dipakai;
        // lebih baik langsung error supaya kelihatan ada proses zombie.
        strictPort: true,
        port: 5173,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
