/**
 * Auth application entry point for ioc2rpz.gui
 * 
 * This file imports Vue 3, bootstrap-vue-next, and Axios as npm packages
 * and initializes the authentication application.
 */

// Import Vue 3 and plugins
import { createApp } from 'vue'
import { createBootstrap } from 'bootstrap-vue-next'
import axios from 'axios'

// Import Bootstrap and bootstrap-vue-next CSS
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue-next/dist/bootstrap-vue-next.css'

// Make axios available globally for compatibility with existing code
window.axios = axios

// Import the auth app configuration from io2auth.js
import { authAppConfig } from '../js/io2auth.js'

// Function to initialize Vue 3 app
function initApp() {
  const appElement = document.getElementById('app')
  if (appElement) {
    // Create Vue 3 app instance using createApp()
    const appData = typeof authAppConfig.data === 'function' ? authAppConfig.data() : authAppConfig.data
    
    const vue3Config = {
      data() {
        return appData
      },
      mounted: authAppConfig.mounted,
      computed: authAppConfig.computed || {},
      methods: authAppConfig.methods || {}
    }
    
    const app = createApp(vue3Config)
    
    // Install bootstrap-vue-next plugin with all components
    app.use(createBootstrap({ components: true, directives: true }))
    
    // Mount the app
    const vm = app.mount('#app')
    
    // Make the app instance available globally for compatibility
    window.io2auth_app = vm
    
    // Make Vue available globally for compatibility
    window.Vue = { version: '3.x' }
    
    console.log('Vue 3 auth app initialized')
  } else {
    console.error('Could not find #app element')
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}
