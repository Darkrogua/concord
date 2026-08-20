import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'node:path'
import fs from 'node:fs'
import { execSync } from 'node:child_process'

const actRoot = path.resolve(__dirname, 'resources')
const concordAppRoot = path.resolve(actRoot, 'act_app')
const concordPreviewHtml = path.resolve(actRoot, 'concord-preview.html')
const concordIndexHtml = path.resolve(actRoot, 'index.html')

function resolveBuildId(isProdBuild) {
  if (!isProdBuild) {
    return 'dev'
  }

  try {
    return execSync('git rev-parse --short HEAD', {
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'ignore'],
    }).trim()
  } catch {
    return String(Date.now())
  }
}

function concordPagesIndexPlugin() {
  return {
    name: 'concord-pages-index',
    apply: 'build',
    buildStart() {
      fs.copyFileSync(concordPreviewHtml, concordIndexHtml)
    },
    buildEnd() {
      if (fs.existsSync(concordIndexHtml)) {
        fs.unlinkSync(concordIndexHtml)
      }
    },
  }
}

function concordDevIndexPlugin() {
  return {
    name: 'concord-dev-index',
    configureServer(server) {
      server.middlewares.use((req, _res, next) => {
        const reqPath = req.url?.split('?')[0] || ''
        if (reqPath === '/' || reqPath === '/index.html') {
          req.url = '/concord-preview.html'
        }
        next()
      })
    },
  }
}

function concordBuildIdPlugin(buildId, outDir) {
  return {
    name: 'concord-build-id',
    apply: 'build',
    closeBundle() {
      fs.writeFileSync(path.join(outDir, 'build-id.txt'), `${buildId}\n`, 'utf8')
    },
  }
}

/** Dev-сервер и production-сборка статического превью Concord UI. */
export default defineConfig(({ command, mode }) => {
  const isProdBuild = command === 'build' && mode === 'production'
  const pagesBase = process.env.CONCORD_PAGES_BASE || '/'
  const buildId = resolveBuildId(isProdBuild)
  const outDir = path.resolve(__dirname, 'dist/concord-pages')

  return {
    root: actRoot,
    publicDir: path.resolve(actRoot, 'public'),
    base: pagesBase,
    define: {
      __CONCORD_BUILD_ID__: JSON.stringify(buildId),
    },
    plugins: [
      concordDevIndexPlugin(),
      concordPagesIndexPlugin(),
      vue(),
      VitePWA({
        registerType: 'autoUpdate',
        injectRegister: null,
        includeAssets: [
          'pwa/favicon.ico',
          'pwa/favicon-96x96.png',
          'pwa/apple-touch-icon.png',
          'pwa/web-app-manifest-192x192.png',
          'pwa/web-app-manifest-512x512.png',
          'build-id.txt',
        ],
        manifest: {
          name: 'Concord — Согласование',
          short_name: 'Concord',
          description: 'Согласование документов',
          lang: 'ru',
          start_url: pagesBase,
          scope: pagesBase,
          display: 'standalone',
          orientation: 'portrait',
          theme_color: '#5e4db2',
          background_color: '#f8f5ff',
          icons: [
            {
              src: 'pwa/web-app-manifest-192x192.png',
              sizes: '192x192',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: 'pwa/web-app-manifest-192x192.png',
              sizes: '192x192',
              type: 'image/png',
              purpose: 'maskable',
            },
            {
              src: 'pwa/web-app-manifest-512x512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'any',
            },
            {
              src: 'pwa/web-app-manifest-512x512.png',
              sizes: '512x512',
              type: 'image/png',
              purpose: 'maskable',
            },
          ],
        },
        workbox: {
          globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,txt,webmanifest}'],
          globIgnores: ['**/pwa/favicon.svg'],
          navigateFallback: 'index.html',
          cleanupOutdatedCaches: true,
        },
        devOptions: {
          enabled: true,
        },
      }),
      ...(isProdBuild ? [concordBuildIdPlugin(buildId, outDir)] : []),
    ],
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
      watch: {
        usePolling: true,
        interval: 300,
      },
    },
    build: {
      outDir,
      emptyOutDir: true,
      rollupOptions: {
        input: {
          'concord-preview': concordPreviewHtml,
          index: concordIndexHtml,
        },
      },
    },
  }
})
