import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { svelte } from '@sveltejs/vite-plugin-svelte';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, type Plugin } from 'vite';

const isSvelteCheck = process.argv.some((argument) => argument.includes('svelte-check'));
const projectRoot = path.dirname(fileURLToPath(import.meta.url));

if (isSvelteCheck) {
    process.env.LARAVEL_BYPASS_ENV_CHECK ??= '1';
}

function normalizeLaravelManifestPaths(): Plugin {
    return {
        name: 'normalize-laravel-manifest-paths',
        closeBundle() {
            const manifestPath = path.resolve(projectRoot, 'public/build/manifest.json');

            if (!fs.existsSync(manifestPath)) {
                return;
            }

            const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf-8')) as Record<
                string,
                Record<string, unknown>
            >;

            const normalizeResourcePath = (value: string): string => {
                const normalizedValue = value.replace(/\\/g, '/');
                const resourcesIndex = normalizedValue.lastIndexOf('/resources/');

                if (resourcesIndex >= 0) {
                    return normalizedValue.slice(resourcesIndex + 1);
                }

                if (normalizedValue.startsWith('resources/')) {
                    return normalizedValue;
                }

                return value;
            };

            const normalizedManifest = Object.fromEntries(
                Object.entries(manifest).map(([key, entry]) => {
                    const normalizedKey = normalizeResourcePath(key);
                    const normalizedEntry = {
                        ...entry,
                        ...(typeof entry.src === 'string'
                            ? { src: normalizeResourcePath(entry.src) }
                            : {}),
                    };

                    return [normalizedKey, normalizedEntry];
                }),
            );

            fs.writeFileSync(
                manifestPath,
                JSON.stringify(normalizedManifest, null, 2),
            );
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        svelte(),
        wayfinder({
            formVariants: true,
        }),
        normalizeLaravelManifestPaths(),
    ],
});
