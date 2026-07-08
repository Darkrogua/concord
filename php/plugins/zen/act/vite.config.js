import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'node:path'
import fs from 'node:fs'
import { execSync } from 'node:child_process'

function resolveBuildId(isProdBuild) {
  if (!isProdBuild) {
    return 'dev'
  }

  let git = 'unknown'
  try {
    git = execSync('git rev-parse --short HEAD', {
      encoding: 'utf8',
      stdio: ['pipe', 'pipe', 'ignore'],
    }).trim()
  } catch {
    // git недоступен в контейнере — только timestamp
  }

  return `${git}-${Date.now()}`
}

function actBuildInfoPlugin(buildId, assetsDir) {
  return {
    name: 'act-build-info',
    apply: 'build',
    closeBundle() {
      const payload = {
        buildId,
        builtAt: new Date().toISOString(),
      }
      fs.writeFileSync(
        path.join(assetsDir, 'build-info.json'),
        `${JSON.stringify(payload, null, 2)}\n`,
        'utf8'
      )
    },
  }
}

const actRoot = path.resolve(__dirname, 'resources')
const assetsBase = '/plugins/zen/act/assets/'

const buildTargets = [
  {
    output_name: 'act_app',
    base_dir: path.resolve(__dirname, 'resources/act_app'),
    js_entry: 'js/act_app.js',
    scss_entry: 'scss/act_app.scss'
  }
]

export default defineConfig(({ command, mode }) => {
  const isDev = mode === 'development'
  const isServe = command === 'serve'
  const isProdBuild = command === 'build' && mode === 'production'
  const assetsDir = path.resolve(__dirname, 'assets')
  const buildId = resolveBuildId(isProdBuild)

  const inputs = {}
  for (const target of buildTargets) {
    const jsPath = path.resolve(target.base_dir, target.js_entry)
    const scssPath = path.resolve(target.base_dir, target.scss_entry)
    const jsKey = `${target.output_name}/${target.js_entry}`
    const scssKey = `${target.output_name}/${target.scss_entry}`
    if (fs.existsSync(jsPath)) {
      inputs[jsKey] = jsPath
    }
    if (fs.existsSync(scssPath)) {
      inputs[scssKey] = scssPath
    }
  }

  const publicOrigin = process.env.ACT_VITE_ORIGIN || 'http://localhost:5174'
  const publicPort = (() => {
    try {
      return new URL(publicOrigin).port || '5174'
    } catch {
      return '5174'
    }
  })()

  return {
    root: actRoot,
    publicDir: path.resolve(actRoot, 'public'),
    base: isServe ? '/' : assetsBase,
    define: {
      __ACT_BUILD_ID__: JSON.stringify(buildId),
    },
    plugins: [
      vue(),
      ...(isProdBuild ? [actBuildInfoPlugin(buildId, assetsDir)] : []),
      VitePWA({
        registerType: 'autoUpdate',
        injectRegister: null,
        includeAssets: [
          'pwa/favicon.ico',
          'pwa/favicon-96x96.png',
          'pwa/apple-touch-icon.png',
          'pwa/web-app-manifest-192x192.png',
          'pwa/web-app-manifest-512x512.png',
        ],
        manifest: {
          name: 'Акт',
          short_name: 'Акт',
          description: 'Приложение для работы с актами',
          lang: 'ru',
          start_url: '/app',
          scope: '/',
          display: 'standalone',
          orientation: 'portrait',
          theme_color: '#5288c1',
          background_color: '#efeff4',
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
          globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
          globIgnores: ['**/pwa/favicon.svg', '**/build-info.json'],
          navigateFallback: null,
          cleanupOutdatedCaches: true,
          runtimeCaching: [
            {
              urlPattern: /\/act\.api\//,
              handler: 'NetworkOnly',
            },
          ],
        },
        devOptions: {
          enabled: true,
        },
      }),
    ],
    server: isServe
      ? {
          host: true,
          port: 5173,
          strictPort: true,
          origin: publicOrigin,
          cors: true,
          hmr: {
            host: 'localhost',
            clientPort: Number(publicPort) || 5174,
            protocol: 'ws'
          }
        }
      : undefined,
    build: {
      outDir: assetsDir,
      emptyOutDir: false,
      assetsDir: 'build',
      manifest: true,
      sourcemap: isDev,
      minify: isDev ? false : 'esbuild',
      rollupOptions: {
        input: inputs
      }
    }
  }
})
