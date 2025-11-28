<?php
// API: return exam blocks for a major; robust error handling for hosting environment
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
ob_start();
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
set_exception_handler(function($e) {
    http_response_code(500);
    error_log('[get_exam_blocks] Exception: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Internal Server Error', 'detail' => $e->getMessage()]);
    exit;
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        if (ob_get_length()) ob_end_clean();
        error_log('[get_exam_blocks] Fatal error: ' . print_r($err, true));
        echo json_encode(['success' => false, 'error' => 'Server fatal error', 'detail' => $err['message']]);
        exit;
    }
});
require_once 'config/database.php';

$db = getDBConnection();

// Lấy major_id từ query string
$major_id = isset($_GET['major_id']) ? intval($_GET['major_id']) : 0;

if ($major_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid major_id']);
    exit;
}

try {
    // Lấy tất cả các block string cho major này (không cần university_id)
    $stmt = $db->prepare(
        "SELECT block FROM admission_scores WHERE major_id = ? AND block IS NOT NULL AND block != ''"
    );
    $stmt->execute([$major_id]);
    $block_strings = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Tách và chuẩn hóa các khối thi (vì có thể có nhiều khối trong 1 field, cách nhau bởi ;)
    $unique_blocks = [];
    foreach ($block_strings as $block_string) {
        // Tách theo dấu ; nếu có
        $blocks = explode(';', $block_string);
        foreach ($blocks as $block) {
            $normalized = trim($block, "; \t\n\r\0\x0B");
            if (!empty($normalized) && !in_array($normalized, $unique_blocks)) {
                $unique_blocks[] = $normalized;
            }
        }
    }

    // Sắp xếp theo alphabet
    sort($unique_blocks);

    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        'success' => true,
        'blocks' => $unique_blocks,
        'debug' => [
            'major_id' => $major_id,
            'total_records' => count($block_strings),
            'unique_blocks_count' => count($unique_blocks)
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[get_exam_blocks] Error: ' . $e->getMessage());
    if (ob_get_length()) ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Database error', 'detail' => $e->getMessage()]);
}
