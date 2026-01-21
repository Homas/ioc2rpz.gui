<?php
/**
 * Vite Asset Helper for PHP
 * 
 * Provides functions to load Vite-built assets with proper hashed filenames.
 * Supports both development mode (Vite dev server) and production mode (built assets).
 * 
 * Requirements: 3.5 - Cache-busting via hashed filenames in production builds
 */

/**
 * Check if we're in development mode
 * Set VITE_DEV_MODE=true in environment or define before including this file
 * 
 * @return bool
 */
function vite_is_dev_mode() {
    // Check environment variable
    if (getenv('VITE_DEV_MODE') === 'true') {
        return true;
    }
    // Check PHP constant
    if (defined('VITE_DEV_MODE') && VITE_DEV_MODE === true) {
        return true;
    }
    return false;
}

/**
 * Get the Vite dev server URL
 * 
 * @return string
 */
function vite_dev_server_url() {
    $host = getenv('VITE_DEV_HOST') ?: 'localhost';
    $port = getenv('VITE_DEV_PORT') ?: '5173';
    return "http://{$host}:{$port}";
}

/**
 * Read and parse the Vite manifest file
 * 
 * @return array|null Returns manifest array or null if not found
 */
function vite_get_manifest() {
    static $manifest = null;
    
    if ($manifest !== null) {
        return $manifest;
    }
    
    $manifest_path = __DIR__ . '/dist/.vite/manifest.json';
    
    if (!file_exists($manifest_path)) {
        return null;
    }
    
    $content = file_get_contents($manifest_path);
    $manifest = json_decode($content, true);
    
    return $manifest;
}

/**
 * Get the hashed filename for an entry point
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return string|null Returns the hashed filename or null if not found
 */
function vite_get_entry_file($entry) {
    $manifest = vite_get_manifest();
    
    if ($manifest === null) {
        return null;
    }
    
    // Entry points are keyed by their source path
    $entry_key = "src/{$entry}.js";
    
    if (isset($manifest[$entry_key]) && isset($manifest[$entry_key]['file'])) {
        return $manifest[$entry_key]['file'];
    }
    
    return null;
}

/**
 * Get CSS files associated with an entry point (including from imports)
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return array Array of CSS filenames
 */
function vite_get_entry_css($entry) {
    $manifest = vite_get_manifest();
    
    if ($manifest === null) {
        return [];
    }
    
    $entry_key = "src/{$entry}.js";
    $css_files = [];
    
    if (!isset($manifest[$entry_key])) {
        return [];
    }
    
    $entry_data = $manifest[$entry_key];
    
    // Get CSS from the entry itself
    if (isset($entry_data['css'])) {
        $css_files = array_merge($css_files, $entry_data['css']);
    }
    
    // Get CSS from imports (chunks)
    if (isset($entry_data['imports'])) {
        foreach ($entry_data['imports'] as $import) {
            if (isset($manifest[$import]) && isset($manifest[$import]['css'])) {
                $css_files = array_merge($css_files, $manifest[$import]['css']);
            }
        }
    }
    
    return array_unique($css_files);
}

/**
 * Get imported chunks for an entry point
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return array Array of imported chunk filenames
 */
function vite_get_entry_imports($entry) {
    $manifest = vite_get_manifest();
    
    if ($manifest === null) {
        return [];
    }
    
    $entry_key = "src/{$entry}.js";
    $imports = [];
    
    if (!isset($manifest[$entry_key]) || !isset($manifest[$entry_key]['imports'])) {
        return [];
    }
    
    foreach ($manifest[$entry_key]['imports'] as $import) {
        if (isset($manifest[$import]) && isset($manifest[$import]['file'])) {
            $imports[] = $manifest[$import]['file'];
        }
    }
    
    return $imports;
}

/**
 * Generate script tag for an entry point
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return string HTML script tag(s)
 */
function vite_script_tag($entry) {
    if (vite_is_dev_mode()) {
        // Development mode: load from Vite dev server
        $dev_url = vite_dev_server_url();
        $html = '<script type="module" src="' . $dev_url . '/@vite/client"></script>' . "\n";
        $html .= '<script type="module" src="' . $dev_url . '/src/' . $entry . '.js"></script>';
        return $html;
    }
    
    // Production mode: load from built assets
    $file = vite_get_entry_file($entry);
    
    if ($file === null) {
        return '<!-- Vite: Entry "' . htmlspecialchars($entry) . '" not found in manifest -->';
    }
    
    $html = '';
    
    // Add modulepreload links for imported chunks (improves loading performance)
    $imports = vite_get_entry_imports($entry);
    foreach ($imports as $import) {
        $html .= '<link rel="modulepreload" href="/dist/' . htmlspecialchars($import) . '">' . "\n";
    }
    
    // Add the main entry script
    $html .= '<script type="module" src="/dist/' . htmlspecialchars($file) . '"></script>';
    
    return $html;
}

/**
 * Generate link tags for CSS associated with an entry point
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return string HTML link tag(s)
 */
function vite_css_tags($entry) {
    if (vite_is_dev_mode()) {
        // In dev mode, CSS is injected by Vite via JS
        return '';
    }
    
    $css_files = vite_get_entry_css($entry);
    
    if (empty($css_files)) {
        return '';
    }
    
    $tags = [];
    foreach ($css_files as $css_file) {
        $tags[] = '<link rel="stylesheet" href="/dist/' . htmlspecialchars($css_file) . '">';
    }
    
    return implode("\n", $tags);
}

/**
 * Generate all necessary tags (CSS + JS) for an entry point
 * 
 * @param string $entry Entry point name (e.g., 'main', 'auth')
 * @return string HTML tags for CSS and JS
 */
function vite_tags($entry) {
    $html = '';
    
    // CSS tags (empty in dev mode)
    $css = vite_css_tags($entry);
    if (!empty($css)) {
        $html .= $css . "\n";
    }
    
    // Script tags
    $html .= vite_script_tag($entry);
    
    return $html;
}
