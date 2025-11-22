<?php
header('Content-Type: application/json');
require_once 'config/database.php';

$university_id = isset($_GET['university_id']) ? intval($_GET['university_id']) : 0;

if ($university_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid university ID']);
    exit;
}

try {
    $db = getDBConnection();
    
    $stmt = $db->prepare("
        SELECT id, name, code 
        FROM majors 
        WHERE university_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$university_id]);
    $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'majors' => $majors
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
