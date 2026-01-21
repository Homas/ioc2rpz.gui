/**
 * Event Bus for ioc2rpz.gui
 * 
 * This module provides a centralized event bus using mitt to replace
 * the Vue 2 $root.$emit pattern that is not supported in Vue 3.
 * 
 * It provides:
 * - A shared mitt event emitter instance
 * - Helper functions for modal show/hide operations
 * - Helper functions for table refresh operations
 * 
 * Usage:
 * - Import { emitter, showModal, hideModal, refreshTable } from './eventBus.js'
 * - Call showModal('modalId') instead of this.$root.$emit('bv::show::modal', 'modalId')
 * - Call refreshTable('tableId') instead of this.$root.$emit('bv::refresh::table', 'tableId')
 */

import mitt from 'mitt'

// Create the shared event emitter instance
export const emitter = mitt()

// Event type constants
export const EVENTS = {
  SHOW_MODAL: 'bv::show::modal',
  HIDE_MODAL: 'bv::hide::modal',
  REFRESH_TABLE: 'bv::refresh::table'
}

/**
 * Show a modal by its ID
 * @param {string} modalId - The ID of the modal to show
 */
export function showModal(modalId) {
  emitter.emit(EVENTS.SHOW_MODAL, modalId)
}

/**
 * Hide a modal by its ID
 * @param {string} modalId - The ID of the modal to hide
 */
export function hideModal(modalId) {
  emitter.emit(EVENTS.HIDE_MODAL, modalId)
}

/**
 * Refresh a table by its ID
 * @param {string} tableId - The ID of the table to refresh
 */
export function refreshTable(tableId) {
  emitter.emit(EVENTS.REFRESH_TABLE, tableId)
}

/**
 * Subscribe to modal show events
 * @param {Function} handler - Callback function that receives the modal ID
 * @returns {Function} Unsubscribe function
 */
export function onShowModal(handler) {
  emitter.on(EVENTS.SHOW_MODAL, handler)
  return () => emitter.off(EVENTS.SHOW_MODAL, handler)
}

/**
 * Subscribe to modal hide events
 * @param {Function} handler - Callback function that receives the modal ID
 * @returns {Function} Unsubscribe function
 */
export function onHideModal(handler) {
  emitter.on(EVENTS.HIDE_MODAL, handler)
  return () => emitter.off(EVENTS.HIDE_MODAL, handler)
}

/**
 * Subscribe to table refresh events
 * @param {Function} handler - Callback function that receives the table ID
 * @returns {Function} Unsubscribe function
 */
export function onRefreshTable(handler) {
  emitter.on(EVENTS.REFRESH_TABLE, handler)
  return () => emitter.off(EVENTS.REFRESH_TABLE, handler)
}

// Export default emitter for direct access if needed
export default emitter
