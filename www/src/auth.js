/**
 * Auth application entry point for ioc2rpz.gui
 * 
 * This file imports Vue 2, BootstrapVue, and Axios as npm packages
 * and initializes the authentication application.
 */

// Import Vue and plugins
import Vue from 'vue'
import { BootstrapVue, IconsPlugin } from 'bootstrap-vue'
import axios from 'axios'

// Import Bootstrap and BootstrapVue CSS
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'

// Make Vue available globally for compatibility
window.Vue = Vue

// Make axios available globally for compatibility with existing code
window.axios = axios

// Install BootstrapVue and IconsPlugin
Vue.use(BootstrapVue)
Vue.use(IconsPlugin)

// Set Vue production tip
Vue.config.productionTip = false

// Import the auth app configuration from io2auth.js
import { authAppConfig } from '../js/io2auth.js'

// Function to initialize Vue app
function initApp() {
  const appElement = document.getElementById('app')
  if (appElement) {
    new Vue(authAppConfig)
    console.log('Vue auth app initialized')
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
