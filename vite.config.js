import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

export default defineConfig({
  plugins: [
    tailwindcss(),
  ],
  build: {
    manifest: true,
    // Gunakan import.meta.dirname alih-alih __dirname untuk ESM modern
    outDir: resolve(import.meta.dirname, 'public/assets'),
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app: resolve(import.meta.dirname, 'resources/js/app.js'),
      },
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
  publicDir: false,
});