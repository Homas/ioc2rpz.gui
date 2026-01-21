import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  
  root: 'www',
  
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'www/src/main.js'),
        auth: resolve(__dirname, 'www/src/auth.js')
      },
      output: {
        entryFileNames: '[name].[hash].js',
        chunkFileNames: '[name].[hash].js',
        assetFileNames: '[name].[hash][extname]'
      }
    }
  },
  
  resolve: {
    alias: {
      '@': resolve(__dirname, 'www/src'),
      // Use Vue 3 full build with template compiler for in-DOM templates
      'vue': 'vue/dist/vue.esm-bundler.js'
    }
  },
  
  server: {
    proxy: {
      '/io2data.php': 'http://localhost:8080',
      '/io2auth.php': 'http://localhost:8080'
    }
  }
})
