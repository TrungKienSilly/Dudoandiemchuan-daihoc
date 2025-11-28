<?php
/**
 * ai_chat.php
 * Simple PHP endpoint to proxy chat messages to the AI provider.
 * Frontend (chat widget) posts { message: string, provider?: string }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Prevent accidental HTML/PHP warnings from being sent to client as non-JSON
@ini_set('display_errors', 0);
@error_reporting(0);

// Use ai_config which contains GR OQ integration and parsing; avoid including ai.php to prevent duplicate function declarations
require_once __DIR__ . '/config/ai_config.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    // Fallback: if client posted as form-data or application/x-www-form-urlencoded
    if (!$data && !empty($_POST['message'])) {
        $data = ['message' => trim($_POST['message']), 'provider' => $_POST['provider'] ?? 'groq'];
    }
    // optional debug log for incoming chat requests
    if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
        @file_put_contents(__DIR__ . '/ai_chat_request.log', date('Y-m-d H:i:s') . " " . json_encode(
            ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'uri' => $_SERVER['REQUEST_URI'] ?? '', 'body' => $data], JSON_UNESCAPED_UNICODE
        ) . "\n", FILE_APPEND);
    }
    if (!$data || empty($data['message'])) {
        // Dump raw body for debugging to ai_chat_raw.log when debug flag enabled
        if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
            @file_put_contents(__DIR__ . '/ai_chat_raw.log', date('Y-m-d H:i:s') . " REMOTE: " . ($_SERVER['REMOTE_ADDR'] ?? '') . " URI: " . ($_SERVER['REQUEST_URI'] ?? '') . " RAW: " . $raw . " POST: " . json_encode($_POST, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input: message is required']);
        exit;
    }

    $message = trim($data['message']);
    $provider = $data['provider'] ?? 'groq';

    // Build a brief prompt wrapper for chat-style messages
    $prompt = "Bạn là một trợ lý tuyển sinh. Trả lời ngắn gọn và hữu ích. \nUser: " . $message . "\nAssistant:";

    $responseText = null;
    // Only support Groq provider from PHP for now (Gemini requires a different client and may be used separately)
    if ($provider !== 'groq') $provider = 'groq';
    if ($provider === 'groq') {
        $res = callGroqAPI($prompt);
        if (is_array($res) && isset($res['success']) && $res['success'] === true) {
            $responseText = $res['content'] ?? '';
        } else {
            // If Groq call fails, fallback to a mock response to avoid breaking chat UI
            $errorMsg = is_array($res) && isset($res['error']) ? $res['error'] : 'Groq API error';
            if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
                @file_put_contents(__DIR__ . '/ai_chat_error.log', date('Y-m-d H:i:s') . ' - Groq error: ' . $errorMsg . "\n", FILE_APPEND);
            }
            // Fallback: simple canned response (keeps UI working when AI provider is unavailable)
            $responseText = "Xin chào, tôi là trợ lý AI. Hiện tại hệ thống AI đang gặp sự cố (code: groq_error). Vui lòng thử lại sau hoặc tham khảo thông tin trên trang. Nếu bạn cần trợ giúp khẩn cấp, hãy cung cấp chi tiết hơn để chúng tôi tư vấn.";
            $provider = 'mock';
        }
    } else {
        $aiResponse = callAIAPI($prompt);
        if ($aiResponse === null) {
            if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
                @file_put_contents(__DIR__ . '/ai_chat_error.log', date('Y-m-d H:i:s') . ' - Gemini error\n', FILE_APPEND);
            }
            $responseText = "Xin chào, hiện tại hệ thống AI đang tạm thời không khả dụng. Vui lòng thử lại sau.";
            $provider = 'mock';
        } else {
            $responseText = $aiResponse;
        }
    }

    // Return the text response
    echo json_encode([
        'success' => true,
        'provider' => $provider,
        'response' => $responseText
    ]);
    exit;

} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $t->getMessage()]);
    exit;
}


