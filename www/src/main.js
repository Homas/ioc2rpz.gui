/**
 * Main application entry point for ioc2rpz.gui
 * 
 * This file initializes the Vue 3 application with:
 * - Bootstrap-vue-next for UI components
 * - Axios for HTTP requests with CSRF token injection
 * - Event bus integration for modal and table control
 * - Global helper function exposure for template usage
 * 
 * Architecture:
 * - Imports appConfig from io2.js containing Vue component definition
 * - Creates Vue 3 app using createApp() instead of Vue 2's new Vue()
 * - Manages modal visibility state via reactive modalVisibility object
 * - Sets up event bus listeners for cross-component communication
 * 
 * @module main
 * @package ioc2rpz.gui
 */

// Import Vue 3 and plugins
import { createApp, reactive, ref, nextTick } from 'vue'
import { createBootstrap } from 'bootstrap-vue-next'
import axios from 'axios'

// Import Bootstrap and bootstrap-vue-next CSS
import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue-next/dist/bootstrap-vue-next.css'

// Import event bus for modal and table control
import { onShowModal, onHideModal, onRefreshTable, showModal, hideModal, refreshTable } from './eventBus.js'

// Import appConfig and helper functions from io2.js
import {
  appConfig,
  // Helper functions (standalone, no Vue dependency)
  sleep,
  downloadAsPlainText,
  copyToClipboardID,
  getLocationOrigin,
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

/**
 * Make axios available globally for compatibility with existing code
 * @global
 */
window.axios = axios

/**
 * Axios request interceptor for CSRF token injection
 * 
 * Automatically adds CSRF token to all state-changing requests (POST, PUT, DELETE, PATCH).
 * The token is retrieved from window.csrfToken which is set by PHP.
 * 
 * Handles different data formats:
 * - FormData: Appends csrf_token field
 * - Object: Adds csrf_token property
 * - JSON string: Parses, adds token, re-stringifies
 * - No data: Creates object with csrf_token
 */
axios.interceptors.request.use(function (config) {
  // Add CSRF token to POST, PUT, DELETE, PATCH requests
  if (['post', 'put', 'delete', 'patch'].includes(config.method.toLowerCase())) {
    // Get CSRF token from global variable set by PHP
    const csrfToken = window.csrfToken || '';
    
    if (config.data instanceof FormData) {
      config.data.append('csrf_token', csrfToken);
    } else if (typeof config.data === 'object' && config.data !== null) {
      config.data.csrf_token = csrfToken;
    } else if (typeof config.data === 'string') {
      // Handle JSON string data
      try {
        const data = JSON.parse(config.data);
        data.csrf_token = csrfToken;
        config.data = JSON.stringify(data);
      } catch (e) {
        // If not JSON, append as query parameter
        config.data = config.data + '&csrf_token=' + encodeURIComponent(csrfToken);
      }
    } else {
      // No data, create object with CSRF token
      config.data = { csrf_token: csrfToken };
    }
  }
  return config;
}, function (error) {
  return Promise.reject(error);
});

/**
 * Expose helper functions to global scope for use in templates
 * These functions are called from inline event handlers in PHP templates
 * @global
 */
window.sleep = sleep
window.downloadAsPlainText = downloadAsPlainText
window.getLocationOrigin = getLocationOrigin
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

/**
 * Expose Vue-instance dependent functions to global scope
 * These require access to the Vue app instance
 * @global
 */
window.update_window_size = update_window_size
window.toggleUpdates = toggleUpdates
window.splitRpiDNSList = splitRpiDNSList
window.ImportIOC2RPZ = ImportIOC2RPZ

/**
 * Expose event bus functions globally for use in templates and other scripts
 * @global
 */
window.showModal = showModal
window.hideModal = hideModal
window.refreshTable = refreshTable

/**
 * Modal visibility state management
 * 
 * In bootstrap-vue-next, modals are controlled via v-model (boolean refs).
 * This reactive object tracks visibility state for all modals by their ID.
 * 
 * @type {Object.<string, boolean>}
 * @example
 * modalVisibility.mConfEditSrv = true; // Shows the server edit modal
 */
const modalVisibility = reactive({})

/**
 * Get or create a modal visibility ref
 * Creates the visibility state if it doesn't exist (defaults to false/hidden)
 * 
 * @param {string} modalId - The modal ID
 * @returns {boolean} The current visibility state
 */
function getModalVisibility(modalId) {
  if (!(modalId in modalVisibility)) {
    modalVisibility[modalId] = false
  }
  return modalVisibility[modalId]
}

/**
 * Set modal visibility state
 * Updates the reactive state which triggers Vue to show/hide the modal
 * 
 * @param {string} modalId - The modal ID
 * @param {boolean} visible - Whether the modal should be visible
 */
function setModalVisibility(modalId, visible) {
  modalVisibility[modalId] = visible
}

/**
 * Expose modal visibility functions globally
 * @global
 */
window.getModalVisibility = getModalVisibility
window.setModalVisibility = setModalVisibility
window.modalVisibility = modalVisibility

/**
 * Initialize the Vue 3 application
 * 
 * This function:
 * 1. Finds the #app element in the DOM
 * 2. Converts appConfig data to Vue 3 reactive format
 * 3. Creates the Vue 3 app with bootstrap-vue-next
 * 4. Sets up event bus listeners for modal and table control
 * 5. Mounts the app to the DOM
 * 
 * The app instance is stored in window.io2gui_app for global access.
 */
function initApp() {
  const appElement = document.getElementById('app')
  if (appElement) {
    // Create Vue 3 app instance using createApp()
    // Convert appConfig data to reactive for Vue 3
    const appData = typeof appConfig.data === 'function' ? appConfig.data() : appConfig.data
    
    // Add modal visibility state to app data
    // This allows templates to use v-model="modalVisibility.modalId" for modal control
    appData.modalVisibility = modalVisibility
    
    // Create the app configuration for Vue 3
    const vue3Config = {
      data() {
        return appData
      },
      mounted: appConfig.mounted,
      computed: appConfig.computed || {},
      methods: {
        ...appConfig.methods,
        // Add modal control methods that can be called from templates
        showModalById(modalId) {
          setModalVisibility(modalId, true)
        },
        hideModalById(modalId) {
          setModalVisibility(modalId, false)
        }
      }
    }
    
    // Create the Vue 3 app
    const app = createApp(vue3Config)
    
    // Install bootstrap-vue-next plugin with all components and directives
    app.use(createBootstrap({ components: true, directives: true }))
    
    // Mount the app to the DOM element
    // Note: Vue 3 doesn't use 'el' option, we mount explicitly
    const vm = app.mount('#app')
    
    // Make the app instance available globally for Vue-instance dependent functions
    window.io2gui_app = vm
    
    // Make Vue available globally for compatibility (some templates may reference it)
    window.Vue = { version: '3.x' }
    
    // Set up event bus listeners for modal control
    // When showModal is called, update the visibility state
    onShowModal((modalId) => {
      console.log('Event bus: showing modal', modalId)
      setModalVisibility(modalId, true)
      // Force Vue to update
      nextTick(() => {
        // Trigger reactivity update
        vm.$forceUpdate && vm.$forceUpdate()
      })
    })
    
    // When hideModal is called, update the visibility state
    onHideModal((modalId) => {
      console.log('Event bus: hiding modal', modalId)
      setModalVisibility(modalId, false)
      nextTick(() => {
        vm.$forceUpdate && vm.$forceUpdate()
      })
    })
    
    // Set up event bus listener for table refresh
    // In bootstrap-vue-next, tables can be refreshed by calling refresh() on the table ref
    onRefreshTable((tableId) => {
      console.log('Event bus: refreshing table', tableId)
      // Try to find the table ref and call refresh
      if (vm.$refs && vm.$refs[tableId]) {
        const tableRef = vm.$refs[tableId]
        if (typeof tableRef.refresh === 'function') {
          tableRef.refresh()
        } else if (typeof tableRef.refreshTblKeepPage === 'function') {
          tableRef.refreshTblKeepPage()
        }
      }
    })
    
    console.log('Vue 3 main app initialized with event bus integration')
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
