import { defineConfig } from 'vitest/config'
import { resolve } from 'path'

// Vitest configuration for ioc2rpz.gui.
//
// This config is intentionally separate from vite.config.js: the test runner
// does not use the Vue plugin or the app build inputs, it just needs to be able
// to import the pure logic exported from www/js/io2.js (and the modules it
// pulls in, e.g. www/src/eventBus.js).
export default defineConfig({
  resolve: {
    alias: {
      // Mirror the app aliases so importing io2.js and its dependencies works.
      '@': resolve(__dirname, 'www/src'),
      'io2': resolve(__dirname, 'www/js/io2.js'),
      // Use the Vue build with the template compiler, matching vite.config.js.
      'vue': 'vue/dist/vue.esm-bundler.js'
    }
  },
  test: {
    // jsdom provides the browser globals (window, document) that www/js/io2.js
    // references at module load time, so its exports can be imported directly.
    environment: 'jsdom',
    globals: true,
    // Pick up tests co-located under www/js/__tests__ as well as a top-level
    // tests/ directory.
    include: [
      'tests/**/*.{test,spec}.{js,mjs}',
      'www/js/__tests__/**/*.{test,spec}.{js,mjs}'
    ]
  }
})
