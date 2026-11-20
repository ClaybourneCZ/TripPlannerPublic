import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

const server = await createServer({
    configFile: false,
    plugins: [vue()],
    resolve: { alias: { '@': fileURLToPath(new URL('../../resources/js', import.meta.url)) } },
    server: { middlewareMode: true, hmr: false },
    appType: 'custom',
});
try {
    await (await server.ssrLoadModule('/tests/frontend/features.mjs')).run();
} finally {
    await server.close();
}
