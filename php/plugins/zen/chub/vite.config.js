import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'
import fs from 'node:fs'

const chub_node_modules = path.resolve(__dirname, 'node_modules')

export default defineConfig(({ command, mode }) => {
  const isDev = mode === 'development'
  const isServe = command === 'serve'
  const build_targets = [
    {
      output_name: 'flow_app',
      base_dir: path.resolve(__dirname, 'resources/flow_app'),
      js_entry: 'js/flow_app.js',
      scss_entry: 'scss/flow_app.scss'
    },
    {
      output_name: 'process_app',
      base_dir: path.resolve(__dirname, 'resources/process_app'),
      js_entry: 'js/process_app.js',
      scss_entry: 'scss/process_app.scss'
    },
    {
      output_name: 'documents_editor',
      base_dir: path.resolve(__dirname, 'resources/documents_editor'),
      js_entry: 'js/documents_editor.js',
      scss_entry: 'scss/documents_editor.scss'
    },
    {
      // Тема liner собирается тем же пайплайном и теми же зависимостями.
      output_name: 'liner',
      base_dir: path.resolve(__dirname, '../../../themes/liner/resources'),
      js_entry: 'js/app.js',
      scss_entry: 'scss/app.scss'
    }
  ]

  const inputs = {}
  for (const target of build_targets) {
    const js_path = path.resolve(target.base_dir, target.js_entry)
    const scss_path = path.resolve(target.base_dir, target.scss_entry)
    const js_key = `${target.output_name}/${target.js_entry}`
    const scss_key = `${target.output_name}/${target.scss_entry}`

    if (fs.existsSync(js_path)) {
      inputs[js_key] = js_path
    }
    if (fs.existsSync(scss_path)) {
      inputs[scss_key] = scss_path
    }
  }

  const liner_scss_path = path.resolve(__dirname, '../../../themes/liner/resources/scss')
  const liner_components_path = path.resolve(__dirname, '../../../themes/liner/resources/components')

  return {
    root: path.resolve(__dirname, 'resources'),
    css: {
      preprocessorOptions: {
        scss: {
          includePaths: [liner_scss_path],
        },
      },
    },
    /* Тема liner вне chub/resources: резолв npm из node_modules плагина. */
    resolve: {
      alias: [
        { find: '@liner-components', replacement: liner_components_path },
        { find: '@vueuse/core', replacement: path.join(chub_node_modules, '@vueuse/core') },
        { find: 'v-calendar', replacement: path.join(chub_node_modules, 'v-calendar') },
        { find: 'vue-screen-utils', replacement: path.join(chub_node_modules, 'vue-screen-utils') },
        { find: 'maska/vue', replacement: path.join(chub_node_modules, 'maska/dist/vue.js') },
        { find: 'maska', replacement: path.join(chub_node_modules, 'maska') },
        { find: '@panzoom/panzoom', replacement: path.join(chub_node_modules, '@panzoom/panzoom') },
        { find: /^swiper$/, replacement: path.join(chub_node_modules, 'swiper') },
        { find: 'swiper/css/navigation', replacement: path.join(chub_node_modules, 'swiper/modules/navigation.css') },
        { find: 'swiper/css/pagination', replacement: path.join(chub_node_modules, 'swiper/modules/pagination.css') },
        { find: 'swiper/css/grid', replacement: path.join(chub_node_modules, 'swiper/modules/grid.css') },
        { find: 'swiper/css', replacement: path.join(chub_node_modules, 'swiper/swiper.css') },
        { find: 'swiper/modules', replacement: path.join(chub_node_modules, 'swiper/modules/index.mjs') },
      ],
    },
    base: isServe ? '/' : '/plugins/zen/chub/assets/',
    plugins: [vue()],
    server: isServe ? {
      host: true,
      port: 5173,
      strictPort: true,
      origin: 'http://localhost:5173',
      cors: true,
      hmr: {
        host: 'localhost',
        clientPort: 5173,
        protocol: 'ws'
      }
    } : undefined,
    build: {
      outDir: path.resolve(__dirname, 'assets'),
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
