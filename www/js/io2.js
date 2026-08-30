/**
 * ioc2rpz.gui - Main Application JavaScript
 * 
 * This file contains:
 * - Helper functions for validation and utilities
 * - Vue-instance dependent functions
 * - Vue app configuration (appConfig) for use with main.js
 * 
 * ES Module format for Vite bundling
 * 
 * @module io2
 * @package ioc2rpz.gui
 */

// Import event bus functions for Vue 3 compatibility
// Replaces Vue 2's $root.$emit pattern
import { showModal, refreshTable } from '../src/eventBus.js'

// ============================================
// Helper Functions (standalone, no Vue dependency)
// ============================================

/**
 * Pauses execution for a specified duration
 * Useful for adding delays between async operations
 * 
 * @param {number} ms - Milliseconds to sleep
 * @returns {Promise<void>} Promise that resolves after the delay
 * @example
 * await sleep(1000); // Wait 1 second
 */
function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Downloads data as a plain text file
 * Creates a temporary anchor element to trigger browser download
 * 
 * @param {string} fileName - Name for the downloaded file
 * @param {string} Data - Text content to download
 */
function downloadAsPlainText(fileName, Data) {
  var dataStr = "data:text/plain;base64," + btoa(Data);
  var downloadAnchorNode = document.createElement('a');
  downloadAnchorNode.setAttribute("href", dataStr);
  downloadAnchorNode.setAttribute("download", fileName);
  document.body.appendChild(downloadAnchorNode);
  downloadAnchorNode.click();
  downloadAnchorNode.remove();
}

/**
 * Copies the content of an input element to clipboard
 * Uses the deprecated execCommand API (still widely supported)
 * 
 * @param {string} id - DOM element ID to copy from
 */
function copyToClipboardID(id) {
  var el = document.getElementById(id);
  if (el && navigator.clipboard) {
    navigator.clipboard.writeText(el.value);
  } else if (el) {
    el.select();
    document.execCommand('copy');
  }
}

/**
 * Gets the current page's origin (protocol + host + port)
 * 
 * @returns {string} The window location origin
 */
function getLocationOrigin() {
  return window.location.origin;
}

/**
 * Validates if a string is a valid host, IP address, or network CIDR
 * 
 * @param {string} V - Value to validate
 * @returns {boolean} True if valid hostname, IPv4, IPv4/CIDR, IPv6, or hostname
 */
function checkHostIPNet(V) {
  return checkIPv4(V) || checkIPv4Net(V) || checkIPv6(V) || checkHostName(V);
}

/**
 * Validates if a string is a valid host or IP address (no CIDR)
 * 
 * @param {string} V - Value to validate
 * @returns {boolean} True if valid hostname, IPv4, or IPv6
 */
function checkHostIP(V) {
  return checkIPv4(V) || checkIPv6(V) || checkHostName(V);
}

/**
 * Validates if a string is a valid IP address (IPv4 or IPv6)
 * 
 * @param {string} IP - IP address to validate
 * @returns {boolean} True if valid IPv4 or IPv6 address
 */
function checkIP(IP) {
  return checkIPv4(IP) || checkIPv6(IP);
}

/**
 * Validates if a string is a valid IPv4 address
 * 
 * @param {string} IP - IP address to validate
 * @returns {boolean} True if valid IPv4 address (0.0.0.0 - 255.255.255.255)
 */
function checkIPv4(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(IP);
}

/**
 * Validates if a string is a valid IPv4 network in CIDR notation
 * 
 * @param {string} IP - Network to validate (e.g., "192.168.1.0/24")
 * @returns {boolean} True if valid IPv4/CIDR notation
 */
function checkIPv4Net(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/([0-9]|[1-2][0-9]|3[0-2])$/.test(IP);
}

/**
 * Validates if a string is a valid IPv6 address
 * Supports full, compressed, and mixed notation with optional CIDR
 * 
 * @param {string} IP - IPv6 address to validate
 * @returns {boolean} True if valid IPv6 address
 */
function checkIPv6(IP) {
  return /^(([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))(\/[0-9]+)?$/.test(IP);
}

/**
 * Validates if a string is a valid fully qualified domain name (FQDN)
 * Must be 4-253 characters with valid labels and TLD
 * 
 * @param {string} HN - Hostname to validate
 * @returns {boolean} True if valid FQDN
 */
function checkHostName(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z]{2,63}$)/.test(HN);
}

/**
 * Validates if a string is a valid hostname with numeric TLD allowed
 * Similar to checkHostName but allows numeric TLDs (e.g., for RPZ zones)
 * 
 * @param {string} HN - Hostname to validate
 * @returns {boolean} True if valid hostname with alphanumeric TLD
 */
function checkHostNameNum(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z0-9]{2,63}$)/.test(HN);
}

/**
 * Validates if a string is a valid hostname (without requiring TLD)
 * Used for local hostnames and short names
 * 
 * @param {string} HN - Hostname to validate
 * @returns {boolean} True if valid hostname format
 */
function checkHostNameOnly(HN) {
  return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.?)+$)/.test(HN);
}

/**
 * Validates if a string is a valid source URL
 * Accepts http://, https://, ftp://, file:, and shell: protocols
 * 
 * @param {string} HN - URL to validate
 * @returns {boolean} True if URL starts with valid protocol
 */
function checkSourceURL(HN) {
  return /^(http:\/\/|https:\/\/|ftp:\/\/|file:|shell:)/.test(HN);
}

/**
 * Characters NOT allowed in a fetched source/allowlist URL (http, https, ftp).
 *
 * This is the RFC 3986 URI character set - unreserved, gen-delims, sub-delims and the
 * "%" of a percent-escape - plus a literal space.
 *
 * Space and "%" are deliberately allowed. Both used to be stripped, silently:
 * "https://host/a b.txt" became "https://host/ab.txt", and, worse, the correct encoding
 * "https://host/a%20b.txt" became "https://host/a20b.txt" because "%" was not in the set.
 * That corrupted every percent-escape (%2F, %3D, %26) with no error shown, producing a
 * source that fetched the wrong path, or 404'd only at publish time.
 *
 * Excluded on purpose: the double quote, backslash, angle brackets, braces, pipe, caret,
 * backtick and control characters. The double quote is the important one - these values
 * are written verbatim into a quoted Erlang string in the generated ioc2rpz.conf, so a
 * quote would terminate it. Keeping that exclusion is what makes widening the set to
 * include space and "%" safe.
 *
 * Used only with String.prototype.replace(), which resets lastIndex, so sharing one
 * global-flagged regex across calls is safe. Do not use it with .test().
 */
const SOURCE_URL_DISALLOWED = /[^A-Za-z0-9\-._~:/?#[\]@!$&'()*+,;=% ]/g;

/**
 * Characters NOT allowed in a local file path (cert, key, CA cert).
 *
 * Mirrors the backend validateFilePath() exactly: letters, digits, dot, underscore,
 * hyphen, slash. The previous pattern wrote the hyphen unescaped as
 * "[^A-Za-z0-9/=:\?#.-_&]", which is read as the range "." to "_" and silently permitted
 * ":", ";", "<", ">", "@", "[", "]", "\" and "^". Those passed the form and were then
 * rejected by the backend, so the user got a server-side "Invalid certificate file path"
 * for input the field had accepted.
 */
const LOC_FILE_DISALLOWED = /[^A-Za-z0-9._/-]/g;

/**
 * Escapes HTML special characters to prevent XSS attacks
 * @param {string} str - The string to escape
 * @returns {string} - The escaped string safe for HTML insertion
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

// Export helper functions for use in main.js and templates
export {
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
  escapeHtml
};

// ============================================
// Vue-instance dependent functions
// These functions require access to the Vue app instance (passed as parameter)
// ============================================

/**
 * Updates window size related properties on the Vue instance
 * Called on window resize to adjust UI elements
 * 
 * @param {Object} obj - Vue instance or component with $refs
 */
export function update_window_size(obj) {
  // Fallback to global app instance if $refs is undefined
  if (obj.$refs === undefined) { obj = window.io2gui_app; }
  obj.logs_pp = window.innerHeight > 500 && window.innerWidth > 1000 ? Math.floor((window.innerHeight - 350) / 28) : 5;
  obj.logs_height = window.innerHeight > 400 ? (window.innerHeight - 240) : 150;
  obj.windowInnerWidth = window.innerWidth;
  splitRpiDNSList(obj);
}

/**
 * Toggles the publish updates state
 * Stores state in localStorage for persistence across sessions
 * 
 * @param {*} srv - Server parameter (unused but kept for API compatibility)
 * @param {Object} obj - Vue instance
 * @param {boolean} state - New state for publishUpdates flag
 */
export function toggleUpdates(srv, obj, state) {
  window.localStorage.publishUpdates = state;
  obj.publishUpdates = state;
}

/**
 * Splits the RpiDNS list into chunks for dashboard display
 * Creates rows of cards based on available container width
 * 
 * @param {Object} obj - Vue instance with RpiDNSList and $refs.RpiDNSCards
 */
export function splitRpiDNSList(obj) {
  obj.RpiDNSListDash = [];
  var i, j, chunk = parseInt((obj.$refs.RpiDNSCards.offsetWidth == 0 ? (window.innerWidth - 165) : obj.$refs.RpiDNSCards.offsetWidth - 50) / 315);
  chunk = chunk > 0 ? chunk : 1;
  for (i = 0, j = obj.RpiDNSList.length; i < j; i += chunk) {
    obj.RpiDNSListDash.push(obj.RpiDNSList.slice(i, i + chunk));
  }
}

/**
 * Counts the top-level, comma-separated fields inside the body of an Erlang
 * tuple (the content between `{tag,{` and the closing `}}`).
 *
 * Commas nested inside double-quoted strings, `[...]` lists, or `{...}` tuples
 * are ignored, so only the arity of the outermost tuple is counted. An empty
 * or whitespace-only body yields 0.
 *
 * Used by the config reader to report an accurate field count when a srv/rpz
 * tuple matches neither the legacy nor the attribution-aware arity.
 *
 * @param {string} body - inner content of the tuple (without the wrapping braces)
 * @returns {number} number of top-level fields
 */
export function countErlTupleFields(body) {
  if (body == null) return 0;
  body = String(body);
  var depth = 0, inStr = false, commas = 0, hasContent = false;
  for (var i = 0; i < body.length; i++) {
    var c = body[i];
    if (c === '"') { inStr = !inStr; hasContent = true; }
    else if (inStr) { continue; }
    else if (c === '[' || c === '{') { depth++; hasContent = true; }
    else if (c === ']' || c === '}') { if (depth > 0) depth--; }
    else if (c === ',' && depth === 0) { commas++; }
    else if (!/\s/.test(c)) { hasContent = true; }
  }
  return hasContent ? commas + 1 : 0;
}

/**
 * Splits the body of an Erlang tuple into its top-level fields.
 *
 * Uses the same scanner rules as countErlTupleFields(): commas nested inside
 * double-quoted strings, `[...]` lists, or `{...}` tuples do not split a field, so
 * only the outermost tuple is split. Each returned field is trimmed. An empty or
 * whitespace-only body yields an empty array, matching countErlTupleFields()
 * returning 0 for the same input.
 *
 * The config reader needs the fields themselves (not just their count) because the
 * srv and rpz tuples carry optional TRAILING elements - the `TrackSources` atom and
 * `{rate_limit,[...]}` - which the server accepts in either order. Those cannot be
 * captured positionally by a regex (a `{rate_limit,...}` tuple contains the very
 * commas, braces and brackets a positional group has to exclude), so the reader
 * matches the mandatory fields by shape and then classifies the remaining tokens by
 * tag.
 *
 * @param {string} body - inner content of the tuple (without the wrapping braces)
 * @returns {string[]} top-level fields, in order, each trimmed
 */
export function splitErlTupleFields(body) {
  if (body == null) return [];
  body = String(body);
  if (!/\S/.test(body)) return [];
  var fields = [], depth = 0, inStr = false, start = 0;
  for (var i = 0; i < body.length; i++) {
    var c = body[i];
    if (c === '"') { inStr = !inStr; }
    else if (inStr) { continue; }
    else if (c === '[' || c === '{') { depth++; }
    else if (c === ']' || c === '}') { if (depth > 0) depth--; }
    else if (c === ',' && depth === 0) {
      fields.push(body.slice(start, i).trim());
      start = i + 1;
    }
  }
  fields.push(body.slice(start).trim());
  return fields;
}

/**
 * Largest accepted rate limit window, in seconds (24 hours).
 *
 * The window is not just a policy number: it is stored with every rate limit entry in
 * the server's `rate_limits` ETS table and decides when that entry is swept. An absurd
 * window (a fumbled digit, a value pasted in milliseconds) would keep every entry alive
 * for as long as it lasts, turning the table into a slow config-driven memory leak. A
 * day is far beyond any legitimate DNS rate limit window.
 */
export const RL_WINDOW_MAX = 86400;

/**
 * Rate limit options the ioc2rpz server accepts, with the level each one is valid at
 * and its legal range.
 *
 * `max_unknown_requests` is server-level only: it limits the aggregate {IP} bucket
 * (unknown zone, unsupported qtype, wrong class), and a request counted there never
 * resolved to a zone, so there is no zone config to read it from. The server logs and
 * ignores it when it appears on an rpz record.
 *
 * `window` must be > 0 and is capped at RL_WINDOW_MAX. The maximums may be 0, which
 * means "refuse every request in that bucket", and are not capped: a large threshold
 * only makes the limiter permissive, it does not retain anything.
 */
export const RL_OPTIONS = {
  window: { min: 1, max: RL_WINDOW_MAX, srv: true, rpz: true },
  max_requests: { min: 0, srv: true, rpz: true },
  max_unknown_requests: { min: 0, srv: true, rpz: false }
};

/**
 * ioc2rpz compile-time rate limit defaults (include/ioc2rpz.hrl).
 *
 * The last step of the resolution chain zone -> server -> built-in default. Shown in
 * the editors as the inherited value; never written to a configuration file, because
 * writing it would convert "inherit" into "explicitly set".
 */
export const RL_DEFAULTS = {
  window: 60,                 // ?RATE_LIMIT_WINDOW (60000 ms)
  max_requests: 6,            // ?MAX_REQUESTS_PER_WINDOW
  max_unknown_requests: 1     // ?MAX_UNKNOWN_REQUESTS_PER_WINDOW
};

/**
 * Parses a `{rate_limit,[{opt,N},...]}` tuple into an options object.
 *
 * Every option is independently optional, and an empty option list is accepted as
 * "nothing set". Returns an error instead of a partial result when the element is
 * malformed, names an unknown option, repeats an option, uses a non-integer value,
 * places a server-only option on an rpz record, or carries an out-of-range value -
 * because the server would log and ignore such a value and silently fall back to the
 * next level, making a GUI-side round-trip lossy and the setting a no-op.
 *
 * @param {string} token - a single top-level tuple field, e.g. `{rate_limit,[{window,60}]}`
 * @param {string} level - 'srv' or 'rpz', selecting which options are permitted
 * @returns {{ok: true, opts: Object}|{ok: false, error: string}}
 */
export function parseErlRateLimit(token, level) {
  var m = String(token == null ? '' : token).match(/^\{\s*rate_limit\s*,\s*\[([\s\S]*)\]\s*\}$/);
  if (!m) {
    return { ok: false, error: 'malformed rate_limit element "' + token + '"' };
  }
  var opts = {};
  var items = splitErlTupleFields(m[1]);
  for (var i = 0; i < items.length; i++) {
    var im = items[i].match(/^\{\s*([a-z_]+)\s*,\s*(-?[0-9]+)\s*\}$/);
    if (!im) {
      return { ok: false, error: 'malformed rate_limit option "' + items[i] + '"' };
    }
    var name = im[1], value = parseInt(im[2], 10);
    var spec = RL_OPTIONS[name];
    if (!spec) {
      return { ok: false, error: 'unknown rate_limit option "' + name + '"' };
    }
    if (!spec[level]) {
      return { ok: false, error: 'rate_limit option "' + name + '" is not supported at the ' + level + ' level' };
    }
    if (Object.prototype.hasOwnProperty.call(opts, name)) {
      return { ok: false, error: 'duplicate rate_limit option "' + name + '"' };
    }
    if (value < spec.min) {
      return {
        ok: false,
        error: 'rate_limit option "' + name + '" value ' + value + ' is below the minimum of ' + spec.min
      };
    }
    if (spec.max !== undefined && value > spec.max) {
      return {
        ok: false,
        error: 'rate_limit option "' + name + '" value ' + value + ' is above the maximum of ' + spec.max
      };
    }
    opts[name] = value;
  }
  return { ok: true, opts: opts };
}

/**
 * Classifies the optional trailing elements of a srv or rpz tuple.
 *
 * The server parses these by tag rather than by position, so `{rate_limit,...}` and
 * the `TrackSources` atom may appear in either order, or either one alone, or neither.
 * A token starting with `{rate_limit` is the rate limit element; anything else is
 * taken to be the TrackSources atom and validated against the level's vocabulary.
 *
 * @param {string[]} tokens - the trailing top-level fields, after the mandatory ones
 * @param {string} level - 'srv' or 'rpz'
 * @returns {{ok: true, track: string|null, rateLimit: Object|null}|{ok: false, error: string}}
 */
export function classifyErlTrailingElements(tokens, level) {
  var validTrack = level === 'srv' ? ['off', 'auto', 'on'] : ['auto', 'true', 'false'];
  var trackLabel = level === 'srv' ? 'source attribution default' : 'track sources value';
  var track = null, rateLimit = null;
  for (var i = 0; i < tokens.length; i++) {
    var t = tokens[i];
    if (/^\{\s*rate_limit\b/.test(t)) {
      if (rateLimit !== null) {
        return { ok: false, error: 'duplicate rate_limit element' };
      }
      var parsed = parseErlRateLimit(t, level);
      if (!parsed.ok) return { ok: false, error: parsed.error };
      rateLimit = parsed.opts;
    } else {
      if (track !== null) {
        return { ok: false, error: 'duplicate ' + trackLabel + ' "' + t + '"' };
      }
      if (!validTrack.includes(t)) {
        return { ok: false, error: 'Invalid ' + trackLabel + ' "' + t + '"' };
      }
      track = t;
    }
  }
  return { ok: true, track: track, rateLimit: rateLimit };
}

/**
 * Resolves one rate limit option through the chain zone -> server -> built-in default.
 *
 * Each option resolves independently, so a feed may set `max_requests` alone and
 * still inherit `window` from the server. An absent value at a level means "inherit"
 * and never masks the level below it. A value outside the option's legal range is
 * skipped exactly like an absent one, mirroring the server: it logs and ignores such a
 * value and falls back to the next level.
 *
 * @param {*} zoneValue - the feed's value ('' / null / undefined means inherit)
 * @param {*} srvValue - the server's value ('' / null / undefined means inherit)
 * @param {string} option - one of the RL_OPTIONS keys
 * @returns {{value: number, from: string}} resolved value and its origin
 *          ('zone', 'server', or 'default')
 */
export function resolveRateLimit(zoneValue, srvValue, option) {
  var spec = RL_OPTIONS[option] || { min: 0 };
  var levels = [['zone', zoneValue], ['server', srvValue]];
  for (var i = 0; i < levels.length; i++) {
    var from = levels[i][0], raw = levels[i][1];
    if (raw === null || raw === undefined || raw === '') continue;
    if (typeof raw === 'string' && !/^\s*-?[0-9]+\s*$/.test(raw)) continue;
    var n = typeof raw === 'number' ? raw : parseInt(raw, 10);
    if (!Number.isInteger(n) || n < spec.min) continue;
    if (spec.max !== undefined && n > spec.max) continue;
    return { value: n, from: from };
  }
  return { value: RL_DEFAULTS[option], from: 'default' };
}

/**
 * Renders a resolved rate limit as the inherited-state hint shown next to an empty
 * input, naming both the value and where it came from so the operator can tell an
 * inherited server setting from the ioc2rpz built-in default.
 *
 * Mirrors the existing `Inherited: {{ effectiveTracking }}` indicator, adding the
 * origin because the rate limits resolve through one more level than attribution.
 *
 * @param {{value: number, from: string}} resolved - output of resolveRateLimit()
 * @returns {string} e.g. `"Inherited: 60 (server)"`
 */
/**
 * Binds a stored rate limit column to its form input value.
 *
 * NULL / undefined / '' all mean "inherit" and bind to the empty string. Everything
 * else binds to its string form - notably 0, which is a legitimate stored value
 * meaning "refuse every request in that bucket" and must NOT collapse to empty (a
 * `|| ''` fallback would silently turn an explicit 0 into inherit).
 *
 * @param {*} value Stored column value
 * @returns {string} the input value ('' means inherit)
 */
export function rlToInput(value) {
  if (value === null || value === undefined || value === '') return '';
  return String(value);
}

/**
 * Resolves one rate limit option for a feed that may be assigned to several servers.
 *
 * Each server generates its own configuration file, so an option the feed does not set
 * is inherited from each server separately and can land on a different value for each
 * one. When the servers do not agree the result is marked ambiguous and carries the
 * per-server breakdown, so the editor can say the inherited value varies instead of
 * showing one server's value as if it were the effective limit.
 *
 * A value set on the feed overrides every server, so it is never ambiguous. No servers
 * selected resolves straight to the built-in default.
 *
 * Agreement is decided on the resolved VALUE: when several servers resolve to the same
 * number through different routes (one setting it explicitly, another inheriting the
 * built-in default that happens to equal it) the effective limit is the same
 * everywhere, so it is reported as a single value and attributed to the server level,
 * which is where it can be changed.
 *
 * @param {*} zoneValue - the feed's value ('' / null / undefined means inherit)
 * @param {{name: string}[]} servers - output of the rpzServerRateLimits computed
 * @param {string} option - one of the RL_OPTIONS keys
 * @returns {{value: number, from: string}|{ambiguous: true, perServer: Array}}
 */
export function resolveRateLimitAcrossServers(zoneValue, servers, option) {
  var list = Array.isArray(servers) ? servers : [];
  if (list.length === 0) return resolveRateLimit(zoneValue, '', option);
  var perServer = list.map(function(srv) {
    var r = resolveRateLimit(zoneValue, srv ? srv[option] : '', option);
    return { name: srv && srv.name ? srv.name : '', value: r.value, from: r.from };
  });
  var first = perServer[0];
  var sameValue = perServer.every(function(el) { return el.value === first.value; });
  if (!sameValue) return { ambiguous: true, perServer: perServer };
  var sameOrigin = perServer.every(function(el) { return el.from === first.from; });
  return { value: first.value, from: sameOrigin ? first.from : 'server' };
}

/**
 * Renders a resolved rate limit as the inherited-state hint shown next to an empty
 * input, naming both the value and where it came from so the operator can tell an
 * inherited server setting from the ioc2rpz built-in default.
 *
 * Mirrors the existing `Inherited: {{ effectiveTracking }}` indicator, adding the
 * origin because the rate limits resolve through one more level than attribution.
 *
 * An ambiguous result (a feed on several servers that inherit different values) is
 * rendered as the per-server breakdown, because there is no single effective limit to
 * report: each server's generated configuration gets its own.
 *
 * @param {{value: number, from: string}|{ambiguous: true, perServer: Array}} resolved
 *        output of resolveRateLimit() or resolveRateLimitAcrossServers()
 * @returns {string} e.g. `"Inherited: 60 (server)"` or
 *          `"Inherited: varies by server - ns1: 30, ns2: 60"`
 */
export function rateLimitHint(resolved) {
  if (!resolved) return '';
  if (resolved.ambiguous) {
    var parts = (resolved.perServer || []).map(function(el) {
      return (el.name === '' ? '?' : el.name) + ': ' + el.value;
    });
    return 'Inherited: varies by server - ' + parts.join(', ');
  }
  var origin = resolved.from === 'server' ? 'server'
    : resolved.from === 'zone' ? 'this feed'
    : 'built-in default';
  return 'Inherited: ' + resolved.value + ' (' + origin + ')';
}

/**
 * Builds a stable, testable view model for a feed object's `sources` field.
 *
 * This is a *total* function: it never throws for any input shape and always
 * returns a model with a `kind` discriminator the lookup view can render:
 *
 *  - non-empty array  ⇒ { kind: 'badges', badges: [{ text }, ...] }
 *      one badge per element, preserving order and duplicates; each element is
 *      coerced with String() so badge text is always a string (Req 9.1).
 *  - empty array `[]` ⇒ { kind: 'unavailable' }
 *      a single "attribution unavailable" indication, no badges (Req 9.2).
 *  - `null`           ⇒ { kind: 'unavailable' } (Req 9.3).
 *  - absent (undefined) or any unexpected non-array/non-null value (number,
 *    string, object, boolean, ...) ⇒ { kind: 'none' }
 *      no sources indication and no error (Req 9.4, 10.2).
 *
 * @param {*} sources - the feed object's `sources` field (array | null | undefined | other)
 * @returns {{kind: 'badges', badges: Array<{text: string}>} | {kind: 'unavailable'} | {kind: 'none'}}
 */
export function renderSources(sources) {
  if (Array.isArray(sources)) {
    if (sources.length === 0) return { kind: 'unavailable' };
    return { kind: 'badges', badges: sources.map(function (s) { return { text: String(s) }; }) };
  }
  if (sources === null) return { kind: 'unavailable' };
  return { kind: 'none' };
}

/**
 * Imports IOC2RPZ configuration from text
 * 
 * Parses Erlang-format configuration and creates corresponding records:
 * - TSIG keys
 * - Sources
 * - Whitelists
 * - RPZ zones
 * - Server configuration
 * 
 * Supports three import modes:
 * - 0: Add new only (skip existing)
 * - 1: Update existing
 * - 2: Add with prefix if exists
 * 
 * @param {Object} vm - Vue instance
 * @param {string} txt - Configuration text to import
 * @returns {Promise<void>}
 */
export async function ImportIOC2RPZ(vm, txt) {
  var SrvId;
  let ev = null;
  let p1 = axios.get('/io2data.php/servers');
  let p2 = axios.get('/io2data.php/tkeys');
  let p3 = axios.get('/io2data.php/sources');
  let p4 = axios.get('/io2data.php/whitelists');
  let p5 = axios.get('/io2data.php/rpzs');
  var [servers, tkeys, sources, whitelists, rpzs] = await Promise.all([p1, p2, p3, p4, p5]);
  var TKeysAll = [], SrvAll = [], WLAll = [], SrcAll = [], RpzAll = [];
  var TKeys = [], Srv = [], WL = [], Src = [], Rpz = [];
  if (servers.data) servers.data.forEach(function(el) { SrvAll[el['name']] = el['rowid'] });
  if (tkeys.data) tkeys.data.forEach(function(el) { TKeysAll[el['name']] = el['rowid'] });
  if (sources.data) sources.data.forEach(function(el) { SrcAll[el['name']] = el['rowid'] });
  if (whitelists.data) whitelists.data.forEach(function(el) { WLAll[el['name']] = el['rowid'] });
  if (rpzs.data) rpzs.data.forEach(function(el) { RpzAll[el['name']] = el['rowid'] });

  var skippedIncludes = [];
  var parseErrors = [];
  var srvSuppressed = false;
  for (let line of txt.split(/\r|\n/)) {
    var l = line.trim();
    var m;
    if (m = l.match(/^\{include,"([^"]+)"\}\.$/)) {
      skippedIncludes.push(m[1]);
      continue;
    }
    if (/^{srv,{/.test(l)) {
      // The srv tuple has 4 mandatory fields followed by up to 2 optional TRAILING
      // elements: the TrackSources atom and {rate_limit,[...]}. The server parses
      // those by tag, not by position, so they may appear in either order or alone.
      // A positional regex group cannot capture {rate_limit,...} (it contains the
      // commas, braces and brackets such a group has to exclude), so the mandatory
      // fields are matched by shape and the rest is tokenized and classified by tag.
      var srvLine = l.match(/^{srv,{([\s\S]*)}}\.(\t* *| *\t*%.*)$/);
      var srvFields = srvLine ? splitErlTupleFields(srvLine[1]) : [];
      // Mandatory field shapes, kept identical to the previous positional regex so a
      // tuple that used to be rejected (e.g. one carrying a nested {groups,[...]}) is
      // still rejected rather than silently changing meaning.
      var srvShapeOk = srvFields.length >= 4 && srvFields.length <= 6 &&
        /^"[^"]+"$/.test(srvFields[0]) && /^"[^"]+"$/.test(srvFields[1]) &&
        /^\[[^\]]*\]$/.test(srvFields[2]) && /^\[[^\]]*\]$/.test(srvFields[3]);
      if (!srvShapeOk) {
        var srvBody = l.match(/^{srv,{([\s\S]*)}}\./);
        var srvFieldCount = srvBody ? countErlTupleFields(srvBody[1]) : 0;
        parseErrors.push('Invalid srv tuple: expected 4 to 6 fields, found ' + srvFieldCount + ': ' + l);
        srvSuppressed = true;
      } else {
        var srvTail = classifyErlTrailingElements(srvFields.slice(4), 'srv');
        if (!srvTail.ok) {
          parseErrors.push(srvTail.error + ' in srv tuple: ' + l);
          srvSuppressed = true;
        } else {
          Srv['ns'] = srvFields[0].slice(1, -1);
          Srv['email'] = srvFields[1].slice(1, -1);
          Srv['tkeys'] = [];
          srvFields[2].slice(1, -1).split(/,|\s|"/g).filter(String).forEach(function(el) { Srv['tkeys'].push(el); });
          Srv['mgmt'] = srvFields[3].slice(1, -1).replace(/"/g, '');
          // Absent TrackSources => off; absent rate_limit options => inherit, which is
          // represented as '' (never a number) so it stays distinct from an explicit value.
          Srv['track_default'] = srvTail.track === null ? 'off' : srvTail.track;
          var srvRL = srvTail.rateLimit || {};
          Srv['rl_window'] = srvRL.window === undefined ? '' : String(srvRL.window);
          Srv['rl_max_requests'] = srvRL.max_requests === undefined ? '' : String(srvRL.max_requests);
          Srv['rl_max_unknown_requests'] = srvRL.max_unknown_requests === undefined ? '' : String(srvRL.max_unknown_requests);
        }
      }
    }
    if (/^{rpz,{/.test(l)) {
      // 15 mandatory fields followed by up to 2 optional TRAILING elements (the
      // TrackSources atom and {rate_limit,[...]}), in either order or either alone.
      // Same reason as the srv tuple above: the tail is tokenized and classified by
      // tag instead of being captured positionally.
      var rpzLine = l.match(/^{rpz,{([\s\S]*)}}\.(\t* *| *\t*%.*)$/);
      var rpzFields = rpzLine ? splitErlTupleFields(rpzLine[1]) : [];
      // Mandatory field shapes, preserving exactly what the previous positional regex
      // accepted: quoted name, 4 integers, quoted cache/wildcard, quoted-or-list
      // action, bracket lists, quoted ioc_type, 2 integers, 3 bracket lists.
      var RPZ_SHAPES = [
        /^"[^"]+"$/, /^[0-9]+$/, /^[0-9]+$/, /^[0-9]+$/, /^[0-9]+$/,
        /^"[^"]+"$/, /^"[^"]+"$/, /^(?:"[^"]+"|[^"]+|\[[^\]]*\])$/, /^\[[^\]]*\]$/,
        /^"[^"]+"$/, /^[0-9]+$/, /^[0-9]+$/, /^\[[^\]]*\]$/, /^\[[^\]]*\]$/, /^\[[^\]]*\]$/
      ];
      var rpzShapeOk = rpzFields.length >= 15 && rpzFields.length <= 17;
      if (rpzShapeOk) {
        for (var rsi = 0; rsi < RPZ_SHAPES.length; rsi++) {
          if (!RPZ_SHAPES[rsi].test(rpzFields[rsi])) { rpzShapeOk = false; break; }
        }
      }
      if (!rpzShapeOk) {
        var rpzBody = l.match(/^{rpz,{([\s\S]*)}}\./);
        var rpzFieldCount = rpzBody ? countErlTupleFields(rpzBody[1]) : 0;
        parseErrors.push('Invalid rpz tuple: expected 15 to 17 fields, found ' + rpzFieldCount + ': ' + l);
      } else {
        var rpzTail = classifyErlTrailingElements(rpzFields.slice(15), 'rpz');
        if (!rpzTail.ok) {
          parseErrors.push(rpzTail.error + ' in rpz tuple: ' + l);
        } else {
          // Strip the wrapping quotes/brackets exactly as the positional capture groups did.
          var unq = function(s) { return /^"[\s\S]*"$/.test(s) ? s.slice(1, -1) : s; };
          var inner = function(s) { return s.slice(1, -1); };
          // The server canonicalises zone names to lower case (RFC 4343), so normalize
          // on import too: a mixed-case name in a hand-written config would otherwise
          // import as a zone the server then renames behind the GUI's back.
          var rName = inner(rpzFields[0]).toLowerCase();
          var rpzRL = rpzTail.rateLimit || {};
          Rpz[rName] = [];
          Rpz[rName]['tkeys'] = [];
          if (inner(rpzFields[8])) inner(rpzFields[8]).split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[rName]['tkeys'].push(el); });
          Rpz[rName]['sources'] = [];
          inner(rpzFields[12]).split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[rName]['sources'].push(el); });
          Rpz[rName]['notify'] = inner(rpzFields[13]).replace(/"/g, '');
          Rpz[rName]['whitelists'] = [];
          if (inner(rpzFields[14])) inner(rpzFields[14]).split(/,|\s|"/g).filter(String).forEach(function(el) { Rpz[rName]['whitelists'].push(el); });
          Rpz[rName]['name'] = rName;
          Rpz[rName]['soa_refresh'] = rpzFields[1]; Rpz[rName]['soa_update'] = rpzFields[2];
          Rpz[rName]['soa_exp'] = rpzFields[3]; Rpz[rName]['soa_nxttl'] = rpzFields[4];
          Rpz[rName]['cache'] = unq(rpzFields[5]) == "true" ? 1 : 0;
          Rpz[rName]['wildcards'] = unq(rpzFields[6]) == "true" ? 1 : 0;
          Rpz[rName]['action'] = unq(rpzFields[7]); Rpz[rName]['ioc_type'] = unq(rpzFields[9]);
          Rpz[rName]['AXFR_time'] = rpzFields[10]; Rpz[rName]['IXFR_time'] = rpzFields[11];
          Rpz[rName]['track_sources'] = rpzTail.track === null ? 'Inherit' : rpzTail.track;
          // Absent option => inherit, carried as '' rather than a number.
          Rpz[rName]['rl_window'] = rpzRL.window === undefined ? '' : String(rpzRL.window);
          Rpz[rName]['rl_max_requests'] = rpzRL.max_requests === undefined ? '' : String(rpzRL.max_requests);
        }
      }
    }
    if (m = l.match(/^{key,{"([^"]+)","([^"]+)","([^"]+)"}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!TKeysAll[m[1]] || (!TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!TKeysAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftKeyId = (TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? TKeysAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftKeyName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (TKeysAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftKeyAlg = m[2]; vm.ftKey = m[3]; vm.ftKeyMGMT = (Srv['tkeys'] && Srv['tkeys'].includes(m[1])) ? 1 : 0;
        TKeys[vm.ftKeyName] = vm.ftKeyName; TKeys[m[1]] = vm.ftKeyName;
        await vm.tblMgmtTKeyRecord(ev, 'tkeys');
      } else {
        TKeys[m[1]] = (TKeysAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (TKeysAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!WLAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? WLAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcREGEX = m[4] !== undefined ? m[4] : m[3]; vm.ftSrcURLIXFR = "";
        vm.ftSrcMaxIOC = '0'; vm.ftSrcHotCacheAXFR = '900'; vm.ftSrcHotCacheIXFR = '0';
        WL[vm.ftSrcName] = vm.ftSrcName; WL[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'whitelists'; await vm.tblMgmtSrcRecord(ev, 'whitelists');
      } else {
        WL[m[1]] = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!SrcAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? SrcAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcURLIXFR = m[3]; vm.ftSrcREGEX = m[5] !== undefined ? m[5] : m[4];
        vm.ftSrcMaxIOC = '0'; vm.ftSrcHotCacheAXFR = '900'; vm.ftSrcHotCacheIXFR = '0';
        Src[vm.ftSrcName] = vm.ftSrcName; Src[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'sources'; await vm.tblMgmtSrcRecord(ev, 'sources');
      } else {
        Src[m[1]] = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!WLAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? WLAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcREGEX = m[4] !== undefined ? m[4] : m[3]; vm.ftSrcURLIXFR = "";
        vm.ftSrcMaxIOC = m[6]; vm.ftSrcHotCacheAXFR = m[7]; vm.ftSrcHotCacheIXFR = m[8];
        WL[vm.ftSrcName] = vm.ftSrcName; WL[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'whitelists'; await vm.tblMgmtSrcRecord(ev, 'whitelists');
      } else {
        WL[m[1]] = (WLAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (WLAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
    if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)) {
      if (vm.ftImpAction == 1 || (vm.ftImpAction == 2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpPrefix))) || (vm.ftImpAction == 0 && (!SrcAll[vm.ftImpPrefix + m[1]]))) {
        vm.ftSrcId = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction == 1) ? SrcAll[vm.ftImpPrefix + m[1]] : -1;
        vm.ftSrcName = vm.ftImpAction != 2 ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
        vm.ftSrcURL = m[2]; vm.ftSrcURLIXFR = m[3]; vm.ftSrcREGEX = m[5] !== undefined ? m[5] : m[4];
        vm.ftSrcMaxIOC = m[7]; vm.ftSrcHotCacheAXFR = m[8]; vm.ftSrcHotCacheIXFR = m[9];
        Src[vm.ftSrcName] = vm.ftSrcName; Src[m[1]] = vm.ftSrcName;
        vm.ftSrcType = 'sources'; await vm.tblMgmtSrcRecord(ev, 'sources');
      } else {
        Src[m[1]] = (SrcAll[vm.ftImpPrefix + m[1]] && vm.ftImpAction != 2) ? vm.ftImpPrefix + m[1] : (SrcAll[m[1]] && vm.ftImpAction == 2) ? vm.ftImpPrefix + m[1] : m[1];
      }
    }
  }

  await sleep(1000);
  p1 = axios.get('/io2data.php/tkeys');
  p2 = axios.get('/io2data.php/sources');
  p3 = axios.get('/io2data.php/whitelists');
  [tkeys, sources, whitelists] = await Promise.all([p1, p2, p3]);
  TKeysAll = []; WLAll = []; SrcAll = [];
  if (tkeys.data) tkeys.data.forEach(function(el) { TKeysAll[el['name']] = el['rowid'] });
  if (sources.data) sources.data.forEach(function(el) { SrcAll[el['name']] = el['rowid'] });
  if (whitelists.data) whitelists.data.forEach(function(el) { WLAll[el['name']] = el['rowid'] });

  if (Srv.length > 0 && !srvSuppressed) {
    vm.ftSrvId = -1; vm.ftSrvName = vm.ftImpServName;
    vm.ftSrvNS = Srv['ns']; vm.ftSrvEmail = Srv['email'].replace('.', '@');
    vm.ftSrvMGMTIP = Srv['mgmt'];
    vm.ftSrvTrackDefault = Srv['track_default'] || 'off';
    // '' means inherit: the option was absent from the imported tuple.
    vm.ftSrvRLWindow = Srv['rl_window'] || '';
    vm.ftSrvRLMaxRequests = Srv['rl_max_requests'] || '';
    vm.ftSrvRLMaxUnknownRequests = Srv['rl_max_unknown_requests'] || '';
    if (Srv['tkeys']) Srv['tkeys'].forEach(function(el) {
      if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftSrvTKeys.push(TKeysAll[TKeys[el]]);
    });
    vm.ftSrvSType = 0; vm.ftSrvURL = vm.ftImpFiles[0].name;
    vm.ftCertFile = Srv['certfile']; vm.ftKeyFile = Srv['keyfile'];
    vm.ftCACertFile = Srv['cacertfile']; vm.ftCustomConfig = Srv['custom_config'];
    vm.ftSrvPubIP = vm.ftImpServPubIP; vm.ftSrvIP = vm.ftImpServMGMTIP;
    await vm.tblMgmtSrvRecord(ev, 'servers');
    do {
      await sleep(1000);
      p1 = axios.get('/io2data.php/servers');
      [servers] = await Promise.all([p1]);
      if (servers.data) servers.data.forEach(function(el) { if (vm.ftSrvName == el['name']) SrvId = el['rowid'] });
    } while (SrvId == undefined)
  }

  if (Rpz.length > 0) {
    vm.ftRPZId = -1; vm.ftRPZSrvs = []; vm.ftRPZSrvs.push(SrvId);
    for (var RpzName in Rpz) {
      vm.ftRPZName = RpzName;
      vm.ftRPZSOA_Refresh = Rpz[RpzName]['soa_refresh'];
      vm.ftRPZSOA_UpdRetry = Rpz[RpzName]['soa_update'];
      vm.ftRPZSOA_Exp = Rpz[RpzName]['soa_exp'];
      vm.ftRPZSOA_NXTTL = Rpz[RpzName]['soa_nxttl'];
      vm.ftRPZCache = Rpz[RpzName]['cache'];
      vm.ftRPZWildcard = Rpz[RpzName]['wildcards'];
      if (["nxdomain", "nodata", "passthru", "drop", "tcp-only"].includes(Rpz[RpzName]['action'])) {
        vm.ftRPZAction = Rpz[RpzName]['action']; vm.ftRPZActionCustom = "";
      } else {
        vm.ftRPZAction = "local"; vm.ftRPZActionCustom = Rpz[RpzName]['action'];
      }
      vm.ftRPZIOCType = Rpz[RpzName]['ioc_type'];
      vm.ftRPZAXFR = Rpz[RpzName]['AXFR_time'];
      vm.ftRPZIXFR = Rpz[RpzName]['IXFR_time'];
      vm.ftRPZTrackSources = Rpz[RpzName]['track_sources'] || 'Inherit';
      // '' means inherit: the option was absent from the imported tuple.
      vm.ftRPZRLWindow = Rpz[RpzName]['rl_window'] || '';
      vm.ftRPZRLMaxRequests = Rpz[RpzName]['rl_max_requests'] || '';
      vm.ftRPZTKeys = [];
      if (Rpz[RpzName]['tkeys']) Rpz[RpzName]['tkeys'].forEach(function(el) {
        if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftRPZTKeys.push(TKeysAll[TKeys[el]]);
      });
      vm.ftRPZSrc = [];
      Rpz[RpzName]['sources'].forEach(function(el) {
        if (Src[el] && SrcAll[Src[el]]) vm.ftRPZSrc.push(SrcAll[Src[el]]);
      });
      vm.ftRPZNotify = Rpz[RpzName]['notify'];
      vm.ftRPZWL = [];
      if (Rpz[RpzName]['whitelists']) Rpz[RpzName]['whitelists'].forEach(function(el) {
        if (WL[el] && WLAll[WL[el]]) vm.ftRPZWL.push(WLAll[WL[el]]);
      });
      vm.tblMgmtRPZRecord(ev, 'rpzs');
    }
  }

  if (skippedIncludes.length > 0) {
    vm.showInfo('Import completed. Skipped include statements: ' + skippedIncludes.join(', '), 5);
  }

  if (parseErrors.length > 0) {
    vm.showInfo('Import parse errors: ' + parseErrors.join('; '), 5);
  }
}


// ============================================
// Vue App Configuration Object
// This is exported for use in main.js to create the Vue instance
// ============================================

/**
 * Vue app configuration object
 * 
 * Contains the complete Vue component definition for the main application:
 * - el: Mount point selector
 * - data: Reactive state for all UI components
 * - mounted: Lifecycle hook for initialization
 * - computed: Computed properties (currently empty)
 * - methods: All component methods for CRUD operations and UI interactions
 * 
 * Used by main.js to create the Vue instance: createApp(appConfig)
 * 
 * @type {Object}
 */
export const appConfig = {
  el: "#app",
  
  /**
   * Component reactive data
   * Contains all state variables for the application
   */
  data: {
    /** Toggle state for sidebar menu */
    toggleMenu: 0,
    /** Current active tab index */
    cfgTab: 0,
    /** Current window inner width for responsive layout */
    windowInnerWidth: 800,
    /** Height of log display area */
    logs_height: 150,
    /** Number of log entries per page */
    logs_pp: 5,

    /**
     * Tab index to table name mapping for Vue 3 compatibility
     * Replaces $children access which is removed in Vue 3
     * @type {Object.<number, string|null>}
     */
    tabTableMap: {
      0: 'servers',
      1: null,  // RpiDNS - no table
      2: 'tkeys_groups',
      3: 'tkeys',
      4: 'whitelists',
      5: 'sources',
      6: 'rpzs',
      7: null,  // Utils - no table
      8: 'users'
    },

    /**
     * Column definitions for servers table
     * @type {Array.<Object>}
     */
    servers_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'ip', label: 'MGMT IP/FQDN', sortable: true },
      { key: 'ns', label: 'Name Server' },
      { key: 'email', label: 'Admin Email' },
      { key: 'mgmt', label: 'Manage', 'class': 'text-center' },
      { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width250' }
    ],

    tkeys_groups_fields: [
      { key: 'group_name', label: 'Group name', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    tkeys_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'alg', label: 'Algorithm', sortable: true },
      { key: 'tkey', label: 'TSIG Key', formatter: (value) => { return value.length > 45 ? value.substring(0, 44) + ' ...' : value; } },
      { key: 'mgmt', label: 'Management', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    whitelists_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'url', label: 'URL', sortable: true },
      { key: 'regex', label: 'RegEx', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    sources_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'url', label: 'URL', sortable: true, formatter: (value) => { return value.length > 35 ? value.substring(0, 34) + ' ...' : value; } },
      { key: 'url_ixfr', label: 'URL update', sortable: true, formatter: (value) => { return value.length > 35 ? value.substring(0, 34) + ' ...' : value; } },
      { key: 'regex', label: 'RegEx', sortable: true, formatter: (value) => { return value.length > 25 ? value.substring(0, 24) + ' ...' : value; } },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    rpzs_fields: [
      { key: 'name', label: 'Name', sortable: true },
      { key: 'servers_list', label: 'Servers', sortable: true },
      { key: 'ioc_type', label: 'IOC type', sortable: true },
      { key: 'cache', label: 'Cachable', sortable: true, 'class': 'text-center' },
      { key: 'wildcard', label: 'Wildcards', sortable: true, 'class': 'text-center' },
      { key: 'action', label: 'Responce action', sortable: true, formatter: (value) => { return value == "nxdomain" ? "NXDomain" : value == "nodata" ? "NoData" : value == "passthru" ? "Passthru" : value == "drop" ? "Drop" : value == "tcp-only" ? "TCP-Only" : "Local Records"; } },
      { key: 'sources_list', label: 'Sources', sortable: true },
      { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    users_fields: [
      { key: 'name', label: 'Login', sortable: true },
      { key: 'actions_e', label: 'Actions', 'class': 'text-center', 'tdClass': 'width150' }
    ],

    modalMSG: 'Modal',
    errorMSG: 'Error',
    deleteRec: 0,
    deleteTbl: '',

    // TSIG Keys
    ftKeyId: 0, ftKeyName: '', ftKey: '', ftKeyMGMT: 0, ftKeyAlg: "md5",
    tkeys_Alg: ["md5", "sha256", "sha512"],
    ftTKeysGroups: [], ftTKeysAllGroups: [],

    // Key Groups
    ftKeyGId: -1, ftKeyGName: '',

    // Sources/Whitelists
    ftSrcId: 0, ftSrcName: '', ftSrcURL: '', ftSrcURLIXFR: '', ftSrcREGEX: '',
    ftSrcType: "sources", ftSrcTitle: "Source",
    ftSrcMaxIOC: '0', ftSrcHotCacheAXFR: '900', ftSrcHotCacheIXFR: '0',
    ftSrcIoCType: 'mixed', ftSrcKeepInCache: 0,

    // Servers
    ftSrvId: 0, ftSrvName: '', ftSrvPubIP: '', ftSrvIP: '', ftSrvNS: '', ftSrvEmail: '',
    ftSrvMGMT: 0, ftSrvMGMTIP: '', ftSrvTKeys: [], ftSrvTKeysAll: [], ftSrvDisabled: 0,
    ftSrvSType: 0, ftSrvURL: "", ftCertFile: "", ftKeyFile: "", ftCACertFile: "", ftCustomConfig: "",
    ftSrvTrackDefault: 'off',
    // Server-level DNS rate limits. '' means inherit the ioc2rpz built-in default;
    // they are kept as strings so an empty input stays distinguishable from 0.
    ftSrvRLWindow: '', ftSrvRLMaxRequests: '', ftSrvRLMaxUnknownRequests: '',
    // Exposed to the templates so the window inputs and their error messages share the
    // single cap definition instead of repeating the number.
    rlWindowMax: RL_WINDOW_MAX,
    servers_filter: "",

    Srv_TrackDefault_Options: [
      { value: 'off', text: 'off' },
      { value: 'auto', text: 'auto' },
      { value: 'on', text: 'on' }
    ],

    // RPZs
    ftRPZId: 0, ftRPZName: '', ftRPZSrvs: [], ftRPZSrvsAll: [],
    ftRPZTKeys: [], ftRPZTKeysAll: [], ftRPZWL: [], ftRPZWLAll: [],
    ftRPZSrc: [], ftRPZSrcAll: [], ftRPZNotify: "",
    ftRPZSOA_Refresh: '', ftRPZSOA_UpdRetry: '', ftRPZSOA_Exp: '', ftRPZSOA_NXTTL: '',
    ftRPZCache: 0, ftRPZWildcard: 0,
    ftRPZAction: "nxdomain", ftRPZActionCustom: "",
    ftRPZIOCType: "mixed", ftRPZAXFR: '', ftRPZIXFR: '', ftRPZDisabled: 0,
    ftRPZTrackSources: 'Inherit',
    // Per-feed DNS rate limit overrides. '' means inherit from the server level.
    ftRPZRLWindow: '', ftRPZRLMaxRequests: '',

    RPZ_TrackSources_Options: [
      { value: 'Inherit', text: 'Inherit' },
      { value: 'auto', text: 'auto' },
      { value: 'true', text: 'true' },
      { value: 'false', text: 'false' }
    ],

    RPZ_Act_Options: [
      { value: 'nxdomain', text: 'NXDomain' },
      { value: 'nodata', text: 'NoData' },
      { value: 'passthru', text: 'Passthru' },
      { value: 'drop', text: 'Drop' },
      { value: 'tcp-only', text: 'TCP-only' },
      { value: 'local', text: 'Local records' }
    ],

    RPZ_IType_Options: [
      { value: 'mixed', text: 'mixed' },
      { value: 'fqdn', text: 'fqdn' },
      { value: 'ip', text: 'ip' }
    ],

    ftRPZProWindow: "", ftRPZProWindowInfo: "", RPZtabI: 0,
    ftRPZInfoServerName: '', ftRPZInfoServerIP: '',
    ftRPZInfoTKeyName: '', ftRPZInfoTKeyAlg: '', ftRPZInfoTKey: '', ftRPZInfoDig: '',

    infoWindow: true, publishUpdates: false, editRow: {},
    mInfoMSGvis: false, msgInfoMSG: '',

    // Import
    ftImpServName: '', ftImpServPubIP: '', ftImpServMGMTIP: '',
    ftImpFiles: [], ftImpFileDesc: '', ftImpPrefix: '', ftImpAction: 0,
    ftImportRec: '',

    // Users
    ftUId: 0, ftUName: '', ftUNameProf: '', ftUCPwd: '', ftUPwd: '', ftUpwdConf: '', ftUPerm: 0,
    UPerm_Options: [
      { value: 1, text: 'Super Admin' },
      { value: 100, text: 'RPZ Admin' },
      { value: 1000, text: 'Read Only', disabled: true }
    ],

    // Export
    ftExRPZ: [], ftExRPZAll: [], ftExFormat: '',
    rpzExportSAll: false, rpzExportIBView: 'default', rpzExportIBMember: 'infoblox.localdomain',

    // IOC lookup (source attribution)
    ftLookupIoc: '', ftLookupServer: '', ftLookupServersAll: [],
    ftLookupResults: [], ftLookupError: '', ftLookupNotFound: false,
    ftLookupSubmitted: false, ftLookupInProgress: false,

    // RpiDNS
    RpiDNSList: [], RpiDNSListDash: [],
    addRpiDNSName: "", addRpiDNSComment: "", addRpiDNSModel: { type: 'container', max: 100000000 }, addRpiDNSServer: { type: 'bind', max: 0 },
    addRpiDNSOptions: [
      { id: 'container', value: { type: 'container', max: 100000000 }, text: 'Container' },
/*
      { id: null, value: null, text: 'Select your hardware/VM', disabled: true },
      { id: 'pizero', value: { type: 'pizero', max: 500000 }, text: 'Raspbian on Pi Zero/Zero W' },
      { id: 'pi123', value: { type: 'pi123', max: 500000 }, text: 'Raspbian on Pi 1/2/3' },
      { id: 'pi4-1g', value: { type: 'pi4-1g', max: 2500000 }, text: 'Raspbian on Pi 4 with 1Gb/2Gb' },
      { id: 'pi4-4g', value: { type: 'pi4-4g', max: 5000000 }, text: 'Raspbian on Pi 4 with 4Gb' },
      { id: 'ubuntu18', value: { type: 'ubuntu18', max: 100000000 }, text: 'Ubuntu 18.x', disabled: true }
*/
      ],
    addRpiDNSServerOptions: [
      { id: 'bind', value: { type: 'bind', max: 0 }, text: 'ISC Bind' },
  /*
      { id: null, value: null, text: 'Select DNS server', disabled: true },
      { id: 'powerdns', value: { type: 'powerdns', max: 500000 }, text: 'PowerDNS', disabled: true },
      { id: 'pidns', value: { type: 'pidns', max: 500000 }, text: 'piDNS', disabled: true }
  */
    ],
    addRpiDNSFeedAction: [
      { id: 'passthrunolog', value: 'passthru log no', text: 'Passthru - No log', type: "allow" },
      { id: 'passthru', value: 'passthru', text: 'Passthru', type: "allow" },
      { id: 'cname', value: 'cname', text: 'Block - Redirect', type: "deny" },
      { id: 'nxdomain', value: 'nxdomain', text: 'Block - NXDomain', type: "deny" },
      { id: 'nodata', value: 'nodata', text: 'Block - NoData', type: "deny" },
      { id: 'drop', value: 'drop', text: 'Block - Drop', type: "deny" },
      { id: 'disabled', value: 'disabled', text: 'Log only', type: "any" }
    ],

    tRPZRpiDNS_fields: [
      { key: 'rowid', label: '', sortable: false, 'tdClass': 'width005' },
      { key: 'name', label: 'Name', sortable: false },
      { key: 'action', label: 'Action', sortable: false, 'tdClass': 'width200' }
    ],
    addRpiDNSRulesCount: 0, ftRpiDNSRPZ: [], ftRpiDNSRPZrecom: [], ftRpiDNSRPZAction: {},
    addRpiDNSRedirect: "", addRpiDNSRedirectURL: "",
    addRpiDNSType: "", addRpiDNSTypeIPNet: "",
    addRpiDNSLogs: "", addRpiDNSLogsURL: "",
    addRpiDNSCheckConf: true, RpiDNSLabel: "Add RpiDNS", RpiDNSBttn: "Add", addRpiDNSid: 0,
    locationOrigin: window.location.origin
  },

  /**
   * Vue lifecycle hook - called after component is mounted
   * Initializes window size tracking, loads saved preferences, and fetches RpiDNS list
   */
  mounted: function() {
    if (window.location.hash) {
      var a = window.location.hash.split(/#|\//).filter(String);
      switch (a[0]) {
        case "tabs_menu":
          this.cfgTab = parseInt(a[1]);
      }
    }
    this.ftUName = window.jsUser || '';
    this.ftUNameProf = window.jsUser || '';

    update_window_size(this);
    this.$nextTick(() => {
      window.addEventListener('resize', () => { update_window_size(this); });
    });

    if (window.localStorage.getItem('publishUpdates')) {
      this.publishUpdates = (window.localStorage.getItem('publishUpdates') == "true");
    }

    this.refreshRpiDNS();
  },

  /**
   * Computed properties (currently empty, can be extended as needed)
   */
  computed: {
    /**
     * Resolve the effective source-tracking state for the RPZ (feed) being edited.
     *
     * Resolution rule (Req 3.2, 3.3):
     *  - When the per-feed "Track sources" control (ftRPZTrackSources) is one of
     *    auto/true/false, use it directly, mapping true->on, false->off, auto->auto.
     *  - Otherwise (Inherit or any unexpected value), fall back to the selected
     *    server's global default when it is one of off/auto/on.
     *  - Otherwise, off.
     * The returned value is always exactly one of off/auto/on (the server vocabulary).
     *
     * The global default is taken from the first selected server (ftRPZSrvs[0]) when
     * that server's track_default is present in the loaded server list (ftRPZSrvsAll).
     * The rpz_servers list currently exposes only {value, text}, so track_default is
     * generally not available in the editor; in that case the global default is off.
     */
    effectiveTracking: function() {
      var feed = this.ftRPZTrackSources;
      if (feed === 'auto') return 'auto';
      if (feed === 'true') return 'on';
      if (feed === 'false') return 'off';

      // Feed setting is Inherit (or unexpected): resolve from the global default.
      var globalDefault = 'off';
      if (Array.isArray(this.ftRPZSrvs) && this.ftRPZSrvs.length > 0 && Array.isArray(this.ftRPZSrvsAll)) {
        var firstId = this.ftRPZSrvs[0];
        var srv = this.ftRPZSrvsAll.find(function(el) { return el && el.value == firstId; });
        if (srv && typeof srv.track_default !== 'undefined' && srv.track_default !== null) {
          globalDefault = srv.track_default;
        }
      }
      if (globalDefault === 'off' || globalDefault === 'auto' || globalDefault === 'on') return globalDefault;
      return 'off';
    },

    /**
     * Whether source tracking is "enabling" for the RPZ (feed) being edited.
     *
     * Tracking is enabling (Req 4.1, 4.3, 4.4) when:
     *  - the per-feed "Track sources" control (ftRPZTrackSources) is auto or true, OR
     *  - the control is Inherit and the resolved effectiveTracking is auto or on.
     * When the control is false, or Inherit resolving to off, tracking is not enabling.
     *
     * Drives the AXFR_Rebuild hint and the "will not be produced until cache = true"
     * notice, both of which must be hidden when tracking resolves to off/false.
     */
    trackingEnabling: function() {
      var feed = this.ftRPZTrackSources;
      if (feed === 'auto' || feed === 'true') return true;
      if (feed === 'false') return false;
      // Inherit (or unexpected): enabling only when effective tracking is auto or on.
      var eff = this.effectiveTracking;
      return eff === 'auto' || eff === 'on';
    },

    /**
     * The stored rate limits of EVERY server this feed is assigned to, which form the
     * middle link of the feed-level resolution chain.
     *
     * A feed can be published to several servers, and each server generates its own
     * configuration file, so the same feed can end up with a different effective limit
     * per server. Returning all of them (rather than just the first) is what lets the
     * editor say so instead of showing one arbitrary server's value as if it were the
     * only one. `ftRPZSrvsAll` carries rl_window and rl_max_requests because the
     * rpz_servers endpoint selects them.
     *
     * When no server is selected, or the list has not loaded, the result is empty and
     * resolution falls through to the built-in defaults.
     *
     * @returns {{name: string, window: *, max_requests: *}[]} one entry per selected
     *          server, in selection order; '' for an option the server does not set
     */
    rpzServerRateLimits: function() {
      if (!Array.isArray(this.ftRPZSrvs) || this.ftRPZSrvs.length === 0) return [];
      if (!Array.isArray(this.ftRPZSrvsAll)) return [];
      var all = this.ftRPZSrvsAll;
      var absent = function(v) { return v === undefined || v === null ? '' : v; };
      return this.ftRPZSrvs.map(function(id) {
        var srv = all.find(function(el) { return el && el.value == id; });
        if (!srv) return null;
        return {
          name: srv.text === undefined || srv.text === null ? String(id) : String(srv.text),
          window: absent(srv.rl_window),
          max_requests: absent(srv.rl_max_requests)
        };
      }).filter(function(el) { return el !== null; });
    },

    /**
     * Resolves the feed's effective DNS rate limits for display.
     *
     * Each option resolves independently through zone -> server -> built-in default,
     * so a feed may set max_requests alone and still inherit window from the server.
     *
     * A feed assigned to several servers inherits from each of them separately, so an
     * option the feed does NOT set can resolve to a different value per server. In that
     * case the result carries `ambiguous: true` and the per-server breakdown, and the
     * editor reports that the inherited value varies rather than picking one server's
     * value and presenting it as the effective limit. An option the feed DOES set is
     * never ambiguous: it overrides every server.
     *
     * @returns {Object} per option, either {value, from} or
     *          {ambiguous: true, perServer: [{name, value, from}]}
     */
    effectiveRPZRateLimit: function() {
      var servers = this.rpzServerRateLimits;
      return {
        window: resolveRateLimitAcrossServers(this.ftRPZRLWindow, servers, 'window'),
        max_requests: resolveRateLimitAcrossServers(this.ftRPZRLMaxRequests, servers, 'max_requests')
      };
    },

    /** Human-readable origin+value hint for the feed's inherited rate limit window. */
    effectiveRPZRLWindowHint: function() {
      return rateLimitHint(this.effectiveRPZRateLimit.window);
    },

    /** Human-readable origin+value hint for the feed's inherited max_requests. */
    effectiveRPZRLMaxRequestsHint: function() {
      return rateLimitHint(this.effectiveRPZRateLimit.max_requests);
    },

    /**
     * Resolves the server's effective DNS rate limits for display.
     *
     * The server is the second link of the chain, so there is no zone value here: an
     * empty input inherits the ioc2rpz built-in default directly.
     */
    effectiveSrvRateLimit: function() {
      return {
        window: resolveRateLimit('', this.ftSrvRLWindow, 'window'),
        max_requests: resolveRateLimit('', this.ftSrvRLMaxRequests, 'max_requests'),
        max_unknown_requests: resolveRateLimit('', this.ftSrvRLMaxUnknownRequests, 'max_unknown_requests')
      };
    },

    /** Human-readable origin+value hint for the server's rate limit window. */
    effectiveSrvRLWindowHint: function() {
      return rateLimitHint(this.effectiveSrvRateLimit.window);
    },

    /** Human-readable origin+value hint for the server's max_requests. */
    effectiveSrvRLMaxRequestsHint: function() {
      return rateLimitHint(this.effectiveSrvRateLimit.max_requests);
    },

    /** Human-readable origin+value hint for the server's max_unknown_requests. */
    effectiveSrvRLMaxUnknownRequestsHint: function() {
      return rateLimitHint(this.effectiveSrvRateLimit.max_unknown_requests);
    }
  },

  /**
   * Component methods for all UI interactions and CRUD operations
   */
  methods: {
    /**
     * Fetches and refreshes the RpiDNS device list from the server
     * Parses configuration JSON and populates display names
     */
    refreshRpiDNS: function() {
      let obj = this;
      axios.get('/io2data.php/rpidns').then(function(response) {
        if (/DOCTYPE html/.test(response.data)) {
          window.location.reload(true);
        } else if (response.data.status == "success") {
          obj.$root.RpiDNSList = [];
          response.data.data.forEach(function(El) {
            // Parse rpz if it's a string
            if (typeof El.rpz === 'string') {
              try { El.rpz = JSON.parse(El.rpz); } catch(e) { El.rpz = []; }
            }
            if (!Array.isArray(El.rpz)) El.rpz = [];
            
            // Safely get dns_name and model_name with fallbacks
            let dnsOption = obj.addRpiDNSServerOptions.find(item => { return item.id === El.dns });
            El.dns_name = dnsOption ? dnsOption.text : El.dns || 'Unknown';
            let modelOption = obj.addRpiDNSOptions.find(item => { return item.id === El.model });
            El.model_name = modelOption ? modelOption.text : El.model || 'Unknown';
            obj.$root.RpiDNSList.push(El);
          });
          splitRpiDNSList(obj);
        } else {
          obj.showInfo(response.data.description, 3);
        }
      }).catch(function(error) {
        obj.showInfo('Unknown error!!!', 3);
      });
    },

    /**
     * Opens the Add RpiDNS modal dialog
     * Clears form fields and sets mode to "Add"
     * 
     * @param {number} id - Unused parameter (kept for API consistency)
     */
    rpidns_add: function(id) {
      this.clear_rpidns_modal();
      this.RpiDNSLabel = "Add RpiDNS"; this.RpiDNSBttn = "Add"; this.addRpiDNSid = 0;
      showModal('mAddRpiDNS');
    },

    /**
     * Opens the Edit RpiDNS modal dialog with existing device data
     * Populates form fields from the selected device configuration
     * 
     * @param {number} id - RpiDNS device ID to edit
     */
    rpidns_edit: function(id) {
      this.clear_rpidns_modal();
      this.addRpiDNSid = id; this.RpiDNSLabel = "Edit RpiDNS"; this.RpiDNSBttn = "Save";
      this.addRpiDNSRulesCount = 0;
      var obj = this;
      let El = this.RpiDNSList.find(item => { return item.id === id });
      this.addRpiDNSName = El.name;
      this.addRpiDNSModel = this.addRpiDNSOptions.find(item => { return item.id === El.model }).value;
      this.addRpiDNSServer = this.addRpiDNSServerOptions.find(item => { return item.id === El.dns }).value;
      this.addRpiDNSCheckConf = El.updconf;
      this.addRpiDNSRedirect = El.redirect === undefined ? "default" : El.redirect;
      this.addRpiDNSRedirectURL = El.redirect_cname === undefined ? "" : El.redirect_cname;
      this.addRpiDNSLogs = El.logging === undefined ? "local" : El.logging;
      this.addRpiDNSLogsURL = El.logging_host === undefined ? "" : El.logging_host;
      this.addRpiDNSType = El.dns_type === undefined ? "primary" : El.dns_type;
      this.addRpiDNSTypeIPNet = El.dns_ipnet === undefined ? "" : El.dns_ipnet;
      El.rpz.forEach(function(item) { 
        obj.ftRpiDNSRPZAction[item.feed] = item.action; 
        obj.ftRpiDNSRPZ.push(item.feed); 
      });
      this.addRpiDNSComment = El.comment;
      showModal('mAddRpiDNS');
    },

    /**
     * Validates a hostname or IP address field
     * Returns null for empty fields, true/false for validation result
     * 
     * @param {string} vrbl - Name of the data property to validate
     * @returns {boolean|null} Validation state for bootstrap-vue form feedback
     */
    validateHostnameIP: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : checkHostIP(this.$data[vrbl]);
    },

    validateHostnameIPNet: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : checkHostIPNet(this.$data[vrbl]);
    },

    formatHostnameIPNet: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/\/\,]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatHostnameIP: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    clear_rpidns_modal: function() {
      this.addRpiDNSName = ""; this.addRpiDNSModel = { type: 'container', max: 100000000 };
      this.addRpiDNSServer = { type: 'bind', max: 0 };
      this.addRpiDNSCheckConf = true; this.ftRpiDNSRPZ = []; this.ftRpiDNSRPZAction = {};
      var obj = this;
      // In bootstrap-vue-next, use items() method instead of localItems
      const tableRef = obj.$refs.io2tbl_rpzs;
      const tableItems = tableRef && typeof tableRef.items === 'function' 
        ? tableRef.items() 
        : (tableRef && tableRef.localItems) || [];
      if (tableItems.length > 0) {
        tableItems.forEach(function(item) { obj.ftRpiDNSRPZAction[item.name] = ((item.type == "v" || item.type == "w") ? "passthru log no" : "cname"); });
      }
      this.addRpiDNSComment = ""; this.addRpiDNSRulesCount = 0;
      this.addRpiDNSRedirect = "default"; this.addRpiDNSRedirectURL = "";
      this.addRpiDNSLogs = "local"; this.addRpiDNSLogsURL = "";
      this.addRpiDNSType = "primary"; this.addRpiDNSTypeIPNet = "";
    },

    add_rpidns: function(event) {
      if (this.validateHostnameOnly('addRpiDNSName') && this.ftRpiDNSRPZ.length > 0 && this.addRpiDNSModel !== null && this.addRpiDNSServer !== null && ((this.addRpiDNSType == 'secondary' && checkIP(this.addRpiDNSTypeIPNet)) || this.addRpiDNSType == 'primary')) {
        let doc = this;
        var data, promise;
        let rpzfeeds = [];
        let missingAction = false;
        this.ftRpiDNSRPZ.forEach(function(item) {
          if (!doc.ftRpiDNSRPZAction[item]) { missingAction = true; }
          rpzfeeds.push({ "feed": item, "action": doc.ftRpiDNSRPZAction[item] || "" });
        });
        if (missingAction) { event.preventDefault(); this.showInfo('Please select an action for all selected RPZ feeds', 3); return; }
        data = { id: this.addRpiDNSid, name: this.addRpiDNSName, comment: this.addRpiDNSComment, model: this.addRpiDNSModel.type, dns: this.addRpiDNSServer.type, updconf: this.addRpiDNSCheckConf, rpz: JSON.stringify(rpzfeeds), redirect: this.addRpiDNSRedirect, redirect_cname: this.addRpiDNSRedirectURL, logging: this.addRpiDNSLogs, logging_host: this.addRpiDNSLogsURL, dns_type: this.addRpiDNSType, dns_ipnet: this.addRpiDNSTypeIPNet };
        if (this.RpiDNSBttn == "Add") promise = axios.post('/io2data.php/rpidns', data); else promise = axios.put('/io2data.php/rpidns', data);
        promise.then((data) => {
          if (data.data[0].status == "success") { doc.clear_rpidns_modal(); doc.refreshRpiDNS(); }
          else { doc.showInfo(data.data[0].description, 3); }
        }).catch(error => { doc.showInfo('Unknown error!!!', 3); });
      } else {
        event.preventDefault();
        if (!this.validateHostnameOnly('addRpiDNSName') || this.addRpiDNSName.length == 0) this.showInfo('Please set correct RpiDNS name', 3);
        else if (this.addRpiDNSType == 'secondary' && !checkIP(this.addRpiDNSTypeIPNet)) this.showInfo('Please set a primary DNS server IP', 3);
        else if (this.addRpiDNSModel == null) this.showInfo('Please select RpiDNS model', 3);
        else if (this.addRpiDNSServer == null) this.showInfo('Please select DNS server software', 3);
        else if (this.ftRpiDNSRPZ.length == 0) this.showInfo('Please select RPZ feeds', 3);
        else this.showInfo('Please define all fields', 3);
      }
    },

    rpidns_delete: function(rpidns_id) {
      var el = this.$root || this;
      el.deleteRec = rpidns_id;
      el.deleteTbl = 'rpidns';
      el.modalMSG = '<b>You are about to delete selected RpiDNS. This action is irreversible!</b>';
      showModal('mConfDel');
    },

    addRpiDNSFeedActionComp: function(type) {
      return this.addRpiDNSFeedAction;
    },

    validateCustomAction: function(CustomActions) {
      let good = CustomActions == '' ? null : true;
      let gotcname = 0;
      CustomActions.split(/\r\n|\n|\r/).forEach(function(action) {
        let rule = action.trim().split("=", 2);
        switch (rule[0]) {
          case "local_aaaa": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv6(rule[1]); break;
          case "local_a": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv4(rule[1]); break;
          case "redirect_ip": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIP(rule[1]); break;
          case "local_cname": case "redirect_domain": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkHostName(rule[1]); gotcname++; break;
          case "local_txt": good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && true; break;
          default: good = good && (action.startsWith("#") || action.startsWith("//") || action == "");
        }
      });
      return good && (gotcname <= 1);
    },

    /**
     * Items provider function for bootstrap-vue-next BTable
     * In bootstrap-vue-next, the provider function receives a context object
     * and should return items directly or a Promise that resolves to items
     * 
     * @param {Object} ctx - Provider context with currentPage, perPage, filter, sortBy, signal
     * @param {string} apiUrl - The API URL to fetch data from (passed via closure or table ref)
     * @returns {Promise<Array>} Promise resolving to array of items
     */
    async tableProvider(ctx, apiUrl) {
      try {
        const response = await axios.get(apiUrl, { signal: ctx.signal });
        if (/DOCTYPE html/.test(response.data)) {
          window.location.reload(true);
          return [];
        }
        const items = response.data;
        this.totalRows = items.length;
        return items;
      } catch (error) {
        if (error.name === 'CanceledError' || error.name === 'AbortError') {
          // Request was cancelled, this is expected behavior
          return [];
        }
        console.error('Table provider error:', error);
        return [];
      }
    },

    /**
     * Legacy get_tables function for backward compatibility
     * This wraps the new tableProvider for tables that still use the old pattern
     * @deprecated Use tableProvider with provider prop instead
     */
    get_tables(obj) {
      // For backward compatibility, extract apiUrl from the obj parameter
      // In bootstrap-vue-next, we should use the provider prop instead
      const apiUrl = obj.apiUrl || obj;
      return this.tableProvider({ signal: new AbortController().signal }, apiUrl);
    },

    /**
     * Creates a provider function for a specific API URL
     * Use this to create provider functions for each table
     * @param {string} apiUrl - The API URL for the table
     * @returns {Function} Provider function for BTable
     */
    createTableProvider(apiUrl) {
      return (ctx) => this.tableProvider(ctx, apiUrl);
    },

    onFiltered(filteredItems) {
      this.checkedItems = []; this.checkAll = false;
      this.totalRows = filteredItems.length; this.currentPage = 1;
    },

    refreshTbl(table) {
      refreshTable(table);
    },

    importRec: function(action, table, row, target) {
      this.$root.ftImportRec = '';
      showModal('mImportRec');
    },

    mgmtRec: function(action, table, row, target) {
      this.$root.infoWindow = action == 'info' ? true : false;
      switch (action + ' ' + table) {
        case "add users":
          this.$root.ftUId = 0; this.$root.ftUNameProf = ""; this.$root.ftUPerm = 1;
          this.$root.ftUPwd = ""; this.$root.ftUpwdConf = "";
          showModal('mUAdd'); break;
        case "edit users":
          this.$root.ftUId = row.item.rowid; this.$root.ftUNameProf = row.item.name;
          this.$root.ftUPerm = row.item.perm; this.$root.ftUPwd = ""; this.$root.ftUpwdConf = "";
          showModal('mUAdd'); break;
        case "add tkeys_groups":
          this.$root.ftKeyGId = -1; this.$root.ftKeyGName = "";
          showModal('mTGroups'); break;
        case "edit tkeys_groups":
          this.$root.ftKeyGId = row.item.rowid; this.$root.ftKeyGName = row.item.group_name;
          showModal('mTGroups'); break;
        case "add tkeys":
          this.$root.ftKeyId = -1; this.$root.genRandom('tkeyName'); this.$root.genRandom('tkey');
          this.$root.ftKeyAlg = 'md5'; this.$root.ftKeyMGMT = false; this.$root.editRow = {};
          this.$root.get_lists('tkeys_groups_list', 'ftTKeysAllGroups'); this.$root.ftTKeysGroups = [];
          showModal('mConfEditTSIG'); break;
        case "info tkeys": case "edit tkeys": case "clone tkeys":
          this.$root.ftKeyId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftKeyName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftKey = row.item.tkey; this.$root.ftKeyAlg = row.item.alg;
          this.$root.ftKeyMGMT = (row.item.mgmt == 1); 
          this.$root.editRow = row.item;
          this.$root.get_lists('tkeys_groups_list', 'ftTKeysAllGroups');
          var tkey_groups = [];
          row.item.tkey_groups.forEach(function(el) { tkey_groups.push(el.rowid); });
          this.$root.ftTKeysGroups = tkey_groups;
          showModal('mConfEditTSIG'); break;
        case "add whitelists": case "add sources":
          this.$root.ftSrcId = -1; this.$root.ftSrcName = ''; this.$root.ftSrcURL = '';
          this.$root.ftSrcREGEX = ''; this.$root.ftSrcType = table; this.$root.ftSrcURLIXFR = '';
          this.$root.ftSrcMaxIOC = '0'; this.$root.ftSrcHotCacheAXFR = '900'; this.$root.ftSrcHotCacheIXFR = '0';
          this.$root.ftSrcTitle = (table == "sources") ? "Source" : "Whitelist";
          this.$root.editRow = {}; this.$root.ftSrcIoCType = 'mixed'; 
          this.$root.ftSrcKeepInCache = false;
          showModal('mConfEditSources'); 
          break;
        case "info whitelists": 
        case "edit whitelists": 
        case "clone whitelists":
        case "info sources": case "edit sources": case "clone sources":
          this.$root.ftSrcId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftSrcName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftSrcURL = row.item.url; this.$root.ftSrcREGEX = row.item.regex;
          this.$root.ftSrcIoCType = row.item.ioc_type; 
          this.$root.ftSrcKeepInCache = (row.item.keep_in_cache == 1);
          this.$root.ftSrcType = table;
          this.$root.ftSrcURLIXFR = (table == "sources") ? row.item.url_ixfr : '';
          this.$root.ftSrcMaxIOC = `${row.item.max_ioc}`;
          this.$root.ftSrcHotCacheAXFR = `${row.item.hotcache_time}`;
          this.$root.ftSrcHotCacheIXFR = `${row.item.hotcacheixfr_time}`;
          this.$root.ftSrcTitle = (table == "sources") ? "Source" : "Whitelist";
          this.$root.editRow = row.item;
          showModal('mConfEditSources'); 
          break;
        case "add servers":
          this.$root.ftSrvId = -1; this.$root.ftSrvName = ''; this.$root.ftSrvIP = '';
          this.$root.ftSrvPubIP = ''; this.$root.ftSrvNS = ''; this.$root.ftSrvEmail = '';
          this.$root.ftSrvMGMT = 0; this.$root.ftSrvTKeys = []; this.$root.ftSrvMGMTIP = '';
          this.$root.ftSrvSType = 0; this.$root.ftSrvURL = "";
          this.$root.ftCertFile = ""; this.$root.ftKeyFile = "";
          this.$root.ftCACertFile = ""; this.$root.ftCustomConfig = "";
          this.$root.ftSrvDisabled = 0; this.$root.ftSrvTrackDefault = 'off';
          // '' = inherit the ioc2rpz built-in default
          this.$root.ftSrvRLWindow = ''; this.$root.ftSrvRLMaxRequests = ''; this.$root.ftSrvRLMaxUnknownRequests = '';
          this.$root.get_lists('tkeys_mgmt', 'ftSrvTKeysAll'); this.$root.editRow = {};
          showModal('mConfEditSrv'); break;
        case "info servers": case "edit servers": case "clone servers":
          this.$root.ftSrvId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftSrvName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftSrvIP = row.item.ip; this.$root.ftSrvPubIP = row.item.pub_ip;
          this.$root.ftSrvNS = row.item.ns; this.$root.ftSrvEmail = row.item.email;
          this.$root.ftSrvMGMT = row.item.mgmt; this.$root.ftSrvSType = row.item.stype;
          this.$root.ftSrvURL = row.item.URL; this.$root.ftCertFile = row.item.certfile;
          this.$root.ftKeyFile = row.item.keyfile; this.$root.ftCACertFile = row.item.cacertfile;
          this.$root.ftCustomConfig = row.item.custom_config; this.$root.ftSrvDisabled = row.item.disabled;
          this.$root.ftSrvTrackDefault = row.item.track_default || 'off';
          // A NULL/absent stored limit binds to '' (inherit); 0 is a real value and must survive.
          this.$root.ftSrvRLWindow = rlToInput(row.item.rl_window);
          this.$root.ftSrvRLMaxRequests = rlToInput(row.item.rl_max_requests);
          this.$root.ftSrvRLMaxUnknownRequests = rlToInput(row.item.rl_max_unknown_requests);
          var IPs = '';
          row.item.mgmt_ips.forEach(function(el) { IPs += el.mgmt_ip + ' '; });
          this.$root.ftSrvMGMTIP = IPs.trim();
          this.$root.get_lists('tkeys_mgmt', 'ftSrvTKeysAll');
          var tkeys = [];
          row.item.tkeys.forEach(function(el) { tkeys.push(el.rowid); });
          this.$root.ftSrvTKeys = tkeys;
          this.$root.editRow = row.item;
          this.$root.editRow.mgmt_ips_str = this.$root.ftSrvMGMTIP;
          this.$root.editRow.tkeys_arr = this.$root.ftSrvTKeys;
          showModal('mConfEditSrv'); break;
        case "publish servers":
          this.$root.pushUpdatestoSRV(row.item.rowid); break;
        case "export servers":
          axios.get('/io2data.php/servercfg?rowid=' + row.item.rowid, { responseType: 'blob' }).then(function(response) {
            if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); }
            else {
              let blob = new Blob([response.data], { type: 'text/plain' });
              let link = document.createElement('a');
              link.href = window.URL.createObjectURL(blob);
              var sFN = response.headers['content-disposition'].match(/filename="([^"]+)"/)[1];
              link.download = sFN ? sFN : row.item.name + '.conf';
              link.click();
            }
          }).catch(function(error) { alert("export failed"); }); break;
        case "add rpzs":
          this.$root.RPZtabI = 0; this.$root.ftRPZProWindow = "hidden"; this.$root.ftRPZProWindowInfo = "";
          this.$root.ftRPZId = -1; this.$root.ftRPZName = '';
          this.$root.ftRPZSOA_Refresh = '86400'; this.$root.ftRPZSOA_UpdRetry = '3600';
          this.$root.ftRPZSOA_Exp = '2592000'; this.$root.ftRPZSOA_NXTTL = '7200';
          this.$root.ftRPZAXFR = '604800'; this.$root.ftRPZIXFR = '86400';
          this.$root.ftRPZCache = true; this.$root.ftRPZWildcard = true;
          this.$root.get_lists('rpz_servers', 'ftRPZSrvsAll'); this.$root.ftRPZSrvs = [];
          this.$root.get_lists('rpz_tkeys', 'ftRPZTKeysAll'); this.$root.ftRPZTKeys = [];
          this.$root.get_lists('rpz_sources', 'ftRPZSrcAll'); this.$root.ftRPZSrc = [];
          this.$root.get_lists('rpz_whitelists', 'ftRPZWLAll'); this.$root.ftRPZWL = [];
          this.$root.ftRPZAction = "nxdomain"; this.$root.ftRPZActionCustom = "";
          this.$root.ftRPZIOCType = "mixed"; this.$root.ftRPZNotify = "";
          this.$root.ftRPZTrackSources = 'Inherit';
          // '' = inherit from the server level
          this.$root.ftRPZRLWindow = ''; this.$root.ftRPZRLMaxRequests = '';
          this.$root.ftRPZDisabled = false; this.$root.editRow = {};
          showModal('mConfEditRPZ'); break;
        case "info rpzs": case "edit rpzs": case "clone rpzs":
          this.$root.RPZtabI = 0;
          this.$root.ftRPZProWindow = action == "info" ? "" : "hidden";
          this.$root.ftRPZId = action == "clone" ? -1 : row.item.rowid;
          this.$root.ftRPZName = action == "clone" ? row.item.name + "_clone" : row.item.name;
          this.$root.ftRPZSOA_Refresh = `${row.item.soa_refresh}`;
          this.$root.ftRPZSOA_UpdRetry = `${row.item.soa_update_retry}`;
          this.$root.ftRPZSOA_Exp = `${row.item.soa_expiration}`;
          this.$root.ftRPZSOA_NXTTL = `${row.item.soa_nx_ttl}`;
          this.$root.ftRPZAXFR = `${row.item.axfr_update}`;
          this.$root.ftRPZIXFR = `${row.item.ixfr_update}`;
          this.$root.ftRPZCache = (row.item.cache == 1); 
          this.$root.ftRPZWildcard = (row.item.wildcard == 1);
          this.$root.ftRPZAction = row.item.action;
          this.$root.ftRPZActionCustom = row.item.actioncustom ? JSON.parse(row.item.actioncustom) : "";
          this.$root.ftRPZIOCType = row.item.ioc_type; 
          this.$root.ftRPZTrackSources = row.item.track_sources || 'Inherit';
          // A NULL/absent stored limit binds to '' (inherit); 0 is a real value and must survive.
          this.$root.ftRPZRLWindow = rlToInput(row.item.rl_window);
          this.$root.ftRPZRLMaxRequests = rlToInput(row.item.rl_max_requests);
          this.$root.ftRPZDisabled = (row.item.disabled == 1);
          let vm = this;
          var RPZNotify = '';
          row.item.notify.forEach(function(el) { RPZNotify += el.notify + ' '; });
          this.$root.ftRPZNotify = RPZNotify.trim();
          let dig_srv = ""; let dig_tkey = "";
          this.$root.get_lists('rpz_servers', 'ftRPZSrvsAll');
          let list = [];
          row.item.servers.forEach(function(el) { list.push(el.rowid); dig_srv = dig_srv == "" ? el.pub_ip : dig_srv; });
          this.$root.ftRPZSrvs = list;
          this.$root.ftRPZInfoServerName = Array.isArray(row.item.servers) && row.item.servers.length ? row.item.servers[0].name : '';
          this.$root.ftRPZInfoServerIP = Array.isArray(row.item.servers) && row.item.servers.length ? row.item.servers[0].pub_ip : '';
          this.$root.ftRPZInfoTKeyName = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? row.item.tkeys[0].name : '';
          this.$root.ftRPZInfoTKeyAlg = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? 'hmac-' + row.item.tkeys[0].alg : '';
          this.$root.ftRPZInfoTKey = Array.isArray(row.item.tkeys) && row.item.tkeys.length ? row.item.tkeys[0].tkey : '';
          this.$root.get_lists('rpz_tkeys', 'ftRPZTKeysAll');
          list = [];
          row.item.tkeys.forEach(function(el) { list.push(el.rowid); dig_tkey = dig_tkey == "" ? "hmac-" + el.alg + ":" + el.name + ":" + el.tkey : dig_tkey; });
          this.$root.ftRPZTKeys = list;
          this.$root.ftRPZInfoDig = this.$root.ftRPZInfoServerIP && this.$root.ftRPZInfoTKeyName ? "dig +tcp @" + dig_srv + " -y " + dig_tkey + " " + row.item.name + " SOA" : '';
          this.$root.get_lists('rpz_sources', 'ftRPZSrcAll');
          list = [];
          row.item.sources.forEach(function(el) { list.push(el.rowid); });
          this.$root.ftRPZSrc = list;
          this.$root.get_lists('rpz_whitelists', 'ftRPZWLAll');
          list = [];
          row.item.whitelists.forEach(function(el) { list.push(el.rowid); });
          this.$root.ftRPZWL = list;
          this.$root.editRow = row.item;
          this.$root.editRow.notify_str = this.$root.ftRPZNotify;
          this.$root.editRow.servers_arr = this.$root.ftRPZSrvs;
          this.$root.editRow.tkeys_arr = this.$root.ftRPZTKeys;
          this.$root.editRow.sources_arr = this.$root.ftRPZSrc;
          this.$root.editRow.whitelists_arr = this.$root.ftRPZWL;
          showModal('mConfEditRPZ'); break;
        default:
          alert(action + ' ' + table);
      }
    },

    requestDelete: function(table, row) {
      this.$root.deleteRec = row.item.rowid; this.$root.deleteTbl = table;
      this.$root.modalMSG = '<b>Do you want to delete ' + escapeHtml(row.item.name) + '?</b>';
      showModal('mConfDel');
    },

    validateName: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateNameAT: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9@\/\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateUName: function(vrbl) {
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    /**
     * Formatter for zone (feed) names: the same character filtering as formatName,
     * plus canonicalisation to lower case.
     *
     * The ioc2rpz server canonicalises zone names to lower case (RFC 4343), so a
     * mixed-case name typed here would come back lower-cased from an imported config
     * and look like a different zone. Normalizing on input keeps the GUI and the server
     * in agreement.
     */
    formatZoneName: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g, "").toLowerCase();
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatName: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateB64: function(vrbl) {
      return (this.$data[vrbl].length > 16 && /^(?:[A-Za-z0-9\+\/]{4})*(?:[A-Za-z0-9\+\/]{2}==|[A-Za-z0-9\+\/]{3}=)?$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatB64: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9/=\+\/]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    /**
     * Validates an optional DNS rate limit input.
     *
     * Empty means inherit, so it yields null (no validation state shown), matching
     * validateInt()'s treatment of an empty field. A value that is present must be a
     * non-negative integer at or above `min`, and at or below `max` when one applies:
     * `min` is 1 for `window` (a zero-length window is meaningless) and 0 for the
     * maximums, where 0 legitimately means "refuse every request in that bucket".
     * `window` is additionally capped at RL_WINDOW_MAX, because the window is stored
     * with every rate limit entry and decides when it is swept - an absurd window keeps
     * entries alive that long. The maximums have no cap: a large threshold only makes
     * the limiter permissive.
     *
     * Validating here matters because the server only logs and ignores an invalid
     * value and then falls back to the next level, so a bad value would silently do
     * nothing rather than fail loudly.
     *
     * @param {string} vrbl Name of the reactive field holding the input
     * @param {number} min Smallest accepted value
     * @param {number} [max] Largest accepted value, omitted when unbounded
     * @returns {boolean|null} true valid, false invalid, null empty (inherit)
     */
    validateRateLimit: function(vrbl, min, max) {
      var v = this.$data[vrbl];
      if (v === null || v === undefined || String(v).length === 0) return null;
      v = String(v);
      if (!/^[0-9]+$/.test(v)) return false;
      var n = parseInt(v, 10);
      if (n < min) return false;
      return max === undefined || n <= max;
    },

    validateInt: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^[0-9]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatInt: function(val, e) {
      let a = val.replace(/[^0-9]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateURL: function(vrbl) {
      return (this.$data[vrbl].length > 0 && checkSourceURL(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatURL: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9/=:\?#.\-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatURLAT: function(val, e) {
      let a = val.replace(/[^A-Za-z0-9@/=:\?#.\-_&]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    formatSourceURL: function(val, e) {
      let a;
      // shell: and file: are passed through untouched - a shell command needs spaces,
      // quotes and redirection, and a local path may contain anything the filesystem
      // allows. An IXFR meta URL starting with the literal [:AXFR:] is also left alone.
      // That last test used to be /^[:AXFR:]/, which is a character class matching a
      // leading ":", "A", "X", "F" or "R" rather than the literal token, so it never did
      // what it was named for.
      if (/^shell:/.test(val) || /^file:/.test(val) || /^\[:AXFR:\]/.test(val)) a = val;
      else a = val.replace(SOURCE_URL_DISALLOWED, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateLocFile: function(vrbl) {
      return (this.$data[vrbl].length > 0) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatLocFile: function(val, e) {
      let a = val.replace(LOC_FILE_DISALLOWED, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateIXFRURL: function(vrbl) {
      return this.$data[vrbl].length == 0 ? null : (this.validateURL(vrbl) || this.$data[vrbl] == '[:AXFR:]' || (/^\[:AXFR:\]((\?|\&)[;&a-zA-Z0-9\d%_.~+=-]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?(\#[-a-zA-Z0-9\d_]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?$/.test(this.$data[vrbl])));
    },

    formatIXFRURL: function(val, e) {
      let a;
      // Same rules as the source URL field: an IXFR path is either a meta URL starting
      // with the literal [:AXFR:], a shell:/file: value, or a fetched URL. Sharing
      // SOURCE_URL_DISALLOWED keeps the two fields consistent - they previously used
      // different character sets purely because one escaped the hyphen and the other
      // did not.
      if (/^shell:/.test(val) || /^file:/.test(val) || /^\[:AXFR:\]/.test(val)) a = val;
      else a = val.replace(SOURCE_URL_DISALLOWED, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateREGEX: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateIP: function(vrbl) {
      return (this.$data[vrbl].length > 0 && checkIP(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatIP: function(val, e) {
      let a = val.replace(/[^0-9\.:\-]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateIPList: function(vrbl) {
      return (this.$data[vrbl].length > 0 && this.$data[vrbl].trim().split(/,|\s|\;/g).every(checkIP)) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatIPList: function(val, e) {
      let a = val.replace(/[^0-9\.:\-,; ]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateHostname: function(vrbl) {
      return (this.$data[vrbl].length > 5 && checkHostName(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateHostnameNum: function(vrbl) {
      return (this.$data[vrbl].length > 5 && checkHostNameNum(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    validateHostnameOnly: function(vrbl) {
      return (this.$data[vrbl].length > 2 && checkHostNameOnly(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatHostname: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validateEmail: function(vrbl) {
      return (this.$data[vrbl].length > 0 && /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(this.$data[vrbl].toLowerCase())) ? true : this.$data[vrbl].length == 0 ? null : false;
    },

    formatEmail: function(val, e) {
      let a = val.replace(/[^a-zA-Z0-9\.\-\_@]/g, "");
      if (e) e.currentTarget.value = a;
      return a;
    },

    validatePass: function(pass1) {
      return ((this.$data[pass1].length > 7 && /([0-9])/.test(this.$data[pass1]) && /([a-z])/.test(this.$data[pass1]) && /([A-Z])/.test(this.$data[pass1]) && /([!,%,&,@,#,$,^,*,?,_,~,\,,\.])/.test(this.$data[pass1])) || this.$data[pass1].length > 15) ? true : this.$data[pass1].length == 0 ? null : false;
    },

    validatePassMatch: function(pass1, pass2) {
      return this.$data[pass1] == this.$data[pass2] ? true : false;
    },

    get_lists: function(table, variable) {
      let promise = axios.get('/io2data.php/' + table);
      promise.then((data) => {
        if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); }
        else { this.$root.$data[variable] = data.data; }
      }).catch(error => { this.$root.$data[variable] = []; });
    },

    mgmtTableOk: function(response, obj, table) {
      if (response.data.status == "ok") {
        refreshTable('io2tbl_' + table);
      } else {
        alert('sql error while adding ' + table);
      }
    },

    mgmtTableError: function(error, obj, table) {
      alert('error while adding ' + table + ' ');
    },

    tblMgmtTKeyRecord: function(ev, table) {
      if (this.validateName('ftKeyName') && this.validateB64('ftKey')) {
        var obj = this;
        if ((this.ftKeyId != -1 && (this.$root.ftKeyName != this.editRow.name || this.$root.ftKey != this.editRow.tkey || this.$root.ftKeyAlg != this.editRow.alg || this.$root.ftKeyMGMT != this.editRow.mgmt || this.$root.ftTKeysGroups != this.editRow.tkey_groups))) toggleUpdates(0, this, true);
        let data = { tKeyId: this.ftKeyId, tKeyName: this.ftKeyName, tKey: this.ftKey, tKeyAlg: this.ftKeyAlg, tKeyMGMT: this.ftKeyMGMT, tTKeysGroups: JSON.stringify(this.ftTKeysGroups) };
        if (this.ftKeyId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyName')) this.$refs.formKeyName.$el.focus();
        else this.$refs.formKey.$el.focus();
      }
    },

    tblMgmtTKeyGRecord: function(ev, table) {
      if (this.validateName('ftKeyGName')) {
        var obj = this;
        let data = { tKeyGId: this.ftKeyGId, tKeyGName: this.ftKeyGName };
        if (this.ftKeyGId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyGName')) this.$refs.formKeyGName.$el.focus();
        else this.$refs.formKey.$el.focus();
      }
    },

    tblMgmtSrcRecord: function(ev, table) {
      if (this.validateName('ftSrcName') && this.validateURL('ftSrcURL') && (this.validateREGEX('ftSrcREGEX') == null || this.validateREGEX('ftSrcREGEX')) && (((this.validateIXFRURL('ftSrcURLIXFR') || this.validateIXFRURL('ftSrcURLIXFR') == null) && this.ftSrcType == 'sources') || this.ftSrcType != 'sources') && this.validateInt('ftSrcMaxIOC') && this.validateInt('ftSrcHotCacheAXFR') && this.validateInt('ftSrcHotCacheIXFR')) {
        var obj = this;
        if (this.ftSrcId != -1 && (this.ftSrcName != this.editRow.name || this.ftSrcURL != this.editRow.url || this.ftSrcREGEX != this.editRow.regex || this.ftSrcMaxIOC != this.editRow.max_ioc || this.ftSrcHotCacheAXFR != this.editRow.hotcache_time || this.ftSrcHotCacheIXFR != this.editRow.hotcacheixfr_time || (this.ftSrcURLIXFR != this.editRow.url_ixfr && this.ftSrcType == 'sources') || this.ftSrcIoCType != this.editRow.ioc_type || this.ftSrcKeepInCache != this.editRow.keep_in_cache)) toggleUpdates(0, this, true);
        let data = { tSrcId: this.ftSrcId, tSrcName: this.ftSrcName, tSrcURL: this.ftSrcURL, tSrcREGEX: this.ftSrcREGEX, tSrcURLIXFR: this.ftSrcURLIXFR, tSrcMaxIOC: this.ftSrcMaxIOC, tSrcHotCacheAXFR: this.ftSrcHotCacheAXFR, tSrcHotCacheIXFR: this.ftSrcHotCacheIXFR, tSrcIoCType: this.ftSrcIoCType, tSrcKeepInCache: this.ftSrcKeepInCache };
        if (this.ftSrcId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrcName')) this.$refs.formSrcName.$el.focus();
        else if (!this.validateURL('ftSrcURL') && this.validateREGEX('ftSrcURL') != null) this.$refs.formSrcURL.$el.focus();
        else if (!this.validateREGEX('ftSrcREGEX') && this.validateREGEX('ftSrcREGEX') != null) this.$refs.formREGEX.$el.focus();
        else this.$refs.formSrcURLIXFR.$el.focus();
      }
    },

    manageUsers: function(ev) {
      if (this.validateUName('ftUNameProf') && this.validatePass('ftUPwd') && this.validatePassMatch('ftUPwd', 'ftUpwdConf')) {
        let obj = this;
        let data = { rowid: this.ftUId, name: this.ftUNameProf, pwd: this.ftUPwd, perm: this.ftUPerm };
        if (this.ftUId == 0) {
          axios.post('/io2data.php/users', data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, 'users'); }).catch(function(error) { obj.mgmtTableError(error, obj, 'users'); });
        } else {
          axios.put('/io2data.php/users', data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, 'users'); }).catch(function(error) { obj.mgmtTableError(error, obj, 'users'); });
        }
      } else if (ev != null) {
        ev.preventDefault();
      }
    },

    tblMgmtSrvRecord: function(ev, table) {
      if (this.validateName('ftSrvName') && (this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null) && (this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null) && this.validateHostname('ftSrvNS') && this.validateEmail('ftSrvEmail') && (this.validateIPList('ftSrvMGMTIP') || this.validateIP('ftSrvMGMTIP') == null) && this.validateRateLimit('ftSrvRLWindow', 1, RL_WINDOW_MAX) !== false && this.validateRateLimit('ftSrvRLMaxRequests', 0) !== false && this.validateRateLimit('ftSrvRLMaxUnknownRequests', 0) !== false) {
        var obj = this;
        if (this.ftSrvName != this.editRow.name || this.ftSrvIP != this.editRow.ip || this.ftSrvPubIP != this.editRow.pub_ip || this.ftSrvNS != this.editRow.ns || this.ftSrvEmail != this.editRow.email || this.ftSrvMGMT != this.editRow.mgmt || this.ftSrvSType != this.editRow.stype || this.ftSrvURL != this.editRow.URL || this.ftSrvMGMTIP != this.editRow.mgmt_ips_str || this.ftSrvTKeys != this.editRow.tkeys_arr || this.ftCertFile != this.editRow.certfile || this.ftKeyFile != this.editRow.keyfile || this.ftCACertFile != this.editRow.cacertfile || this.ftCustomConfig != this.editRow.custom_config || this.ftSrvTrackDefault != (this.editRow.track_default || 'off') || this.ftSrvRLWindow != rlToInput(this.editRow.rl_window) || this.ftSrvRLMaxRequests != rlToInput(this.editRow.rl_max_requests) || this.ftSrvRLMaxUnknownRequests != rlToInput(this.editRow.rl_max_unknown_requests)) toggleUpdates(0, this, true);
        let data = { tSrvId: this.ftSrvId, tSrvName: this.ftSrvName, tSrvIP: this.ftSrvIP, tSrvPubIP: this.ftSrvPubIP, tSrvNS: this.ftSrvNS, tSrvEmail: this.ftSrvEmail, tSrvMGMT: this.ftSrvMGMT, tSrvMGMTIP: JSON.stringify(this.ftSrvMGMTIP.split(/,|\s/g).filter(String)), tSrvTKeys: JSON.stringify(this.ftSrvTKeys), tSrvDisabled: this.ftSrvDisabled, tSrvSType: this.ftSrvSType, tSrvURL: this.ftSrvURL, tCertFile: this.ftCertFile, tKeyFile: this.ftKeyFile, tCACertFile: this.ftCACertFile, tCustomConfig: this.ftCustomConfig, tSrvTrackDefault: this.ftSrvTrackDefault, tSrvRLWindow: this.ftSrvRLWindow, tSrvRLMaxRequests: this.ftSrvRLMaxRequests, tSrvRLMaxUnknownRequests: this.ftSrvRLMaxUnknownRequests };
        if (this.ftSrvId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrvName')) this.$refs.formSrvName.$el.focus();
        else if (!(this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null)) this.$refs.formSrvPubIP.$el.focus();
        else if (!(this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null)) this.$refs.formSrvIP.$el.focus();
        else if (!this.validateHostname('ftSrvNS')) this.$refs.formSrvNS.$el.focus();
        else if (!this.validateEmail('ftSrvEmail')) this.$refs.formSrvEmail.$el.focus();
        else if (!this.validateLocFile('ftCertFile')) this.$refs.formCertFile.$el.focus();
        else if (!this.validateLocFile('ftKeyFile')) this.$refs.formKeyFile.$el.focus();
        else if (!this.validateLocFile('ftCACertFile')) this.$refs.formCACertFile.$el.focus();
        // Rate limits block the save, so focus has to reach them: a keyboard or screen
        // reader user is otherwise left on an unrelated field with no way to find the
        // one that is refusing to submit.
        else if (this.validateRateLimit('ftSrvRLWindow', 1, RL_WINDOW_MAX) === false) this.$refs.formSrvRLWindow.$el.focus();
        else if (this.validateRateLimit('ftSrvRLMaxRequests', 0) === false) this.$refs.formSrvRLMaxRequests.$el.focus();
        else if (this.validateRateLimit('ftSrvRLMaxUnknownRequests', 0) === false) this.$refs.formSrvRLMaxUnknownRequests.$el.focus();
        else this.$refs.formSrcNotify.$el.focus();
      }
    },

    tblMgmtRPZRecord: function(ev, table) {
      if (this.validateHostnameNum('ftRPZName') && (this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null) && ((this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local') || this.ftRPZAction != 'local') && this.validateInt('ftRPZSOA_Refresh') && this.validateInt('ftRPZSOA_UpdRetry') && this.validateInt('ftRPZSOA_Exp') && this.validateInt('ftRPZSOA_NXTTL') && this.validateInt('ftRPZAXFR') && this.validateInt('ftRPZIXFR') && this.validateRateLimit('ftRPZRLWindow', 1, RL_WINDOW_MAX) !== false && this.validateRateLimit('ftRPZRLMaxRequests', 0) !== false) {
        var obj = this;
        if (this.ftRPZName != this.editRow.name || this.ftRPZSOA_Refresh != this.editRow.soa_refresh || this.ftRPZSOA_UpdRetry != this.editRow.soa_update_retry || this.ftRPZSOA_Exp != this.editRow.soa_expiration || this.ftRPZSOA_NXTTL != this.editRow.soa_nx_ttl || this.ftRPZAXFR != this.editRow.axfr_update || this.ftRPZIXFR != this.editRow.ixfr_update || this.ftRPZCache != this.editRow.cache || this.ftRPZWildcard != this.editRow.wildcard || this.ftRPZAction != this.editRow.action || this.ftRPZIOCType != this.editRow.ioc_type || this.editRow.notify_str != this.ftRPZNotify || this.editRow.servers_arr != this.ftRPZSrvs || this.editRow.tkeys_arr != this.ftRPZTKeys || this.editRow.sources_arr != this.ftRPZSrc || this.editRow.whitelists_arr != this.ftRPZWL || this.ftRPZActionCustom != this.editRow.actioncustom || this.ftRPZDisabled != this.editRow.disabled || this.ftRPZTrackSources != (this.editRow.track_sources || 'Inherit') || this.ftRPZRLWindow != rlToInput(this.editRow.rl_window) || this.ftRPZRLMaxRequests != rlToInput(this.editRow.rl_max_requests)) toggleUpdates(0, this, true);
        let data = { tRPZId: this.ftRPZId, tRPZName: this.ftRPZName, tRPZSOA_Refresh: this.ftRPZSOA_Refresh, tRPZSOA_UpdRetry: this.ftRPZSOA_UpdRetry, tRPZSOA_Exp: this.ftRPZSOA_Exp, tRPZSOA_NXTTL: this.ftRPZSOA_NXTTL, tRPZCache: this.ftRPZCache, tRPZWildcard: this.ftRPZWildcard, tRPZNotify: JSON.stringify(this.ftRPZNotify.split(/,|\s/g).filter(String)), tRPZSrvs: JSON.stringify(this.ftRPZSrvs), tRPZIOCType: this.ftRPZIOCType, tRPZAXFR: this.ftRPZAXFR, tRPZIXFR: this.ftRPZIXFR, tRPZDisabled: this.ftRPZDisabled, tRPZTKeys: JSON.stringify(this.ftRPZTKeys), tRPZWL: JSON.stringify(this.ftRPZWL), tRPZSrc: JSON.stringify(this.ftRPZSrc), tRPZAction: this.ftRPZAction, tRPZActionCustom: JSON.stringify(this.ftRPZActionCustom), tRPZTrackSources: this.ftRPZTrackSources, tRPZRLWindow: this.ftRPZRLWindow, tRPZRLMaxRequests: this.ftRPZRLMaxRequests };
        if (this.ftRPZId == -1) {
          axios.post('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        } else {
          axios.put('/io2data.php/' + table, data).then((data) => { if (/DOCTYPE html/.test(data.data)) { window.location.reload(true); } else obj.mgmtTableOk(data, obj, table); }).catch(function(error) { obj.mgmtTableError(error, obj, table); });
        }
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateHostnameNum('ftRPZName')) this.$refs.formRPZName.$el.focus();
        else if (!((this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null))) this.$refs.formRPZNotify.$el.focus();
        else if (!this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local') this.$refs.formRPZActionCustom.$el.focus();
        else if (!this.validateInt('ftRPZSOA_Refresh')) this.$refs.formRPZSOA_Refresh.$el.focus();
        else if (!this.validateInt('ftRPZSOA_UpdRetry')) this.$refs.formRPZSOA_UpdRetry.$el.focus();
        else if (!this.validateInt('ftRPZSOA_Exp')) this.$refs.formRPZSOA_Exp.$el.focus();
        else if (!this.validateInt('ftRPZSOA_NXTTL')) this.$refs.formRPZSOA_NXTTL.$el.focus();
        else if (!this.validateInt('ftRPZAXFR')) this.$refs.formRPZAXFR.$el.focus();
        // See the server handler: an invalid rate limit blocks the save, so focus must
        // land on it rather than on the last field of the form.
        else if (this.validateRateLimit('ftRPZRLWindow', 1, RL_WINDOW_MAX) === false) this.$refs.formRPZRLWindow.$el.focus();
        else if (this.validateRateLimit('ftRPZRLMaxRequests', 0) === false) this.$refs.formRPZRLMaxRequests.$el.focus();
        else this.$refs.formRPZIXFR.$el.focus();
      }
    },

    tblDeleteRecord: function(table, rowid) {
      var el = this;
      if (table == 'rpidns') {
        axios.delete('/io2data.php/rpidns', { data: { id: rowid } }).then(function(response) {
          if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); }
          else if (response.data[0] && response.data[0].status == "success") { el.refreshRpiDNS(); }
          else { el.showInfo((response.data[0] && response.data[0].description) || 'Error deleting RpiDNS', 3); }
        }).catch(function(error) { el.showInfo('Error deleting RpiDNS', 3); });
        return;
      }
      if (table != 'users') toggleUpdates(0, this, true);
      axios.delete('/io2data.php/' + table + '?rowid=' + JSON.stringify(rowid)).then(function(response) {
        if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); }
        else if (response.data.status == "ok") { refreshTable('io2tbl_' + table); }
        else { alert('sql error while deleting ' + table + ' ' + rowid); }
      }).catch(function(error) { alert('error while deleting ' + table + ' ' + rowid); });
    },

    pushUpdatestoSRV: function(SrvId) {
      var obj = this;
      toggleUpdates(0, obj, false);
      axios.post(`/io2data.php/publish_upd?SrvId=${SrvId}`).then(function(response) {
        if (response.data.status == "ok") {
          obj.showInfo('Configuration will be updated in a few seconds', 3);
          toggleUpdates(0, obj, false); refreshTable('servers');
        } else {
          if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); } else alert('Publishing error');
        }
      }).catch(function(error) { alert('Publishing error'); });
    },

    showInfo: function(msg, time) {
      var self = this;
      this.msgInfoMSG = escapeHtml(msg); this.mInfoMSGvis = true;
      setTimeout(function() { self.mInfoMSGvis = false; }, time * 1000);
    },

    ImportConfig: function(ev) {
      var file = new FileReader();
      var vm = this;
      file.onload = function(e) { ImportIOC2RPZ(vm, e.target.result); };
      file.readAsText(vm.ftImpFiles[0]);
    },

    ImportConfigLine: function(ev) {
      ImportIOC2RPZ(this, this.ftImportRec);
    },

    checkImpFile: function(e) {
      this.ftImpFiles = e.dataTransfer.files;
      this.ftImpFileDesc = 'File name: ' + encodeURI(this.ftImpFiles[0].name) + ", size: " + this.ftImpFiles[0].size + ' bytes';
    },

    alert: function(txt) { alert(txt); },

    copyToClipboard(ref) {
      var el = this.$refs[ref].$el || this.$refs[ref];
      if (navigator.clipboard) {
        navigator.clipboard.writeText(el.value);
      } else {
        el.select();
        document.execCommand('copy');
      }
    },

    copyToClipboardID(id) {
      copyToClipboardID(id);
    },

    genRandom(type) {
      switch (type) {
        case "tkeyName":
          this.$root.ftKeyName = 'tkey-' + Math.random().toString(36).substr(2, 10) + '-' + Math.random().toString(36).substr(2, 10);
          break;
        case "tkey":
          let key = new Uint8Array(this.$root.ftKeyAlg == 'md5' ? 16 : this.$root.ftKeyAlg == 'sha256' ? 32 : 64);
          do {
            window.crypto.getRandomValues(key);
            this.$root.ftKey = btoa(String.fromCharCode.apply(null, key));
          } while (!this.validateB64('ftKey'));
          break;
      }
    },

    changeTab: function(tab) {
      history.pushState(null, null, '#tabs_menu/' + tab);
      // Use tabTableMap instead of $children (removed in Vue 3)
      const tableName = this.tabTableMap[tab];
      if (tableName) {
        refreshTable('io2tbl_' + tableName);
      }
    },

    signOut: function() {
      axios.post('/io2auth.php/logout').then(function(response) { window.location.reload(true); });
    },

    /**
     * Template wrapper for the pure `renderSources` helper.
     *
     * Exposes the module-scope `renderSources` (see top of io2.js) to the
     * IOC lookup markup so the view can render one badge per source
     * (kind === 'badges'), an "attribution unavailable" indication
     * (kind === 'unavailable'), or nothing (kind === 'none').
     *
     * @param {*} sources - the feed object's `sources` field
     * @returns {{kind: string, badges?: Array<{text: string}>}}
     */
    renderSources: function(sources) {
      return renderSources(sources);
    },

    /**
     * Open the IOC lookup modal and (re)load the server list.
     *
     * Resets any previous result/error state so the view opens clean, then
     * loads the selectable servers into ftLookupServersAll via get_lists
     * (the `rpz_servers` endpoint returns [{value: rowid, text: name}]).
     */
    iocLookupShowModal: function() {
      this.$root.ftLookupIoc = '';
      this.$root.ftLookupServer = '';
      this.$root.ftLookupResults = [];
      this.$root.ftLookupError = '';
      this.$root.ftLookupNotFound = false;
      this.$root.ftLookupSubmitted = false;
      this.$root.ftLookupInProgress = false;
      this.$root.get_lists('rpz_servers', 'ftLookupServersAll');
      showModal('mIocLookup');
    },

    /**
     * Look up a single indicator against the selected server's management
     * interface (through the backend Mgmt_Proxy) and render the result.
     *
     * Client-side guard (Req 8.2): if the indicator is empty (length 0) or
     * longer than 2048 characters, or no server is selected, set a
     * field-specific error message and DO NOT call the proxy.
     *
     * On a successful proxy response:
     *  - an array of feed objects ⇒ render each feed (name/type/sources);
     *    an empty array ⇒ show the "not found in any feed" message (Req 8.5).
     *  - a {status:"failed", error:...} object ⇒ map the classified error
     *    (connection/server/timeout/validation) to a user message that never
     *    reveals credentials (Req 8.6).
     *
     * @param {Event} [ev] - optional DOM event (prevented on a guard failure)
     */
    iocLookup: function(ev) {
      var obj = this;
      var ioc = (this.$root.ftLookupIoc == null) ? '' : String(this.$root.ftLookupIoc);
      var server = this.$root.ftLookupServer;

      // Reset previous results/state before validating a new submission.
      this.$root.ftLookupResults = [];
      this.$root.ftLookupNotFound = false;
      this.$root.ftLookupError = '';

      // --- Client-side guard (Req 8.1, 8.2): no proxy call on invalid input ---
      if (ioc.length === 0) {
        this.$root.ftLookupError = 'Please enter an indicator to look up.';
        if (ev != null) ev.preventDefault();
        return;
      }
      if (ioc.length > 2048) {
        this.$root.ftLookupError = 'The indicator must be 2048 characters or fewer.';
        if (ev != null) ev.preventDefault();
        return;
      }
      if (server === '' || server === null || typeof server === 'undefined') {
        this.$root.ftLookupError = 'Please select a target server.';
        if (ev != null) ev.preventDefault();
        return;
      }

      this.$root.ftLookupSubmitted = true;
      this.$root.ftLookupInProgress = true;

      axios.get('/io2data.php/ioc_lookup?ioc=' + encodeURIComponent(ioc) + '&server=' + encodeURIComponent(server)).then(function(response) {
        if (/DOCTYPE html/.test(response.data)) { window.location.reload(true); return; }
        obj.$root.ftLookupInProgress = false;
        var data = response.data;
        // Classified proxy failure (Req 8.6): never surface credentials.
        if (data && data.status === 'failed') {
          obj.$root.ftLookupError = obj.iocLookupErrorMessage(data);
          return;
        }
        // Extract the feed list from either the bare-array shape or the real
        // ioc2rpz shape { ioc, tkey, data: [ { ioc, feeds: [...] } ] }.
        var feeds = obj.extractLookupFeeds(data);
        if (feeds === null) {
          obj.$root.ftLookupError = 'Received an unexpected response from the management interface.';
        } else if (feeds.length === 0) {
          obj.$root.ftLookupNotFound = true; // Req 8.5
        } else {
          obj.$root.ftLookupResults = feeds; // Req 8.4, 9.1-9.4
        }
      }).catch(function(error) {
        // Network/transport failure reaching the proxy itself.
        obj.$root.ftLookupInProgress = false;
        obj.$root.ftLookupError = 'Could not connect to the management interface.';
      });
    },

    /**
     * Map a classified proxy failure payload to a user-facing message.
     *
     * The backend classifies failures as connection/server/timeout/validation
     * and never includes the TSIG name/secret, so the message is safe to show
     * as-is (Req 8.6).
     *
     * @param {{error: string, code?: number, reason?: string}} data
     * @returns {string}
     */
    /**
     * Normalize a management-interface lookup response into a flat array of
     * feed view-models, or null when the shape is unrecognized.
     *
     * Handles both the bare-array shape (a list of feed objects) and the real
     * ioc2rpz shape:
     *   { "ioc": "...", "tkey": "...", "data": [ { "ioc": "...", "feeds": [...] } ] }
     * The per-indicator `feeds` arrays are flattened together. Each feed entry
     * may be an object (with feed name / type / sources) or a bare string
     * (a feed/zone name), so both are normalized to { feed, type, sources }.
     *
     * @param {*} data - parsed response body from the proxy
     * @returns {Array<{feed:string,type:string,sources:*}>|null}
     */
    extractLookupFeeds: function(data) {
      // Collect { matchedIoc, feed } pairs. `matchedIoc` is the specific
      // indicator (from each data[].ioc) that matched this feed. A single
      // query can match through more than one indicator -- e.g. the exact name
      // and a wildcard parent -- so the same feed can legitimately appear under
      // different matched indicators. Carrying matchedIoc lets the view group
      // the results and explain those repeats instead of looking duplicated.
      var raw = null;
      if (Array.isArray(data)) {
        raw = data.map(function(f) { return { matchedIoc: '', feed: f }; });
      } else if (data && Array.isArray(data.data)) {
        raw = [];
        data.data.forEach(function(entry) {
          var mi = (entry && entry.ioc != null) ? String(entry.ioc) : '';
          if (entry && Array.isArray(entry.feeds)) {
            entry.feeds.forEach(function(f) { raw.push({ matchedIoc: mi, feed: f }); });
          } else if (Array.isArray(entry)) {
            entry.forEach(function(f) { raw.push({ matchedIoc: mi, feed: f }); });
          }
        });
      } else if (data && Array.isArray(data.feeds)) {
        raw = data.feeds.map(function(f) { return { matchedIoc: '', feed: f }; });
      }
      if (raw === null) return null;
      return raw.map(function(item) {
        var f = item.feed;
        if (f && typeof f === 'object') {
          return {
            matchedIoc: item.matchedIoc,
            feed: f.feed || f.zone || f.name || f.rpz || '',
            type: f.type || f.ioc_type || '',
            sources: f.sources
          };
        }
        // Bare string feed/zone name (attribution unavailable / disabled).
        return { matchedIoc: item.matchedIoc, feed: String(f), type: '', sources: undefined };
      });
    },

    iocLookupErrorMessage: function(data) {
      switch (data.error) {
        case 'connection':
          return 'Could not connect to the management interface.';
        case 'timeout':
          return 'The request timed out.';
        case 'server':
          return 'The management interface returned an error' + (data.code ? ' (code ' + data.code + ')' : '') + '.';
        case 'validation':
          if (data.reason === 'server') return 'Please select a valid target server.';
          if (data.reason === 'ioc') return 'The indicator is invalid or too long.';
          return 'The lookup request was rejected as invalid.';
        default:
          return 'The lookup failed.';
      }
    },

    exportShowModal: function(format) {
      this.$root.ftExFormat = format;
      this.$root.get_lists('rpz_lists', 'ftExRPZAll');
      this.$root.ftExRPZ = [];
      this.$root.rpzExportSAll = false;
      showModal('mExpRPZ');
    },

    rpzExportToggleAll: function(checked) {
      this.ftExRPZ = checked ? this.ftExRPZAll.map(function(el) { return el.value; }) : [];
    },

    exportDNSConfig: async function() {
      let p = axios.get('/io2data.php/rpzs?rowid=' + JSON.stringify(this.$root.ftExRPZ));
      var [rpzs] = await Promise.all([p]);
      var keys = []; var options = "";
      var zone_opt = []; zone_opt['fqdn'] = ""; zone_opt['mixed'] = ""; zone_opt['ip'] = "";
      var keys_txt = ""; var zones = ""; let tkey_str = "";

      switch (this.$root.ftExFormat) {
        case 'bind':
          rpzs.data.forEach(function(el) {
            let servers = "";
            el['servers'].forEach(function(srv) {
              if (el['tkeys'].length == 0) { tkey_str = ""; } else { tkey_str = ` key "${el['tkeys'][0]['name']}"`; }
              servers += `${srv['pub_ip']} ${tkey_str};`;
            });
            zones += `\nzone "${el['name']}" {\n  type slave;\n  file "/var/cache/bind/${el['name']}";\n  masters {${servers}};\n};\n`;
            zone_opt[el['ioc_type']] += `\n    zone "${el['name']}" policy ` + (el['action'] == 'local' ? 'given' : el['action']) + ";";
            if (el['tkeys'].length > 0) {
              keys[el['tkeys'][0]['name']] = [];
              keys[el['tkeys'][0]['name']]['name'] = el['tkeys'][0]['name'];
              keys[el['tkeys'][0]['name']]['alg'] = el['tkeys'][0]['alg'];
              keys[el['tkeys'][0]['name']]['tkey'] = el['tkeys'][0]['tkey'];
            }
          });
          options = `\noptions {\n  #This is just options for RPZs. Add other options as required\n  recursion yes;\n  response-policy {\n    ####FQDN only zones ${zone_opt['fqdn']}\n    ####Mixed zones ${zone_opt['mixed']}\n    ####IP only zones ${zone_opt['ip']}\n  } qname-wait-recurse no break-dnssec yes;\n};\n`;
          for (var i in keys) {
            keys_txt += `\nkey "${keys[i]['name']}"{\n  algorithm hmac-${keys[i]['alg']}; secret "${keys[i]['tkey']}";\n};\n`;
          }
          break;
        case 'PowerDNS':
          let RPZ_PowerDNS_Options = { 'nxdomain': 'defpol=Policy.NXDOMAIN', 'nodata': 'defpol=Policy.NODATA', 'passthru': 'defpol=Policy.NoAction', 'drop': 'defpol=Policy.Drop', 'tcp-only': 'defpol=Policy.Truncate', 'local': '' };
          let pdns_opt = ""; let cmm = "";
          rpzs.data.forEach(function(el) {
            if (el['tkeys'].length == 0) { tkey_str = ""; }
            else { tkey_str = `tsigname="${el['tkeys'][0]['name']}", tsigalgo="hmac-${el['tkeys'][0]['alg']}", tsigsecret="${el['tkeys'][0]['tkey']}"`; }
            if (RPZ_PowerDNS_Options[el['action']] != "" && tkey_str != "") cmm = ",";
            if (RPZ_PowerDNS_Options[el['action']] != "" || tkey_str != "") pdns_opt = `, {${RPZ_PowerDNS_Options[el['action']]}${cmm} ${tkey_str}}`; else pdns_opt = "";
            zones += `\nrpzMaster("${el['servers'][0]['pub_ip']}", "${el['name']}"${pdns_opt})\n`;
          });
          break;
        case 'Infoblox':
          let zone_pri = []; zone_pri['fqdn'] = []; zone_pri['mixed'] = []; zone_pri['ip'] = [];
          let zp = 0;
          options = "header-responsepolicyzone,fqdn*,zone_format*,rpz_policy,substitute_name,view,zone_type,external_primaries,grid_secondaries,priority";
          let RPZ_IB_Options = { 'nxdomain': 'Nxdomain', 'nodata': 'Nodata', 'passthru': 'Passthru', 'drop': 'Nxdomain', 'tcp-only': 'Passthru', 'local': 'Given' };
          let TKEY_Alg = { 'md5': 'HMAC-MD5', 'sha256': 'HMAC-SHA256', 'sha512': 'HMAC-SHA512' };
          let IBMember = this.$root.rpzExportIBMember;
          let IBNView = this.$root.rpzExportIBView;
          rpzs.data.forEach(function(el) {
            let tkey = -1;
            el['tkeys'].some(function(el) {
              tkey++;
              return ((el['alg'] != 'sha512') && (el['tkey'].indexOf('/') == -1));
            });
            if (el['tkeys'].length == 0) {
              tkey_str = `${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/FALSE`;
            } else {
              tkey_str = `${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/TRUE/${el['tkeys'][tkey]['name']}/${el['tkeys'][tkey]['tkey']}/${TKEY_Alg[el['tkeys'][tkey]['alg']]}`;
            }
            zone_pri[el['ioc_type']].push(`\n  responsepolicyzone,${el['name']},FORWARD,${RPZ_IB_Options[el['action']]},,${IBNView},responsepolicy,${tkey_str},${IBMember}/False/False/False,`);
          });
          zone_pri['fqdn'].forEach(function(el) { zones += el + zp; zp++; });
          zone_pri['mixed'].forEach(function(el) { zones += el + zp; zp++; });
          zone_pri['ip'].forEach(function(el) { zones += el + zp; zp++; });
          break;
      }
      downloadAsPlainText(this.$root.ftExFormat + "_sample_config.txt", options + keys_txt + zones);
    }
  }
};

// Note: No global Vue instance creation here.
// The Vue instance is created in main.js using: new Vue(appConfig)
