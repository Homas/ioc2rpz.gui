/**
 * io2auth.js - Authentication module for ioc2rpz.gui
 * 
 * This file exports the Vue app configuration for the authentication page.
 * The Vue instance is created in www/src/auth.js
 * 
 * Features:
 * - User login form handling
 * - Initial administrator creation
 * - Password strength validation
 * - Username format validation
 * - Error message display
 * 
 * @module io2auth
 * @package ioc2rpz.gui
 */

/**
 * Escapes HTML special characters to prevent XSS attacks
 * Used to sanitize user-provided messages before display
 * 
 * @param {string} str - The string to escape
 * @returns {string} The escaped string safe for HTML insertion
 */
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  const htmlEscapes = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };
  return String(str).replace(/[&<>"']/g, char => htmlEscapes[char]);
}

/**
 * Vue app configuration for the authentication application
 * 
 * Contains:
 * - data: Form field values and modal state
 * - methods: Validation, sign-in, and user creation functions
 * 
 * @type {Object}
 */
export const authAppConfig = {
  el: "#app",
  
  /**
   * Reactive data for the auth form
   */
  data: {
    /** Username input field value */
    ftUNameProf: '',
    /** Password input field value */
    ftUPwd: '',
    /** Password confirmation input field value */
    ftUpwdConf: '',
    /** Info message modal visibility state */
    mInfoMSGvis: false,
    /** Info message text to display */
    msgInfoMSG: '',
  },
  
  methods: {
    /**
     * Validates a username field
     * Username must be 3+ characters containing only alphanumeric, dots, hyphens, underscores
     * 
     * @param {string} vrbl - Name of the data property to validate
     * @returns {boolean|null} true if valid, false if invalid, null if empty
     */
    validateName: function(vrbl){
      return (this.$data[vrbl].length > 2 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    /**
     * Validates password strength
     * Password must be either:
     * - 8+ chars with uppercase, lowercase, number, and special character
     * - OR 16+ characters (passphrase)
     * 
     * @param {string} pass1 - Name of the password data property
     * @returns {boolean|null} true if valid, false if invalid, null if empty
     */
    validatePass: function(pass1){
      return ((this.$data[pass1].length > 7 && /([0-9])/.test(this.$data[pass1]) && /([a-z])/.test(this.$data[pass1]) && /([A-Z])/.test(this.$data[pass1]) && /([!,%,&,@,#,$,^,*,?,_,~,\,,\.])/.test(this.$data[pass1])) || this.$data[pass1].length > 15) ? true : this.$data[pass1].length == 0 ? null:false;
    },

    /**
     * Validates that two password fields match
     * 
     * @param {string} pass1 - Name of the first password data property
     * @param {string} pass2 - Name of the confirmation password data property
     * @returns {boolean} true if passwords match
     */
    validatePassMatch: function(pass1, pass2){
      return this.$data[pass1] == this.$data[pass2] ? true : false;
    },

    /**
     * Handles initial administrator creation
     * Validates all fields and sends POST request to create admin user
     * Redirects to home page on success
     * 
     * @param {Event} e - Form submit event
     */
    createUser: function(e){
      e.preventDefault();
      // Validate all fields before submission
      if (!this.validateName('ftUNameProf')) {
        this.showInfo('Username must be at least 3 characters and contain only letters, numbers, dots, hyphens, and underscores', 5);
        return;
      }
      if (!this.validatePass('ftUPwd')) {
        this.showInfo('Password must be either: 8+ chars with uppercase, lowercase, number, and special char (!%&@#$^*?_~,.) OR 16+ chars', 5);
        return;
      }
      if (!this.validatePassMatch('ftUPwd', 'ftUpwdConf')) {
        this.showInfo('Passwords do not match', 3);
        return;
      }
      var data={login: this.ftUNameProf, pwd: this.ftUPwd, pwdConf: this.ftUpwdConf};
      var obj=this;
      axios.post('/io2auth.php/createadmin',data).then(function (response) {
        if (response.data.status == "createSuccess"){
          obj.showInfo(response.data.description,3);
          setTimeout(function(){
            window.location.href='/'; //???
          }, 3 * 1000);
        }else{
          obj.showInfo(response.data.description,3);
        };
      }).catch(function (error){
        obj.showInfo('Unknown error!!!',3);
      })
   },

    /**
     * Handles user sign-in
     * Sends credentials to server and redirects on success
     * Displays error message on failure
     * 
     * @param {Event} e - Form submit event
     */
    signIn: function(e){
//      if (this.$root.ftUNameProf.length == 0) e.preventDefault(); //TODO
      e.preventDefault();
      var data={login: this.ftUNameProf, pwd: this.ftUPwd};
      var obj=this;
      axios.post('/io2auth.php/signin',data).then(function (response) {
        if (response.data.status == "authSuccess"){
          if (~window.location.href.indexOf('/io2auth.php')) window.location.href='/'; else window.location.reload(true);
        }else{
          obj.showInfo(response.data.description,3);
        };
      }).catch(function (error){
        obj.showInfo('Unknown error!!!',3);
      })
    },

    /**
     * Displays a temporary info message to the user
     * Message is automatically hidden after the specified time
     * 
     * @param {string} msg - Message to display (will be HTML-escaped)
     * @param {number} time - Duration in seconds to show the message
     */
    showInfo: function (msg,time) {
      var self=this;
      this.msgInfoMSG=escapeHtml(msg);
      this.mInfoMSGvis=true;
      setTimeout(function(){
        self.mInfoMSGvis = false; // Use your variable name
      }, time * 1000);
    },

  }
};

/**
 * Default export for convenience
 * Allows importing as: import authAppConfig from './io2auth.js'
 */
export default authAppConfig;
