import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

const actRoot = path.resolve(__dirname, 'resources')
const concordAppRoot = path.resolve(actRoot, 'act_app')
const pagesBase = process.env.CONCORD_PAGES_BASE || '/'

/** Dev-сервер и production-сборка статического превью Concord UI. */
export default defineConfig(() => ({
  root: actRoot,
  publicDir: false,
  base: pagesBase,
  plugins: [vue()],
  resolve: {
    alias: {
      '@concord': concordAppRoot,
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
    open: '/concord-preview.html',
  },
  configureServer(server) {
    server.middlewares.use((req, _res, next) => {
      const path = req.url?.split('?')[0] || ''
      if (path === '/' || path === '/index.html') {
        req.url = '/concord-preview.html'
      }
      next()
    })
  },
  build: {
    outDir: path.resolve(__dirname, 'dist/concord-pages'),
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(actRoot, 'concord-preview.html'),
    },
  },
}))
