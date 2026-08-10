import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      strategies: 'injectManifest',
      srcDir: 'src/workers',
      filename: 'service-worker.ts',
      injectRegister: false,
      manifest: false, // we use our own manifest.webmanifest in public/
      devOptions: {
        enabled: false,
      },
      injectManifest: {
        globPatterns: ['**/*.{js,css,html,woff2,png,svg,ico}'],
      },
    }),
  ],
  resolve: {
    alias: {
      '@': new URL('./src', import.meta.url).pathname,
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    target: 'es2022',
  },
});
