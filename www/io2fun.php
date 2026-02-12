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
 * @license MIT
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
    if (!empty($data['tSrvURL']) && !validateUrl($data['tSrvURL'])) {
        return ['valid' => false, 'error' => 'Invalid URL'];
    }
    // Validate management IPs array
    $mgmtIps = json_decode($data['tSrvMGMTIP'] ?? '[]', true);
    if (is_array($mgmtIps)) {
        foreach ($mgmtIps as $ip) {
            if (!validateIpOrCidr($ip)) {
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
    if (!validateName($data['tSrcName'] ?? '')) {
        return ['valid' => false, 'error' => 'Invalid source name'];
    }
    if (!empty($data['tSrcURL']) && !validateUrl($data['tSrcURL'])) {
        return ['valid' => false, 'error' => 'Invalid source URL'];
    }
    if (!empty($data['tSrcURLIXFR']) && !validateUrl($data['tSrcURLIXFR'])) {
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
