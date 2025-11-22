<?php
header('Content-Type: application/json');
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
    $stmt = $db->prepare("
        SELECT block
        FROM admission_scores 
        WHERE major_id = ?
        AND block IS NOT NULL 
        AND block != '' 
    ");
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
    
    echo json_encode([
        'success' => true,
        'blocks' => $unique_blocks,
        'debug' => [
            'major_id' => $major_id,
            'total_records' => count($block_strings),
            'unique_blocks_count' => count($unique_blocks)
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
