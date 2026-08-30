<?php
/**
 * ioc2rpz.gui - Core Functions Library
 * 
 * This file contains:
 * - HTTP request handling and parsing
 * - Security functions (CSRF, headers, password validation)
 * - UUID generation
 * - RpiDNS installation script generation
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2018-2026
 * @license Apache-2.0
 */

/**
 * Parses and returns the current HTTP request data
 * 
 * Handles both form-encoded and JSON request bodies.
 * Extracts request method and API endpoint from PATH_INFO.
 * 
 * @return array Associative array containing:
 *   - All request parameters (from $_REQUEST or JSON body)
 *   - 'method': HTTP method (GET, POST, PUT, DELETE, PATCH)
 *   - 'req': API endpoint name from URL path
 */
function getRequest(){
  #do it simple for now
  #support only 1 level request
  $rawRequest = file_get_contents('php://input');
  if (empty($rawRequest)){
    $Data=$_REQUEST;
  }else{
    $Data=json_decode($rawRequest,true);
    if (!is_array($Data)) $Data=[];
    // Merge query-string parameters (e.g. ?SrvId=1) so they are available
    // alongside the JSON body. Body values take precedence on conflict.
    $Data=array_merge($_GET, $Data);
  };
  $Data['method'] = $_SERVER['REQUEST_METHOD'];
  $Data['req'] = explode("/", substr(@$_SERVER['PATH_INFO'], 1))[0];
  /*
   * TODO escape values for SQL safety
   */
  //if ($Data['method'] == 'PUT') print_r($Data);
  return $Data;
};

/**
 * Determines the current request protocol (HTTP or HTTPS)
 * 
 * Checks HTTPS server variable and port 443 to detect secure connections.
 * 
 * @return string "https://" for secure connections, "http://" otherwise
 */
function getProto(){
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
};

/**
 * Starts the PHP session with hardened cookie attributes.
 *
 * The cookie flags are applied here rather than relying on php.ini because the
 * only place that edits php.ini is the container entrypoint
 * (scripts/run_ioc2rpz.gui.sh), and it does so once, guarded by a marker file.
 * Bare-metal installations and already-provisioned containers therefore never
 * received them. Setting them in code makes every deployment consistent.
 *
 * SameSite=Strict prevents the browser from attaching the session cookie to any
 * cross-site request, which is the backstop behind the CSRF token check in
 * io2data.php: even a leaked or omitted token cannot be replayed from a foreign
 * origin. Strict rather than Lax because the GUI has no inbound deep links that
 * need to carry an authenticated session.
 *
 * `secure` follows the detected protocol instead of being pinned on, so a
 * deployment terminating TLS elsewhere and serving plain HTTP internally can
 * still log in. Calling this on an already-active session is a no-op.
 *
 * @return void
 */
function startSession(){
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => (getProto() === "https://"),
    'samesite' => 'Strict',
  ]);
  session_start();
};

/**
 * Diffs stored association rows against the requested target set.
 *
 * Association tables (servers_tsig, rpzs_servers, rpzs_sources, ...) hold one row
 * per link, and the editors submit the complete desired set on every save. This
 * resolves that into the minimum set of statements: association rows whose target
 * is no longer wanted are deleted, targets that are not linked yet are inserted,
 * and links that already match are left untouched.
 *
 * Both inputs are result sets from DB_selectArray, i.e. arrays of associative
 * rows. `$old` rows carry the association's own `rowid` plus the foreign key named
 * by `$fkColumn`; `$new` rows carry the target `rowid`. Everything is cast to int
 * before comparison so the returned values are always safe to interpolate into
 * SQL, and so a string rowid from SQLite compares equal to an int from the
 * request.
 *
 * A duplicate link to the same target is reported for deletion, keeping only the
 * first occurrence, so pre-existing duplicates get cleaned up rather than
 * preserved.
 *
 * @param array  $old      Existing association rows (need `rowid` and $fkColumn)
 * @param string $fkColumn Name of the column holding the target row id
 * @param array  $new      Requested target rows (need `rowid`)
 * @return array{delete:int[],insert:int[],keep:int[]} `delete` holds association
 *         rowids, `insert` and `keep` hold target rowids
 */
function diffAssociations(array $old, string $fkColumn, array $new){
  $wanted = array_values(array_unique(array_map('intval', array_column($new, 'rowid'))));
  $keep   = [];
  $delete = [];
  foreach ($old as $row) {
    $target = intval($row[$fkColumn] ?? 0);
    // in_array with strict comparison on an int list: unlike array_search the
    // result cannot be confused with a valid index of 0.
    if (in_array($target, $wanted, true) && !in_array($target, $keep, true)) {
      $keep[] = $target;
    } else {
      $delete[] = intval($row['rowid']);
    }
  };
  return [
    'delete' => $delete,
    'insert' => array_values(array_diff($wanted, $keep)),
    'keep'   => $keep,
  ];
};

/**
 * Diffs stored association rows that hold a literal value rather than a foreign key.
 *
 * Used for the notify and management IP tables, where the association row stores
 * the address itself. Behaves like diffAssociations, but compares strings and
 * returns the values to insert instead of row ids. The caller still has to escape
 * the returned values before use.
 *
 * @param array    $old         Existing rows (need `rowid` and $valueColumn)
 * @param string   $valueColumn Name of the column holding the value
 * @param iterable $wanted      Requested values
 * @return array{delete:int[],insert:string[]} `delete` holds association rowids
 */
function diffValueAssociations(array $old, string $valueColumn, $wanted){
  $wantedList = [];
  foreach (($wanted ?: []) as $value) {
    if (is_array($value) || is_object($value)) continue;
    $value = (string)$value;
    if ($value !== '' && !in_array($value, $wantedList, true)) $wantedList[] = $value;
  };
  $keep   = [];
  $delete = [];
  foreach ($old as $row) {
    $value = (string)($row[$valueColumn] ?? '');
    if (in_array($value, $wantedList, true) && !in_array($value, $keep, true)) {
      $keep[] = $value;
    } else {
      $delete[] = intval($row['rowid']);
    }
  };
  return [
    'delete' => $delete,
    'insert' => array_values(array_diff($wantedList, $keep)),
  ];
};

/**
 * Sets security-related HTTP headers
 * 
 * Configures the following security headers:
 * - X-Content-Type-Options: Prevents MIME type sniffing
 * - X-XSS-Protection: Legacy XSS protection for older browsers
 * - Content-Security-Policy: Comprehensive CSP with:
 *   - Same-origin restrictions for most resources
 *   - Inline scripts/styles allowed for Vue.js compatibility
 *   - FontAwesome CDN whitelisted for fonts and styles
 *   - Clickjacking protection via frame-ancestors
 *   - Plugin blocking via object-src 'none'
 * 
 * @return void
 */
function secHeaders(){
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // XSS Protection (legacy browsers)
    // Note: Modern browsers have deprecated this in favor of CSP, but it provides defense-in-depth
    header("X-XSS-Protection: 1; mode=block");
    
    // Comprehensive Content Security Policy
    // - default-src 'self': Only allow resources from same origin by default
    // - script-src 'self' 'unsafe-inline' 'unsafe-eval': Allow scripts from same origin, inline scripts (needed for Vue.js), and eval (needed for Vue.js templates)
    // - style-src 'self' 'unsafe-inline' https://use.fontawesome.com: Allow styles from same origin, inline styles (needed for Vue.js), and FontAwesome CDN
    // - font-src 'self' https://use.fontawesome.com data:: Allow fonts from same origin, FontAwesome CDN, and data URIs
    // - img-src 'self' data:: Allow images from same origin and data URIs
    // - connect-src 'self': Allow AJAX/fetch to same origin only
    // - frame-ancestors 'self': Prevent clickjacking by only allowing framing from same origin
    // - form-action 'self': Only allow form submissions to same origin
    // - base-uri 'self': Restrict base element to same origin
    // - object-src 'none': Disallow plugins like Flash
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline' https://use.fontawesome.com; font-src 'self' https://use.fontawesome.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none';");

    // HSTS. Previously only set by the container's Apache config, which left
    // bare-metal installations (RpiDNS) without it. Emitted from PHP so every
    // deployment gets it regardless of the web server in front.
    // Only meaningful over TLS - browsers ignore it on plain HTTP, and sending it
    // there would be a no-op that hides a misconfigured deployment.
    if (getProto() === "https://") {
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains");
    }
};

/**
 * Generates a cryptographically secure CSRF token
 * @return string A 64-character hexadecimal token
 */
function generateCsrfToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Validates a CSRF token against the session token
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Gets the current CSRF token from session, or generates a new one if not exists
 * @return string The CSRF token
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates password strength
 * Password must be either:
 * - 8+ chars with at least one uppercase, lowercase, number, and special character
 * - OR 16+ characters (passphrase)
 * @param string $password The password to validate
 * @return bool True if valid, false otherwise
 */
function validatePassword($password) {
    if (strlen($password) > 15) {
        return true; // Long passphrase is acceptable
    }
    if (strlen($password) < 8) {
        return false;
    }
    // Check for required character types
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!,%,&,@,#,$,\^,\*,\?,_,~,\,,\.]/', $password);
    
    return $hasUpper && $hasLower && $hasNumber && $hasSpecial;
}

// API rate limiting constants
define('API_RATE_LIMIT', 200);          // Max requests per window
define('API_RATE_WINDOW', 60);         // Window size in seconds (1 minute)
define('API_WRITE_RATE_LIMIT', 60);    // Max write requests (POST/PUT/DELETE) per window

/**
 * Session-based API rate limiter
 * 
 * Tracks request counts per time window in the session.
 * Separate limits for read (GET) and write (POST/PUT/DELETE/PATCH) operations.
 * 
 * @param string $method HTTP method of the current request
 * @return array ['allowed' => bool, 'retry_after' => int seconds until window resets]
 */
function checkApiRateLimit($method) {
    $now = time();

    // Initialize rate limit tracking in session
    if (!isset($_SESSION['rate_limit_start']) || ($now - $_SESSION['rate_limit_start']) >= API_RATE_WINDOW) {
        $_SESSION['rate_limit_start'] = $now;
        $_SESSION['rate_limit_count'] = 0;
        $_SESSION['rate_limit_write_count'] = 0;
    }

    $_SESSION['rate_limit_count']++;

    $isWrite = in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH']);
    if ($isWrite) {
        $_SESSION['rate_limit_write_count']++;
    }

    $retryAfter = API_RATE_WINDOW - ($now - $_SESSION['rate_limit_start']);

    // Check overall rate limit
    if ($_SESSION['rate_limit_count'] > API_RATE_LIMIT) {
        return ['allowed' => false, 'retry_after' => $retryAfter];
    }

    // Check write-specific rate limit
    if ($isWrite && $_SESSION['rate_limit_write_count'] > API_WRITE_RATE_LIMIT) {
        return ['allowed' => false, 'retry_after' => $retryAfter];
    }

    return ['allowed' => true, 'retry_after' => 0];
}

/**
 * Validates the rpz JSON structure for RpiDNS configuration
 * @param mixed $rpz The rpz data to validate (should be an array of objects with 'feed' and 'action')
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateRpzJson($rpz) {
    // rpz must be an array
    if (!is_array($rpz)) {
        return ['valid' => false, 'error' => 'rpz must be an array'];
    }
    
    // Allowed action values
    $allowedActions = ['cname', 'nxdomain', 'nodata', 'drop', 'passthru', 'passthrunolog', 'disabled', 'passthru log no'];
    
    foreach ($rpz as $index => $item) {
        // Each item must be an array/object
        if (!is_array($item)) {
            return ['valid' => false, 'error' => "rpz item at index $index must be an object"];
        }
        
        // Each item must have 'feed' property
        if (!isset($item['feed']) || !is_string($item['feed']) || empty(trim($item['feed']))) {
            return ['valid' => false, 'error' => "rpz item at index $index must have a non-empty 'feed' string"];
        }
        
        // Each item must have 'action' property
        if (!isset($item['action']) || !is_string($item['action'])) {
            return ['valid' => false, 'error' => "rpz item at index $index must have an 'action' string"];
        }
        
        // Validate action value
        if (!in_array($item['action'], $allowedActions)) {
            return ['valid' => false, 'error' => "rpz item at index $index has invalid action: '{$item['action']}'"];
        }
    }
    
    return ['valid' => true, 'error' => null];
};

/**
 * Validates a hostname for safe use in generated scripts and BIND configs.
 * Allows letters, digits, hyphens, dots (RFC 952/1123). Max 253 chars.
 * Rejects shell metacharacters and BIND directive injection.
 *
 * @param string $hostname The hostname to validate
 * @return bool True if valid
 */
function validateHostname($hostname) {
    if (empty($hostname) || strlen($hostname) > 253) return false;
    return (bool)preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]{0,251}[a-zA-Z0-9])?$/', $hostname);
}

/**
 * Validates an IP address or CIDR notation for safe use in generated scripts.
 * Accepts IPv4, IPv6, and CIDR (e.g. 192.168.1.0/24).
 * Empty string is allowed (optional field).
 *
 * @param string $ip The IP or CIDR to validate
 * @return bool True if valid or empty
 */
function validateIpOrCidr($ip) {
    if ($ip === '' || $ip === null) return true;
    // Strip CIDR suffix for validation
    $parts = explode('/', $ip, 2);
    if (!filter_var($parts[0], FILTER_VALIDATE_IP)) return false;
    if (isset($parts[1])) {
        $prefix = intval($parts[1]);
        $max = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 128 : 32;
        if ($prefix < 0 || $prefix > $max || (string)$prefix !== $parts[1]) return false;
    }
    return true;
}

/**
 * Validates a management IP entry (the `tSrvMGMTIP` list).
 *
 * These are operator-entered entries for the server's management ACL. On the
 * ioc2rpz server the ACL check is a plain exact string comparison
 * (`lists:member(ip_to_str(PeerIP), ACL)`, where `ip_to_str` is
 * `inet_parse:ntoa`), so an ACL entry is effectively an opaque string that is
 * matched verbatim -- not something the server re-parses as an IP. The GUI
 * therefore should not impose stricter canonicalisation than the operator's
 * intent; historically this field accepted arbitrary values, and PHP's
 * FILTER_VALIDATE_IP is stricter than the GUI's own frontend check (which
 * accepts non-canonical forms such as ":::172.18.0.1"). Enforcing
 * FILTER_VALIDATE_IP here made otherwise-editable server records un-saveable.
 *
 * The one hard requirement is injection safety: these values are written into
 * the generated ioc2rpz.conf as quoted Erlang strings inside the server ACL,
 * so an entry must not be able to break out of that quoted string or inject
 * shell/config syntax. Restricting the character set to IP/CIDR characters
 * (hex digits, dot, colon and an optional numeric /prefix) guarantees there is
 * no way to include a quote, backslash, whitespace or shell metacharacter,
 * while still accepting every value the server's own ip_to_str can emit.
 *
 * Note: a non-canonical entry like ":::172.18.0.1" is accepted but will never
 * match a real peer (ntoa never emits it); that is a data-quality choice left
 * to the operator, not something the GUI blocks.
 *
 * Empty string is allowed (optional field).
 *
 * @param string $ip The management IP entry to validate
 * @return bool True if valid or empty
 */
function validateMgmtIp($ip) {
    if ($ip === '' || $ip === null) return true;
    // Canonical IP / CIDR is always accepted.
    if (validateIpOrCidr($ip)) return true;
    // Otherwise accept only IP-shaped, injection-safe tokens. Length-bound to a
    // sane IP size to avoid unbounded values.
    if (strlen($ip) > 45) return false;
    return (bool)preg_match('#^[0-9A-Fa-f:.]+(/\d{1,3})?$#', $ip);
}

/**
 * Validates a syslog host (hostname or IP, optionally with port).
 * Empty string is allowed (optional field).
 *
 * @param string $host The logging host to validate
 * @return bool True if valid or empty
 */
function validateLoggingHost($host) {
    if ($host === '' || $host === null) return true;
    // Allow host:port format
    $parts = explode(':', $host, 2);
    $hostPart = $parts[0];
    if (isset($parts[1]) && (!is_numeric($parts[1]) || intval($parts[1]) < 1 || intval($parts[1]) > 65535)) return false;
    return filter_var($hostPart, FILTER_VALIDATE_IP) || validateHostname($hostPart);
}

/**
 * Validates an FQDN for use as a redirect CNAME target.
 * Empty string is allowed (optional field).
 *
 * @param string $fqdn The FQDN to validate
 * @return bool True if valid or empty
 */
function validateFqdn($fqdn) {
    if ($fqdn === '' || $fqdn === null) return true;
    // FQDN: letters, digits, hyphens, dots. Max 253 chars.
    if (strlen($fqdn) > 253) return false;
    return (bool)preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-\.]{0,251}[a-zA-Z0-9\.])$/', $fqdn);
}

/**
 * Validates all RpiDNS configuration fields for safe use in generated scripts.
 * Returns validation result with error message if invalid.
 *
 * @param array $data Request data containing rpidns fields
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateRpidnsConfig($data) {
    // Validate hostname (name) — required, used in bash scripts and BIND configs
    if (!validateHostname($data['name'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid hostname: only letters, digits, hyphens, and dots allowed (max 253 chars)'];
    }
    // Validate dns_type — must be one of the allowed values
    $allowedDnsTypes = ['primary', 'secondary'];
    if (!empty($data['dns_type']) && !in_array($data['dns_type'], $allowedDnsTypes)) {
        return ['valid' => false, 'error' => 'Invalid dns_type: must be "primary" or "secondary"'];
    }
    // Validate dns_ipnet — IP or CIDR, embedded in BIND ACLs
    if (!validateIpOrCidr($data['dns_ipnet'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid dns_ipnet: must be a valid IP address or CIDR notation'];
    }
    // Validate logging — must be one of the allowed values
    $allowedLogging = ['local', 'forward'];
    if (!empty($data['logging']) && !in_array($data['logging'], $allowedLogging)) {
        return ['valid' => false, 'error' => 'Invalid logging: must be "local" or "forward"'];
    }
    // Validate logging_host — hostname or IP, embedded in rsyslog config
    if (!validateLoggingHost($data['logging_host'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid logging_host: must be a valid hostname or IP address'];
    }
    // Validate redirect — must be one of the allowed values
    $allowedRedirect = ['default', 'custom', ''];
    if (!empty($data['redirect']) && !in_array($data['redirect'], $allowedRedirect)) {
        return ['valid' => false, 'error' => 'Invalid redirect: must be "default" or "custom"'];
    }
    // Validate redirect_cname — FQDN, embedded in BIND response-policy
    if (!validateFqdn($data['redirect_cname'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid redirect_cname: must be a valid FQDN'];
    }
    // Validate dns — must be one of the allowed values
    $allowedDns = ['bind', 'unbound', ''];
    if (!empty($data['dns']) && !in_array(strtolower($data['dns']), $allowedDns)) {
        return ['valid' => false, 'error' => 'Invalid dns: must be "bind" or "unbound"'];
    }
    // Validate model — alphanumeric with hyphens only
    if (!empty($data['model']) && !preg_match('/^[a-zA-Z0-9\-_]{1,50}$/', $data['model'])) {
        return ['valid' => false, 'error' => 'Invalid model: only letters, digits, hyphens, and underscores allowed (max 50 chars)'];
    }
    return ['valid' => true, 'error' => null];
}

// ---- API field validation constants ----
define('MAX_NAME_LENGTH', 253);
define('MAX_URL_LENGTH', 2048);
define('MAX_REGEX_LENGTH', 1024);
define('MAX_KEY_LENGTH', 1024);
define('MAX_EMAIL_LENGTH', 254);
define('MAX_CERT_PATH_LENGTH', 512);
define('MAX_CUSTOM_CONFIG_LENGTH', 10000);
define('MAX_GROUP_NAME_LENGTH', 128);

/**
 * Validates a generic name field (server name, source name, etc.)
 * Allows letters, digits, hyphens, underscores, dots, spaces.
 *
 * @param string $name The name to validate
 * @param int $maxLen Maximum length
 * @return bool True if valid
 */
function validateName($name, $maxLen = MAX_NAME_LENGTH) {
    if (empty($name) || strlen($name) > $maxLen) return false;
    return (bool)preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-_\. ]{0,'.($maxLen-1).'}$/', $name);
}

/**
 * Validates an IP address (IPv4 or IPv6, no CIDR).
 *
 * @param string $ip The IP to validate
 * @return bool True if valid
 */
function validateIp($ip) {
    return (bool)filter_var($ip, FILTER_VALIDATE_IP);
}

/**
 * Validates an email address.
 *
 * @param string $email The email to validate
 * @return bool True if valid
 */
function validateEmail($email) {
    if (strlen($email) > MAX_EMAIL_LENGTH) return false;
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validates a URL (http/https only).
 * Empty string is allowed (optional field).
 *
 * @param string $url The URL to validate
 * @return bool True if valid or empty
 */
function validateUrl($url) {
    if ($url === '' || $url === null) return true;
    if (strlen($url) > MAX_URL_LENGTH) return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array($scheme, ['http', 'https']);
}

/**
 * Validates the server configuration file name / location (the `tSrvURL`
 * field). This is NOT a web URL: for local storage it is a plain file name
 * such as "ioc2rpz.conf", and for scp/S3 source types it is a path/location
 * (e.g. "user@host:/path/ioc2rpz.conf" or "s3://bucket/key?versionId=...").
 *
 * The character set mirrors the frontend "File Name" field (formatURLAT:
 * letters, digits and @ / = : ? # . - _ &), which is what allows scp and S3
 * locations to be entered. Path traversal ("..") is rejected as a
 * defense-in-depth measure since this value can reference a file on disk.
 * Empty string is allowed (the caller only invokes this when non-empty).
 *
 * @param string $url The configuration file name / location
 * @return bool True if valid or empty
 */
function validateServerConfigFile($url) {
    if ($url === '' || $url === null) return true;
    if (strlen($url) > MAX_URL_LENGTH) return false;
    // Reject path traversal.
    if (strpos($url, '..') !== false) return false;
    // Allow only the same safe characters the frontend permits (letters,
    // digits and @ / = : ? # . - _ &), covering local file names as well as
    // scp and S3 locations. Note: no shell metacharacters (space, ; | ` $ etc.)
    // are permitted.
    return (bool)preg_match('#^[A-Za-z0-9@/=:?\#.&_-]+$#', $url);
}

/**
 * Validates a source URL.
 * ioc2rpz sources support http/https/ftp URLs as well as local files
 * ("file:" prefix) and shell commands ("shell:" prefix).
 * Empty string is allowed (optional field).
 *
 * @param string $url The URL to validate
 * @return bool True if valid or empty
 */
function validateSourceUrl($url) {
    if ($url === '' || $url === null) return true;
    if (strlen($url) > MAX_URL_LENGTH) return false;
    return (bool)preg_match('#^(https?://|ftp://|file:|shell:)#', $url);
}

/**
 * Validates an IXFR (incremental transfer) URL.
 * In addition to regular source URLs, IXFR paths support meta keywords such
 * as "[:AXFR:]", "[:FTimestamp:]" and "[:ToTimestamp:]" that ioc2rpz expands
 * at runtime. Empty string is allowed (optional field).
 *
 * @param string $url The URL to validate
 * @return bool True if valid or empty
 */
function validateIxfrUrl($url) {
    if ($url === '' || $url === null) return true;
    if (strlen($url) > MAX_URL_LENGTH) return false;
    // Regular source URL (http/https/ftp/file/shell) is accepted as-is.
    if (validateSourceUrl($url)) return true;
    // Meta URL starting with [:AXFR:] optionally followed by query/anchor and
    // [:FTimestamp:]/[:ToTimestamp:] keywords (mirrors the frontend regex).
    return (bool)preg_match(
        '/^\[:AXFR:\]((\?|\&)[;&a-zA-Z0-9%_.~+=-]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?(\#[-a-zA-Z0-9_]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?$/',
        $url
    );
}

/**
 * Validates a TSIG key algorithm against allowed values.
 *
 * @param string $alg The algorithm name
 * @return bool True if valid
 */
function validateTsigAlgorithm($alg) {
    $allowed = [
        'hmac-md5', 'hmac-sha1', 'hmac-sha224', 'hmac-sha256', 'hmac-sha384', 'hmac-sha512',
        'HMAC-MD5', 'HMAC-SHA1', 'HMAC-SHA224', 'HMAC-SHA256', 'HMAC-SHA384', 'HMAC-SHA512'
    ];
    return in_array($alg, $allowed);
}

/**
 * Validates a base64-encoded TSIG key value.
 *
 * @param string $key The key to validate
 * @return bool True if valid
 */
function validateTsigKey($key) {
    if (empty($key) || strlen($key) > MAX_KEY_LENGTH) return false;
    return (bool)preg_match('/^[A-Za-z0-9+\/=]+$/', $key);
}

/**
 * Validates a file path for cert/key files.
 * Rejects path traversal and shell metacharacters.
 * Empty string is allowed (optional field).
 *
 * @param string $path The file path to validate
 * @return bool True if valid or empty
 */
function validateFilePath($path) {
    if ($path === '' || $path === null) return true;
    if (strlen($path) > MAX_CERT_PATH_LENGTH) return false;
    // Reject path traversal
    if (strpos($path, '..') !== false) return false;
    // Allow only safe path characters
    return (bool)preg_match('/^[a-zA-Z0-9\-_\.\/]+$/', $path);
}

/**
 * Validates a string length limit.
 * Empty string is allowed.
 *
 * @param string $str The string to check
 * @param int $maxLen Maximum length
 * @return bool True if within limit
 */
function validateStringLength($str, $maxLen) {
    if ($str === '' || $str === null) return true;
    return strlen($str) <= $maxLen;
}

/**
 * Validates the length of an IOC lookup indicator against the closed interval
 * [1, 2048] characters (Req 8.1, 8.7).
 *
 * This is the isolated, pure length-validation contract used by the
 * `GET ioc_lookup` Mgmt_Proxy endpoint: the request may proceed to contact the
 * management interface if and only if this returns true. An empty (or null)
 * indicator, or one longer than 2048 characters, is rejected and MUST NOT
 * result in any management-interface contact.
 *
 * @param string $ioc The submitted indicator string
 * @return bool True iff 1 <= strlen($ioc) <= 2048
 */
function validateIocLength($ioc) {
    if ($ioc === null) return false;
    $len = strlen((string)$ioc);
    return $len >= 1 && $len <= 2048;
}

/**
 * Validates server fields for POST/PUT operations.
 *
 * @param array $data Request data
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateServerFields($data) {
    if (!validateName($data['tSrvName'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid server name'];
    }
    if (!empty($data['tSrvIP']) && !validateIp($data['tSrvIP'])) {
        return ['valid' => false, 'error' => 'Invalid server IP address'];
    }
    if (!empty($data['tSrvPubIP']) && !validateIp($data['tSrvPubIP'])) {
        return ['valid' => false, 'error' => 'Invalid public IP address'];
    }
    if (!empty($data['tSrvNS']) && !validateFqdn($data['tSrvNS'])) {
        return ['valid' => false, 'error' => 'Invalid NS hostname'];
    }
    if (!empty($data['tSrvEmail']) && !validateEmail($data['tSrvEmail'])) {
        return ['valid' => false, 'error' => 'Invalid email address'];
    }
    // tSrvURL is the server's configuration file name / location (e.g.
    // "ioc2rpz.conf"), NOT an http(s) URL. Validate it as a file name/path
    // (mirrors the frontend "File Name" field, validateNameAT), not with
    // validateUrl which requires an http/https scheme.
    if (!empty($data['tSrvURL']) && !validateServerConfigFile($data['tSrvURL'])) {
        return ['valid' => false, 'error' => 'Invalid configuration file name'];
    }
    // Validate management IPs array
    $mgmtIps = json_decode($data['tSrvMGMTIP'] ?? '[]', true);
    if (is_array($mgmtIps)) {
        foreach ($mgmtIps as $ip) {
            if (!validateMgmtIp($ip)) {
                return ['valid' => false, 'error' => 'Invalid management IP: ' . substr($ip, 0, 45)];
            }
        }
    }
    // Validate cert/key file paths
    if (!validateFilePath($data['tCertFile'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid certificate file path'];
    }
    if (!validateFilePath($data['tKeyFile'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid key file path'];
    }
    if (!validateFilePath($data['tCACertFile'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid CA certificate file path'];
    }
    if (!validateStringLength($data['tCustomConfig'] ?? '', MAX_CUSTOM_CONFIG_LENGTH)) {
        return ['valid' => false, 'error' => 'Custom config exceeds maximum length'];
    }
    // Validate source attribution default: absent/empty is valid (treated as 'off'),
    // otherwise must be an exact, case-sensitive member of {off, auto, on}.
    $trackDefault = $data['tSrvTrackDefault'] ?? '';
    if ($trackDefault !== '' && !in_array($trackDefault, ['off', 'auto', 'on'], true)) {
        return ['valid' => false, 'error' => 'Invalid source attribution default: ' . $trackDefault];
    }
    // Validate the server-level DNS rate limits. Absent/empty means inherit the
    // ioc2rpz compile-time default and is always valid; anything present must be an
    // integer in range. All three options are valid at the server level.
    $rl = validateRateLimitFields($data, [
        'tSrvRLWindow'             => ['label' => 'rate limit window',              'min' => 1, 'max' => RL_WINDOW_MAX],
        'tSrvRLMaxRequests'        => ['label' => 'rate limit max requests',        'min' => 0],
        'tSrvRLMaxUnknownRequests' => ['label' => 'rate limit max unknown requests', 'min' => 0],
    ]);
    if (!$rl['valid']) {
        return $rl;
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validates a set of optional DNS rate limit inputs.
 *
 * Every option is individually optional: an absent or empty value means "inherit"
 * (resolve through zone -> server -> compile-time default) and is always accepted. A
 * value that is present must be a plain integer within the option's range: `window`
 * must be > 0 and at most RL_WINDOW_MAX, and the maximums must be >= 0, where 0 is
 * legal and means "refuse every request in that bucket".
 *
 * Only `window` is capped. It is stored with every entry in the server's rate limit
 * table and decides when that entry is swept, so an absurd window (a fumbled digit, a
 * value pasted in milliseconds) would keep entries alive for as long as it lasts. A
 * large maximum only makes the limiter permissive and retains nothing.
 *
 * Rejecting here matters because the server only logs and ignores an invalid value
 * and then falls back to the next level, so a bad value written by the GUI would
 * silently do nothing.
 *
 * @param array $data Request data
 * @param array $fields Map of request key => ['label' => string, 'min' => int,
 *                      'max' => int (optional, unbounded when omitted)]
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateRateLimitFields($data, $fields) {
    foreach ($fields as $key => $spec) {
        $value = $data[$key] ?? '';
        if (is_array($value)) {
            return ['valid' => false, 'error' => 'Invalid ' . $spec['label'] . ': not a number'];
        }
        $value = trim((string) $value);
        if ($value === '') {
            continue; // absent/empty => inherit
        }
        if (!preg_match('/^-?[0-9]+$/', $value)) {
            return [
                'valid' => false,
                'error' => 'Invalid ' . $spec['label'] . ': ' . substr($value, 0, 45) . ' is not an integer',
            ];
        }
        $int = intval($value);
        if ($int < $spec['min']) {
            return [
                'valid' => false,
                'error' => 'Invalid ' . $spec['label'] . ': ' . $int . ' is below the minimum of ' . $spec['min'],
            ];
        }
        if (isset($spec['max']) && $int > $spec['max']) {
            return [
                'valid' => false,
                'error' => 'Invalid ' . $spec['label'] . ': ' . $int . ' is above the maximum of ' . $spec['max'],
            ];
        }
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validates TSIG key fields for POST/PUT operations.
 *
 * @param array $data Request data
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateTkeyFields($data) {
    if (!validateName($data['tKeyName'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid TSIG key name'];
    }
    if (!validateTsigAlgorithm($data['tKeyAlg'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid TSIG algorithm'];
    }
    if (!validateTsigKey($data['tKey'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid TSIG key value (must be base64)'];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validates source/whitelist fields for POST/PUT operations.
 *
 * @param array $data Request data
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateSourceFields($data) {
    // DEBUG: log raw incoming source/IXFR values and validation outcomes.
    // Uncomment to troubleshoot "Invalid IXFR URL" issues.
    // error_log('[io2 DEBUG] validateSourceFields'
    //     . ' name=' . var_export($data['tSrcName'] ?? null, true)
    //     . ' url=' . var_export($data['tSrcURL'] ?? null, true)
    //     . ' ixfr=' . var_export($data['tSrcURLIXFR'] ?? null, true)
    //     . ' ixfr_len=' . (isset($data['tSrcURLIXFR']) ? strlen($data['tSrcURLIXFR']) : -1)
    //     . ' ixfr_hex=' . (isset($data['tSrcURLIXFR']) ? bin2hex($data['tSrcURLIXFR']) : '')
    //     . ' srcUrlValid=' . var_export(validateSourceUrl($data['tSrcURL'] ?? ''), true)
    //     . ' ixfrValid=' . var_export(validateIxfrUrl($data['tSrcURLIXFR'] ?? ''), true));

    if (!validateName($data['tSrcName'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid source name'];
    }
    if (!empty($data['tSrcURL']) && !validateSourceUrl($data['tSrcURL'])) {
        return ['valid' => false, 'error' => 'Invalid source URL'];
    }
    if (!empty($data['tSrcURLIXFR']) && !validateIxfrUrl($data['tSrcURLIXFR'])) {
        return ['valid' => false, 'error' => 'Invalid IXFR URL'];
    }
    if (!validateStringLength($data['tSrcREGEX'] ?? '', MAX_REGEX_LENGTH)) {
        return ['valid' => false, 'error' => 'Regex pattern exceeds maximum length'];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validates RPZ fields for POST/PUT operations.
 *
 * @param array $data Request data
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateRpzFields($data) {
    if (!validateFqdn($data['tRPZName'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid RPZ zone name'];
    }
    // Validate notify IPs
    $notifyIps = json_decode($data['tRPZNotify'] ?? '[]', true);
    if (is_array($notifyIps)) {
        foreach ($notifyIps as $ip) {
            if (!validateIp($ip)) {
                return ['valid' => false, 'error' => 'Invalid notify IP: ' . substr($ip, 0, 45)];
            }
        }
    }
    // Validate IOC type
    $allowedIocTypes = ['fqdn', 'ip', 'mixed'];
    if (!empty($data['tRPZIOCType']) && !in_array($data['tRPZIOCType'], $allowedIocTypes)) {
        return ['valid' => false, 'error' => 'Invalid IOC type'];
    }
    // Validate per-feed source tracking: absent/empty is valid (treated as 'Inherit'),
    // otherwise must be an exact, case-sensitive member of {Inherit, auto, true, false}.
    $trackSources = $data['tRPZTrackSources'] ?? '';
    if ($trackSources !== '' && !in_array($trackSources, ['Inherit', 'auto', 'true', 'false'], true)) {
        return ['valid' => false, 'error' => 'Invalid track sources value: ' . $trackSources];
    }
    // Validate the per-feed DNS rate limits. Absent/empty means inherit the
    // server-level value (then the compile-time default).
    $rl = validateRateLimitFields($data, [
        'tRPZRLWindow'      => ['label' => 'rate limit window',       'min' => 1, 'max' => RL_WINDOW_MAX],
        'tRPZRLMaxRequests' => ['label' => 'rate limit max requests', 'min' => 0],
    ]);
    if (!$rl['valid']) {
        return $rl;
    }
    // max_unknown_requests counts requests that never resolved to a zone, so there is
    // no feed config for the server to read it from: it is server-level only, and the
    // server logs and ignores it on an rpz record. Reject it rather than persisting a
    // value that could never take effect.
    $unknown = $data['tRPZRLMaxUnknownRequests'] ?? '';
    if (!is_array($unknown) && trim((string) $unknown) !== '') {
        return [
            'valid' => false,
            'error' => 'max_unknown_requests is a server-level option and cannot be set on a feed',
        ];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Validates TSIG key group name.
 *
 * @param string $name Group name
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateGroupName($name) {
    if (!validateName($name, MAX_GROUP_NAME_LENGTH)) {
        return ['valid' => false, 'error' => 'Invalid group name'];
    }
    return ['valid' => true, 'error' => null];
}

/**
 * Builds the management REST API URL for a single-indicator lookup.
 *
 * Produces the shape required by the ioc2rpz management interface (Req 8.3):
 *
 *   https://<addr>:<port>/api/v1/ioc/<url-encoded ioc>?tkey=
 *
 * IMPORTANT — the `tkey` query parameter is intentionally left EMPTY.
 *
 * On the server, `tkey` is NOT an authentication token; authentication is
 * performed independently via HTTP basic auth (the management key name/secret
 * in CURLOPT_USERPWD). Instead, `tkey` scopes which feeds are returned: the
 * server's `get_tkey_zones/1` only includes a feed if the supplied `tkey` is
 * one of that zone's transfer/access keys (`akeys`), matches one of its key
 * groups, OR is empty. A *management* key is not a per-zone transfer key, so
 * passing the management key name here filters out every feed and the response
 * comes back with empty `feeds` arrays ("not found in any feed").
 *
 * An empty `tkey` resolves to the server's all-zones case, so an authenticated
 * operator sees every feed the indicator appears in — matching the behaviour of
 * the public ioc2rpz lookup site. The indicator is rawurlencode()'d so any
 * character is transmitted safely in the path. The TSIG key name/secret are
 * never part of the URL (they travel only in the HTTP basic auth header).
 *
 * @param string $addr    Management address (host or IP) of the target server.
 * @param int|string $port Management REST port (rest_mgmt_port).
 * @param string $ioc     The indicator being looked up.
 * @return string The fully-formed request URL.
 */
function buildIocLookupUrl($addr, $port, $ioc) {
    return "https://" . $addr . ":" . $port .
        "/api/v1/ioc/" . rawurlencode($ioc) .
        "?tkey=";
}

/**
 * Classifies the outcome of a management-interface IOC lookup into the JSON
 * response returned to the frontend (Req 8.6).
 *
 * The classification depends only on the transport error number, the HTTP
 * status code, and the response body. It intentionally takes NO credential
 * inputs (TSIG key name/secret), so no classification result can ever leak
 * them. Error payloads carry only the classification and, at most, an HTTP
 * status code.
 *
 * Classification rules (mirrors the Mgmt_Proxy contract):
 *   - CURLE_OPERATION_TIMEDOUT                         => {"status":"failed","error":"timeout"}
 *   - CURLE_COULDNT_CONNECT / CURLE_COULDNT_RESOLVE_HOST
 *     (and any other non-zero transport error)         => {"status":"failed","error":"connection"}
 *   - HTTP status >= 400                                => {"status":"failed","error":"server","code":<status>}
 *   - success but body is not valid JSON                => {"status":"failed","error":"server","code":<status>}
 *   - success with a JSON body                          => the parsed JSON, re-encoded (pass-through)
 *
 * @param int    $errno    curl error number (0 on transport success).
 * @param int    $httpcode HTTP status code from the response.
 * @param string $body     Raw response body.
 * @return string A JSON-encoded response string.
 */
function classifyIocLookupResult($errno, $httpcode, $body) {
    if ($errno === CURLE_OPERATION_TIMEDOUT) {
        return '{"status":"failed","error":"timeout"}';
    }
    if ($errno === CURLE_COULDNT_CONNECT || $errno === CURLE_COULDNT_RESOLVE_HOST) {
        return '{"status":"failed","error":"connection"}';
    }
    if ($errno !== 0) {
        // Any other transport-level failure is classified as a connection failure.
        return '{"status":"failed","error":"connection"}';
    }
    if ($httpcode >= 400) {
        return '{"status":"failed","error":"server","code":' . intval($httpcode) . '}';
    }
    // Success: pass through the parsed JSON body (list of feed objects).
    $parsed = json_decode($body, true);
    if ($parsed === null && trim((string)$body) !== 'null') {
        // Body was not valid JSON despite a success status.
        return '{"status":"failed","error":"server","code":' . intval($httpcode) . '}';
    }
    return json_encode($parsed);
}

/**
 * Generates a version 4 UUID (random)
 * 
 * Creates a cryptographically secure random UUID following RFC 4122.
 * Sets version bits (4) and variant bits (10xx) appropriately.
 * 
 * @return string UUID in format xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
 */
function uuid(){
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
};

/**
 * Generates a complete RpiDNS installation script
 * 
 * This is a wrapper function that delegates to deployment-type specific
 * script generators. Currently supports:
 * - rpidns: Bare-metal/VM deployment on Raspbian (default)
 * 
 * Additional deployment types (e.g., containers) can be added by:
 * 1. Creating a new io2install_<type>.php file with generate_install_script_<type>() function
 * 2. Adding a case to the switch statement below
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $uuid RpiDNS device UUID
 * @param string $deployment_type Type of deployment ('rpidns' for bare-metal, future: 'container')
 * @return string Complete installation script
 */
function generate_install_script($db, $uuid, $deployment_type = 'docker') {
    // Query the device name for the filename
    $safe_uuid = DB_escape($db, $uuid);
    $name_row = DB_selectArray($db, "SELECT name FROM rpidns WHERE rpidns_uuid='$safe_uuid' LIMIT 1");
    $device_name = !empty($name_row) ? $name_row[0]['name'] : 'rpidns';

    switch ($deployment_type) {
        case 'docker':
        case 'container':
            require_once(__DIR__ . '/io2install_docker.php');
            return ['script' => generate_install_script_docker($db, $uuid), 'name' => $device_name];
        case 'rpidns':
        default:
            require_once(__DIR__ . '/io2install_rpidns.php');
            return ['script' => generate_install_script_rpidns($db, $uuid), 'name' => $device_name];
    }
}

?>
