<?php
/**
 * Cấu hình AI API cho hosting
 * File này chứa API keys và endpoints
 */

// Đọc từ .env nếu có
if (file_exists(__DIR__ . '/../.env')) {
    $envFile = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envFile as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key) && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
}
// Debug: log giá trị key ra file
$debug = [
    'getenv_GROQ_API_KEY' => getenv('GROQ_API_KEY'),
    'env_GROQ_API_KEY' => isset($_ENV['GROQ_API_KEY']) ? $_ENV['GROQ_API_KEY'] : null,
    'defined_GROQ_API_KEY' => defined('GROQ_API_KEY') ? GROQ_API_KEY : null
];
// Add a safe debug switch to prevent writing env/API keys to disk in production.
define('AI_DEBUG_LOGGING', (getenv('AI_DEBUG_LOGGING') === '1' || getenv('AI_DEBUG_LOGGING') === 'true') ? true : false);
if (defined('AI_DEBUG_LOGGING') && AI_DEBUG_LOGGING) {
    file_put_contents(__DIR__ . '/../ai_env_debug.log', date('Y-m-d H:i:s') . "\n" . print_r($debug, true) . "\n", FILE_APPEND);
}

// API Keys - Lấy từ environment hoặc hardcode (CHÚ Ý: không commit API key lên Git)
// TODO: LẤY API KEY TẠI: https://console.groq.com (MIỄN PHÍ)
// Sau khi lấy key, thay thế dòng dưới đây:
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: 'gsk_THAY_BANG_API_KEY_CUA_BAN'); // ⚠️ THAY ĐỔI DÒNG NÀY
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: ''); // Hoặc để trống nếu chỉ dùng Groq

// API Endpoints
define('GROQ_API_ENDPOINT', 'https://api.groq.com/openai/v1/chat/completions');
define('GEMINI_API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent');

// Provider mặc định
define('DEFAULT_AI_PROVIDER', 'groq'); // 'groq' hoặc 'gemini'

// Models
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('GEMINI_MODEL', 'gemini-2.0-flash-exp');

/**
 * Hàm gọi Groq API trực tiếp
 */
function callGroqAPI($prompt, $apiKey = null) {
    $apiKey = $apiKey ?: GROQ_API_KEY;
    
    if (empty($apiKey)) {
        return [
            'success' => false,
            'error' => 'GROQ_API_KEY chưa được cấu hình'
        ];
    }
    
    $data = [
        'model' => GROQ_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Bạn là chuyên gia tư vấn tuyển sinh đại học, phân tích dữ liệu chính xác và đưa ra lời khuyên hữu ích.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
    
    $ch = curl_init(GROQ_API_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
        // Bỏ kiểm tra SSL cho môi trường localhost/dev (KHÔNG dùng cho production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // Timeouts to avoid hanging on hosting where outbound requests may be blocked
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // seconds to connect
    curl_setopt($ch, CURLOPT_TIMEOUT, 20); // total timeout
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode: " . $response
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        return [
            'success' => true,
            'content' => $result['choices'][0]['message']['content'],
            'provider' => 'groq'
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Invalid API response',
        'raw' => $result
    ];
}

/**
 * Hàm parse AI response
 */
function parseAIResponse($text) {
    $result = [
        'probability' => null,
        'trend_analysis' => '',
        'recommendations' => [],
        'conclusion' => '',
        'full_text' => $text
    ];
    
    // Trích xuất xác suất
    if (preg_match('/(\d+)\s*%/', $text, $matches)) {
        $result['probability'] = (int)$matches[1];
    }
    
    // Tìm các section
    $trendMatch = preg_match('/(?:^|\n)(?:###\s*)?1\.\s*(?:Phân tích xu hướng|PHÂN TÍCH XU HƯỚNG)/mi', $text, $trendPos, PREG_OFFSET_CAPTURE);
    $adviceMatch = preg_match('/(?:^|\n)(?:###\s*)?2\.\s*(?:Lời khuyên|LỜI KHUYÊN)/mi', $text, $advicePos, PREG_OFFSET_CAPTURE);
    $conclusionMatch = preg_match('/(?:^|\n)(?:###\s*)?3\.\s*(?:Kết luận|KẾT LUẬN)/mi', $text, $conclusionPos, PREG_OFFSET_CAPTURE);
    
    // Extract trend
    if ($trendMatch && $adviceMatch) {
        $start = $trendPos[0][1] + strlen($trendPos[0][0]);
        $end = $advicePos[0][1];
        $trendText = substr($text, $start, $end - $start);
        $result['trend_analysis'] = trim($trendText);
    }
    
    // Extract recommendations
    if ($adviceMatch && $conclusionMatch) {
        $start = $advicePos[0][1] + strlen($advicePos[0][0]);
        $end = $conclusionPos[0][1];
        $adviceText = substr($text, $start, $end - $start);
        
        preg_match_all('/[-•*]\s*(.+)/', $adviceText, $matches);
        if (!empty($matches[1])) {
            $result['recommendations'] = array_slice($matches[1], 0, 3);
        }
    }
    
    // Extract conclusion
    if ($conclusionMatch) {
        $start = $conclusionPos[0][1] + strlen($conclusionPos[0][0]);
        $conclusionText = substr($text, $start);
        $result['conclusion'] = trim($conclusionText);
    }
    
    // Fallback recommendations
    if (empty($result['recommendations'])) {
        $result['recommendations'] = [
            'Xem xét kỹ nguyện vọng và điểm số của mình',
            'Chuẩn bị các nguyện vọng dự phòng',
            'Tham khảo ý kiến từ giáo viên và gia đình'
        ];
    }
    
    return $result;
}

// Improve parsing: add robust fallbacks if sections are missing
function ensureParsedSections(&$result) {
    $text = $result['full_text'] ?? '';

    // Fallback for trend_analysis: take the first paragraph if empty
    if (empty(trim($result['trend_analysis'])) && !empty(trim($text))) {
        // Split into paragraphs by double newline
        $paras = preg_split('/\n\s*\n/', trim($text));
        if (!empty($paras) && isset($paras[0])) {
            $candidate = trim($paras[0]);
            // If candidate too short, try first 2 paragraphs
            if (strlen($candidate) < 80 && isset($paras[1])) {
                $candidate = trim($paras[0] . "\n\n" . $paras[1]);
            }
            $result['trend_analysis'] = $candidate;
        }
    }

    // Fallback for recommendations: try to extract lines with verbs or bullets
    if (empty($result['recommendations'])) {
        $lines = preg_split('/[\r\n]+/', $text);
        $candidates = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            // bullets
            if (preg_match('/^[-•*]\s*(.+)/u', $line, $m)) {
                $candidates[] = trim($m[1]);
                continue;
            }
            // numbered lines like 1., 2)
            if (preg_match('/^\d+[\.)]\s*(.+)/u', $line, $m)) {
                $candidates[] = trim($m[1]);
                continue;
            }
            // lines containing 'khuyên' or 'gợi ý' or 'nên'
            if (preg_match('/\b(khuyên|gợi ý|nên)\b/iu', $line)) {
                // clean leading label
                $line = preg_replace('/^(Lời khuyên[:\s]*)/iu', '', $line);
                $candidates[] = trim($line);
            }
        }
        if (!empty($candidates)) {
            // Deduplicate and take up to 3
            $unique = array_values(array_unique($candidates));
            $result['recommendations'] = array_slice($unique, 0, 3);
        }
    }

    // Fallback for conclusion: take last 2-3 sentences
    if (empty(trim($result['conclusion'])) && !empty(trim($text))) {
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/u', strip_tags($text));
        $sentences = array_filter(array_map('trim', $sentences));
        if (!empty($sentences)) {
            $last = array_slice($sentences, -3);
            $result['conclusion'] = implode(' ', $last);
        }
    }
    // Ensure recommendations has 3 items
    if (count($result['recommendations']) < 3) {
        $defaults = [
            'Xem xét kỹ nguyện vọng và điểm số của mình',
            'Chuẩn bị các nguyện vọng dự phòng',
            'Tham khảo ý kiến từ giáo viên và gia đình'
        ];
        foreach ($defaults as $d) {
            if (count($result['recommendations']) >= 3) break;
            if (!in_array($d, $result['recommendations'])) {
                $result['recommendations'][] = $d;
            }
        }
    }
}

// Automatically apply fallbacks after parse
// Wrap original parse call sites to call ensureParsedSections before returning
?>
