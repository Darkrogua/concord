import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

const proxyTarget = process.env.VITE_PROXY_TARGET || 'http://127.0.0.1:8080'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': {
        target: proxyTarget,
        changeOrigin: true,
      },
      '/sanctum': {
        target: proxyTarget,
        changeOrigin: true,
      },
    },
  },
})
