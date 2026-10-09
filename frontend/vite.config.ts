import { fileURLToPath } from 'node:url';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

// The URL the browser uses to reach the Vite dev server. Only set when Vite runs in a
// container; see docker-compose.yml.
const containerOrigin = process.env.VITE_DEV_SERVER_URL;

// Where the app itself is served. Assets are requested from the Vite origin by a page loaded
// from this one, so Vite has to allow it explicitly as a cross-origin caller.
const appOrigin = process.env.APP_URL ?? 'http://localhost:8001';

export default defineConfig({
    resolve: {
        alias: {
            // `@/` has to be defined here. laravel-vite-plugin ships a default of
            // '/resources/js', which is where the code used to live and no longer exists, and
            // it merges any alias set here over its own. tsconfig.json's `paths` entry is only
            // for the editor and tsc — Vite does not read it.
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    plugins: lazyPlugins(() => [
        laravel({
            input: ['src/css/app.css', 'src/app.tsx'],
            // The built assets, the dev-server hot file and the SSR bundle all belong to the
            // Laravel app, which serves them. This project lives beside it, not inside it.
            publicDirectory: '../backend/public',
            buildDirectory: 'build',
            ssrOutputDirectory: '../backend/bootstrap/ssr',
            // The default watches Laravel's own folders, which are not under this project's
            // root. Blade is only the thin shell around the React app, so there is nothing
            // worth a full reload.
            refresh: false,
            fonts: [
                bunny('Archivo', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('JetBrains Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            // Generated code lands in ./src (it is imported as `@/routes` and `@/actions`).
            path: './src',
            // artisan lives in the backend project now. Relative to this folder, which is
            // where the command runs from.
            command: 'php ../backend/artisan wayfinder:generate',
            // Re-generate when a route or controller changes.
            patterns: [
                '../backend/routes/**/*.php',
                '../backend/app/**/Http/**/*.php',
            ],
        }),
    ]),
    build: {
        // The output folder is backend/public/build, outside this project's root, so Vite will
        // not empty it by default and every build would leave its predecessor's hashed files
        // behind. That folder holds nothing but build output, so emptying it is safe.
        emptyOutDir: true,
    },
    server: {
        // Set by docker-compose, absent for a host (Herd) dev server, so the host workflow
        // keeps its defaults untouched.
        host: containerOrigin ? '0.0.0.0' : undefined,
        strictPort: Boolean(containerOrigin),
        // laravel-vite-plugin writes `server.origin` verbatim into public/hot, and the
        // browser lives outside the container. Without this the hot file would contain the
        // container's own bind address and every asset URL would 404.
        origin: containerOrigin,
        // Vite 8 derives its default Access-Control-Allow-Origin from `server.origin`, which
        // would advertise the Vite URL itself — so a page served from :8001 requesting a
        // module from :5173 gets blocked. The app's origin has to be named explicitly.
        cors: containerOrigin ? { origin: appOrigin } : undefined,
        watch: {
            // Windows bind mounts do not deliver inotify events into a Linux container, so
            // HMR needs polling there. Native watching stays on when running on the host.
            usePolling: Boolean(containerOrigin),
            // 300ms polled the whole tree three times a second and kept a core busy; edits
            // showing up a second later is not noticeable.
            interval: 1000,
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'node_modules/**',
            'tailwind.config.js',
            'src/actions/**',
            'src/components/ui/*',
            'src/routes/**',
            'src/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: ['src/components/ui/*'],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'src/css/app.css',
        },
    },
});
