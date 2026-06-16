import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

// Check if building in dev mode (enables Vue devtools)
const isDevMode = process.env.BUILD_MODE === 'dev'

export default defineConfig({
  plugins: [vue()],
  
  root: 'www',
  
  // Enable Vue devtools in dev mode
  define: {
    __VUE_PROD_DEVTOOLS__: isDevMode,
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: isDevMode
  },
  
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    // Disable minification in dev mode for easier debugging.
    // Vite 8 (Rolldown) uses the Oxc minifier; 'esbuild' is no longer bundled.
    minify: isDevMode ? false : 'oxc',
    // Generate source maps in dev mode
    sourcemap: isDevMode,
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
