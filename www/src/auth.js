/**
 * Auth application entry point for ioc2rpz.gui
 * 
 * This file initializes the Vue 3 authentication application for:
 * - User login form
 * - Initial administrator creation
 * - Password validation feedback
 * 
 * The auth app is separate from the main app and only loads on the login page.
 * It uses bootstrap-vue-next for UI components and axios for API calls.
 * 
 * @module auth
 * @package ioc2rpz.gui
 */

// Import Vue 3 and plugins
import { createApp } from 'vue'
import { createBootstrap } from 'bootstrap-vue-next'
import axios from 'axios'

// Import Bootstrap and bootstrap-vue-next CSS
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue-next/dist/bootstrap-vue-next.css'

/**
 * Make axios available globally for compatibility with existing code
 * @global
 */
window.axios = axios

// Import the auth app configuration from io2auth.js
import { authAppConfig } from '../js/io2auth.js'

/**
 * Initialize the Vue 3 authentication app
 * 
 * Creates a minimal Vue app for the login/registration page.
 * The app instance is stored in window.io2auth_app for debugging.
 */
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
