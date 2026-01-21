/**
 * Main application entry point for ioc2rpz.gui
 * 
 * This file imports Vue 2, BootstrapVue, and Axios as npm packages
 * and initializes the main application using the exported appConfig from io2.js.
 */

// Import Vue and plugins
import Vue from 'vue'
import { BootstrapVue, IconsPlugin } from 'bootstrap-vue'
import axios from 'axios'

// Import Bootstrap and BootstrapVue CSS
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue/dist/bootstrap-vue.css'

// Import appConfig and helper functions from io2.js
import {
  appConfig,
  // Helper functions (standalone, no Vue dependency)
  sleep,
  downloadAsPlainText,
  copyToClipboardID,
  checkHostIPNet,
  checkHostIP,
  checkIP,
  checkIPv4,
  checkIPv4Net,
  checkIPv6,
  checkHostName,
  checkHostNameNum,
  checkHostNameOnly,
  checkSourceURL,
  // Vue-instance dependent functions
  update_window_size,
  toggleUpdates,
  splitRpiDNSList,
  ImportIOC2RPZ
} from '../js/io2.js'

// Make Vue available globally for compatibility
window.Vue = Vue

// Make axios available globally for compatibility with existing code
window.axios = axios

// Expose helper functions to global scope for use in templates
// These functions are called from inline event handlers in PHP templates
window.sleep = sleep
window.downloadAsPlainText = downloadAsPlainText
window.copyToClipboardID = copyToClipboardID
window.checkHostIPNet = checkHostIPNet
window.checkHostIP = checkHostIP
window.checkIP = checkIP
window.checkIPv4 = checkIPv4
window.checkIPv4Net = checkIPv4Net
window.checkIPv6 = checkIPv6
window.checkHostName = checkHostName
window.checkHostNameNum = checkHostNameNum
window.checkHostNameOnly = checkHostNameOnly
window.checkSourceURL = checkSourceURL

// Expose Vue-instance dependent functions to global scope
window.update_window_size = update_window_size
window.toggleUpdates = toggleUpdates
window.splitRpiDNSList = splitRpiDNSList
window.ImportIOC2RPZ = ImportIOC2RPZ

// Install BootstrapVue and IconsPlugin
Vue.use(BootstrapVue)
Vue.use(IconsPlugin)

// Set Vue production tip
Vue.config.productionTip = false

// Function to initialize Vue app
function initApp() {
  const appElement = document.getElementById('app')
  if (appElement) {
    // Create Vue instance using imported appConfig
    // Store reference globally for functions that need access to the Vue instance
    const app = new Vue(appConfig)
    
    // Make the app instance available globally for Vue-instance dependent functions
    window.io2gui_app = app
    console.log('Vue main app initialized')
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
