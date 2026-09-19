import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'node:path';

const projectRoot = path.resolve(__dirname, '../../..');

export default defineConfig({
    root: __dirname,
    plugins: [react()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/__tests__/layouts/*.test.tsx'],
    },
    resolve: {
        alias: {
            '@core': path.join(projectRoot, 'resources/js/core'),
            '@admin': path.join(projectRoot, 'templates/_bundled/sirsoft-admin_basic/src'),
            '@adminLayouts': path.join(projectRoot, 'templates/_bundled/sirsoft-admin_basic/layouts'),
        },
    },
});
