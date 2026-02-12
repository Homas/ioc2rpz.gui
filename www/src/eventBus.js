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
 * 
 * @module eventBus
 * @package ioc2rpz.gui
 */

import mitt from 'mitt'

/**
 * Shared mitt event emitter instance
 * Used for cross-component communication in Vue 3
 * @type {mitt.Emitter}
 */
export const emitter = mitt()

/**
 * Event type constants for type safety and consistency
 * @constant {Object}
 */
export const EVENTS = {
  /** Event fired when a modal should be shown */
  SHOW_MODAL: 'bv::show::modal',
  /** Event fired when a modal should be hidden */
  HIDE_MODAL: 'bv::hide::modal',
  /** Event fired when a table should refresh its data */
  REFRESH_TABLE: 'bv::refresh::table'
}

/**
 * Show a modal by its ID
 * Emits SHOW_MODAL event which is handled by main.js to update modal visibility state
 * 
 * @param {string} modalId - The ID of the modal to show (e.g., 'mConfEditSrv')
 * @fires EVENTS.SHOW_MODAL
 * @example
 * showModal('mConfEditSrv'); // Opens the server edit modal
 */
export function showModal(modalId) {
  emitter.emit(EVENTS.SHOW_MODAL, modalId)
}

/**
 * Hide a modal by its ID
 * Emits HIDE_MODAL event which is handled by main.js to update modal visibility state
 * 
 * @param {string} modalId - The ID of the modal to hide
 * @fires EVENTS.HIDE_MODAL
 * @example
 * hideModal('mConfEditSrv'); // Closes the server edit modal
 */
export function hideModal(modalId) {
  emitter.emit(EVENTS.HIDE_MODAL, modalId)
}

/**
 * Refresh a table by its ID
 * Emits REFRESH_TABLE event which triggers the table's data provider to reload
 * 
 * @param {string} tableId - The ID of the table to refresh (e.g., 'io2tbl_servers')
 * @fires EVENTS.REFRESH_TABLE
 * @example
 * refreshTable('io2tbl_servers'); // Reloads server table data
 */
export function refreshTable(tableId) {
  emitter.emit(EVENTS.REFRESH_TABLE, tableId)
}

/**
 * Subscribe to modal show events
 * Used by main.js to listen for modal show requests
 * 
 * @param {Function} handler - Callback function that receives the modal ID
 * @returns {Function} Unsubscribe function to remove the listener
 * @example
 * const unsubscribe = onShowModal((modalId) => {
 *   console.log('Showing modal:', modalId);
 * });
 * // Later: unsubscribe();
 */
export function onShowModal(handler) {
  emitter.on(EVENTS.SHOW_MODAL, handler)
  return () => emitter.off(EVENTS.SHOW_MODAL, handler)
}

/**
 * Subscribe to modal hide events
 * Used by main.js to listen for modal hide requests
 * 
 * @param {Function} handler - Callback function that receives the modal ID
 * @returns {Function} Unsubscribe function to remove the listener
 */
export function onHideModal(handler) {
  emitter.on(EVENTS.HIDE_MODAL, handler)
  return () => emitter.off(EVENTS.HIDE_MODAL, handler)
}

/**
 * Subscribe to table refresh events
 * Used by main.js to listen for table refresh requests
 * 
 * @param {Function} handler - Callback function that receives the table ID
 * @returns {Function} Unsubscribe function to remove the listener
 */
export function onRefreshTable(handler) {
  emitter.on(EVENTS.REFRESH_TABLE, handler)
  return () => emitter.off(EVENTS.REFRESH_TABLE, handler)
}

/**
 * Default export of the emitter for direct access if needed
 * Prefer using the helper functions (showModal, hideModal, refreshTable) instead
 * @type {mitt.Emitter}
 */
export default emitter
