<?php
/**
 * config/api_bootstrap.php
 * Centralized JSON API bootstrap for endpoints
 * - Sets Content-Type header to JSON
 * - Converts PHP errors to exceptions and catches them
 * - Provides helper api_json_response and api_json_error
 */

if (defined('API_BOOTSTRAP_LOADED')) return;
define('API_BOOTSTRAP_LOADED', 1);

// Don't output HTML error messages; prefer JSON.
@ini_set('display_errors', 0);
@error_reporting(E_ALL);

// Ensure we have a JSON content type if headers not already sent
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Helper: send JSON response and stop
function api_json_response($data, $httpStatus = 200) {
    http_response_code($httpStatus);
    if (ob_get_length()) ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Helper: error response
function api_json_error($message, $detail = null, $httpStatus = 500) {
    $payload = ['success' => false, 'error' => $message];
    if ($detail !== null) {
        $payload['detail'] = $detail;
    }
    api_json_response($payload, $httpStatus);
}

// Convert warnings/notice to exceptions to make behavior predictable
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Respect @ operator: E_RECOVERABLE_ERROR still should be converted
    if (error_reporting() === 0) return false;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Exceptions -> JSON response
set_exception_handler(function($e) {
    error_log('[api_bootstrap] Uncaught exception: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    api_json_error('Internal Server Error', $e->getMessage(), 500);
});

// Catch fatal errors and convert to JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        error_log('[api_bootstrap] Fatal error: ' . print_r($err, true));
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        if (ob_get_length()) ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Server fatal error', 'detail' => $err['message']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

// Optionally check for debug logging
if (!defined('API_DEBUG_LOGGING')) {
    define('API_DEBUG_LOGGING', getenv('API_DEBUG_LOGGING') === '1' || getenv('API_DEBUG_LOGGING') === 'true');
}

?>
