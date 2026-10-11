import { fileURLToPath } from 'node:url';
import { cpSync } from 'node:fs';
import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';
const optional = fileURLToPath(new URL('./src/lib/jspdf-optional.ts', import.meta.url));
export default defineConfig({
  base: '/workspace-design-build/',
  plugins: [preact(), tailwindcss(), {
    name: 'workspace-design-license-notices',
    closeBundle() {
      cpSync(fileURLToPath(new URL('./licenses', import.meta.url)), fileURLToPath(new URL('./dist/licenses', import.meta.url)), {recursive:true});
      cpSync(fileURLToPath(new URL('./LICENSE.upstream', import.meta.url)), fileURLToPath(new URL('./dist/LICENSE.upstream', import.meta.url)));
    },
  }],
  build: {
    outDir: 'dist', manifest: true,
    rollupOptions: { input: 'src/main.tsx' },
  },
  resolve: { alias: { react: 'preact/compat', 'react-dom': 'preact/compat', 'react/jsx-runtime': 'preact/jsx-runtime', canvg: optional, html2canvas: optional, dompurify: optional } },
});
