<?php
// API: return majors for a university; robust error handling for hosting environment
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
// Start output buffering to capture any stray output and convert to JSON on shutdown if needed
ob_start();
// Convert PHP warnings/notices to exceptions so we can return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
// Central exception handler to always return JSON
set_exception_handler(function($e) {
    http_response_code(500);
    error_log('[get_majors] Exception: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    // Attempt to clear any buffer and return JSON
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Internal Server Error', 'detail' => $e->getMessage()]);
    exit;
});
// Shutdown handler to catch fatal errors (including die) and convert to JSON
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        if (ob_get_length()) ob_end_clean();
        error_log('[get_majors] Fatal error: ' . print_r($err, true));
        echo json_encode(['success' => false, 'error' => 'Server fatal error', 'detail' => $err['message']]);
        exit;
    }
});
require_once 'config/database.php';

$university_id = isset($_GET['university_id']) ? intval($_GET['university_id']) : 0;

if ($university_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid university ID']);
    exit;
}

try {
    $db = getDBConnection();

    $stmt = $db->prepare(
        "SELECT id, name, code FROM majors WHERE university_id = ? ORDER BY name"
    );
    $stmt->execute([$university_id]);
    $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON and clear any buffered output
    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        'success' => true,
        'majors' => $majors
    ]);
} catch (Throwable $e) {
    // Any exception is handled by set_exception_handler too; but provide additional context
    http_response_code(500);
    error_log('[get_majors] Error in try-catch: ' . $e->getMessage());
    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error or server error',
        'detail' => $e->getMessage()
    ]);
}

