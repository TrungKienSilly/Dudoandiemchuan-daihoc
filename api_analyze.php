<?php
/**
 * API endpoint để phân tích tuyển sinh bằng AI
 * Thay thế Python backend, gọi trực tiếp Groq API từ PHP
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Shutdown handler: catch fatal errors and log them (useful on hosting)
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $msg = "SHUTDOWN_ERROR: " . date('Y-m-d H:i:s') . " - " . json_encode($err, JSON_UNESCAPED_UNICODE) . "\n";
        if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
            @file_put_contents(__DIR__ . '/ai_error.log', $msg, FILE_APPEND);
        }
        // Try to return JSON error if headers not sent
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        $errorMsg = 'Fatal server error.';
        if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
            $errorMsg .= ' Check ai_error.log on server.';
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
    }
});

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Prevent accidental warnings from being printed (which would break JSON)
@ini_set('display_errors', 0);
@error_reporting(0);

require_once __DIR__ . '/config/ai_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // If debug logging is enabled, allow GET for temporary debugging purposes
    if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING && $_SERVER['REQUEST_METHOD'] === 'GET') {
        // Use GET params as data for debugging
        if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
            @file_put_contents(__DIR__ . '/ai_request_debug.log', date('Y-m-d H:i:s') . " - Received GET for /api_analyze (debug mode). GET: " . json_encode($_GET, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }
        // Convert GET params into data array for backward compat
        $data = $_GET;
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Method Not Allowed'
        ]);
        exit;
    }
}

try {
    // Lấy dữ liệu từ request
    $rawInput = file_get_contents('php://input');
    // Debug: log incoming request method, headers và body
    $reqDebug = [
        'time' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'headers' => function_exists('getallheaders') ? getallheaders() : [],
        'body' => $rawInput
    ];
    if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
        file_put_contents(__DIR__ . '/ai_request_debug.log', json_encode($reqDebug, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }

    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate input
    $totalScore = $data['totalScore'] ?? 0;
    $examBlock = $data['examBlock'] ?? '';
    $universityName = $data['universityName'] ?? '';
    $majorName = $data['majorName'] ?? '';
    $historicalScores = $data['historicalScores'] ?? [];
    $provider = $data['provider'] ?? DEFAULT_AI_PROVIDER;
    
    if (empty($totalScore) || empty($examBlock) || empty($majorName)) {
        throw new Exception('Missing required fields');
    }
    
    // Tạo prompt
    $prompt = "Phân tích tuyển sinh đại học:\n\n";
    $prompt .= "Thí sinh: Khối {$examBlock}, tổng điểm {$totalScore}\n";
    $prompt .= "Ngành: {$majorName} - {$universityName}\n\n";
    $prompt .= "Điểm chuẩn các năm:\n";
    
    foreach ($historicalScores as $score) {
        $year = $score['year'] ?? '';
        $scoreValue = $score['score'] ?? '';
        $prompt .= "- Năm {$year}: {$scoreValue} điểm\n";
    }
    
    $prompt .= "\nHãy phân tích và tư vấn theo ĐÚNG FORMAT sau (bắt buộc):\n\n";
    $prompt .= "1. Phân tích xu hướng (chi tiết, ít nhất 300 từ, không xuất hiện dấu : ở đầu đoạn)\n";
    $prompt .= "Phân tích xu hướng điểm chuẩn ngành {$majorName} tại {$universityName} qua các năm (tăng/giảm/ổn định). ";
    $prompt .= "Phân tích điểm của thí sinh với điểm chuẩn cùng ngành của các trường khác cùng khối. ";
    $prompt .= "So sánh điểm {$totalScore} của thí sinh với điểm chuẩn. Đánh giá triển vọng ngành học trong tương lai.\n\n";
    $prompt .= "2. Lời khuyên\n";
    $prompt .= "Tôi chỉ muốn bạn đưa ra ĐÚNG 3 lời khuyên thôi, không cần câu dẫn hay giải thích thêm:\n";
    $prompt .= "(về điểm số và nguyện vọng)\n";
    $prompt .= "(về chuẩn bị phương án dự phòng)\n";
    $prompt .= "(về tham khảo ý kiến)\n\n";
    $prompt .= "3. Kết luận (ít nhất 100 từ, không xuất hiện dấu : ở đầu đoạn)\n";
    $prompt .= "Nên giữ hay đổi nguyện vọng này? Tại sao?\n\n";
    $prompt .= "LƯU Ý: Trả lời NGẮN GỌN, rõ ràng. Phần \"Lời khuyên\" chỉ ghi 3 dòng, không thêm bất kỳ câu dẫn nào.";
    
    // Gọi AI API
    if ($provider === 'groq' || empty($provider)) {
        $result = callGroqAPI($prompt);
    } else {
        // Có thể thêm Gemini sau
        $result = callGroqAPI($prompt);
    }
    
    if (!$result['success']) {
        // Ghi log lỗi từ callGroqAPI
        if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
            file_put_contents(__DIR__ . '/ai_call_error.log', date('Y-m-d H:i:s') . " - callGroqAPI error: " . $result['error'] . "\n", FILE_APPEND);
        }
        // Fallback: trả về phân tích giả (mock) để frontend vẫn hoạt động trên hosting
        $mock = [
            'probability' => 50,
            'trend_analysis' => "Dữ liệu lịch sử có sự dao động nhẹ. Điểm của bạn nằm ở mức trung bình so với điểm chuẩn gần nhất.",
            'recommendations' => [
                'Xem xét giữ nguyện vọng nếu điểm không thấp hơn nhiều so với điểm chuẩn.',
                'Chuẩn bị 1-2 nguyện vọng dự phòng có điểm chuẩn thấp hơn 1-2 điểm.',
                'Tham khảo ý kiến giáo viên chủ nhiệm và gia đình trước khi quyết định.'
            ],
            'conclusion' => "Tóm lại, dựa trên dữ liệu lịch sử, thí sinh có khả năng trung bình. Nên cân nhắc giữ nếu tự tin hoặc chuyển sang nguyện vọng gần với điểm chuẩn để an toàn.",
            'full_text' => "[MOCK] " . $result['error']
        ];

        echo json_encode([
            'success' => true,
            'analysis' => $mock,
            'raw_response' => '',
            'provider' => 'mock',
            'note' => 'Returned mock analysis because AI provider call failed. Check ai_call_error.log for details.'
        ]);
        exit;
    }
    
    // Parse response
    $analysis = parseAIResponse($result['content']);
    // Ensure sections are populated with fallbacks if parsing missed anything
    if (function_exists('ensureParsedSections')) {
        ensureParsedSections($analysis);
    }
    
    // Trả về kết quả
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'raw_response' => substr($result['content'], 0, 500),
        'provider' => $provider
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
