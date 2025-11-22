<?php
// Cấu hình AI API (Gemini & Groq)
// Load từ environment variables hoặc dùng giá trị mặc định (để trống cho bảo mật)
// Tạo file .env trong thư mục gốc và thêm:
// GEMINI_API_KEY=your_key_here
// GROQ_API_KEY=your_key_here

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent');

define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');

/**
 * Gọi AI API (Gemini) với cơ chế retry khi kết nối không ổn định
 * @param string $prompt - Nội dung prompt
 * @param int $maxRetries - Số lần thử lại tối đa (mặc định 3)
 * @return string|null - Text response hoặc null nếu thất bại
 */
function callAIAPI($prompt, $maxRetries = 3) {
    $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 500,
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_NONE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_NONE'
            ]
        ]
    ];
    
    // Thử lại nhiều lần nếu gặp lỗi
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Tăng lên 20s cho ổn định hơn
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Nếu thành công
        if (!$error && $httpCode === 200) {
            $result = json_decode($response, true);
            
            // Check JSON decode error
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AI API - JSON decode error (Lần $attempt/$maxRetries): " . json_last_error_msg());
                error_log("Raw response: " . substr($response, 0, 500));
                if ($attempt < $maxRetries) sleep(2);
                continue; // Thử lại
            }
            
            // Check response structure
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $text = $result['candidates'][0]['content']['parts'][0]['text'];
                error_log("AI API - Success on attempt $attempt");
                return $text;
            } 
            
            // Check if blocked by safety settings
            if (isset($result['candidates'][0]['finishReason']) && 
                $result['candidates'][0]['finishReason'] === 'SAFETY') {
                error_log("AI API - Blocked by safety settings (Lần $attempt/$maxRetries)");
                if ($attempt < $maxRetries) sleep(2);
                continue; // Thử lại
            }
            
            // Invalid structure - log and retry
            error_log("AI API - Invalid response structure (Lần $attempt/$maxRetries)");
            error_log("Response keys: " . implode(', ', array_keys($result)));
            if (isset($result['candidates'][0])) {
                error_log("Candidate[0] keys: " . implode(', ', array_keys($result['candidates'][0])));
            }
            if ($attempt < $maxRetries) sleep(2);
        } else {
            // Log lỗi
            if ($error) {
                error_log("AI API cURL Error (Lần $attempt/$maxRetries): " . $error);
            } else {
                error_log("AI API HTTP Error (Lần $attempt/$maxRetries): Code $httpCode");
                
                // Handle specific HTTP errors
                if ($httpCode === 429) {
                    error_log("AI API - Rate limit exceeded. Đợi 5 giây...");
                    if ($attempt < $maxRetries) sleep(5); // Đợi lâu hơn cho rate limit
                    continue;
                } else if ($httpCode === 503) {
                    error_log("AI API - Service unavailable. Đợi 3 giây...");
                    if ($attempt < $maxRetries) sleep(3);
                    continue;
                }
            }
        }
        
        // Nếu chưa phải lần thử cuối, đợi trước khi thử lại
        if ($attempt < $maxRetries) {
            sleep(1); // Đợi 1 giây trước khi thử lại (default)
        }
    }
    
    // Sau khi thử hết vẫn lỗi
    error_log("AI API - Failed after $maxRetries attempts");
    return null;
}

/**
 * Tạo prompt phân tích dự đoán tuyển sinh
 */
function generatePredictionPrompt($data) {
    // Rút gọn prompt để tránh timeout
    $prompt = "Phân tích tuyển sinh đại học:\n\n";
    
    $prompt .= "Thí sinh: Khối " . $data['exam_block'] . ", tổng điểm " . $data['total_score'] . "\n";
    $prompt .= "Ngành: " . $data['major_name'] . " - " . $data['university_name'] . "\n\n";
    
    $prompt .= "Điểm chuẩn:\n";
    foreach ($data['historical_scores'] as $score) {
        $prompt .= "- " . $score['year'] . ": " . $score['score'] . "\n";
    }
    
    $prompt .= "\nYêu cầu (trả lời ngắn gọn 100-150 từ):\n";
    $prompt .= "Xu hướng điểm: [1-2 câu]\n";
    $prompt .= ". Lời khuyên: [2-3 điểm]\n";
    $prompt .= ". Kết luận: [nên giữ hay đổi]\n";
    
    return $prompt;
}

/**
 * Phân tích kết quả từ AI và trích xuất xác suất
 */
function parseAIResponse($response) {
    $result = [
        'probability' => null,
        'trend_analysis' => '',
        'recommendations' => [],
        'conclusion' => '',
        'full_text' => $response
    ];
    
    // Trích xuất xác suất - tìm bất kỳ số % nào trong text
    if (preg_match('/(?:xác suất|khả năng|cơ hội).*?[:\s]+(\d+)\s*%/iu', $response, $matches)) {
        $result['probability'] = intval($matches[1]);
    } elseif (preg_match('/(\d+)\s*%/i', $response, $matches)) {
        // Fallback: lấy số % đầu tiên
        $result['probability'] = intval($matches[1]);
    }
    
    // Trích xuất phân tích - tìm đoạn văn sau "Phân tích" hoặc đầu tiên
    if (preg_match('/(?:phân tích|xu hướng)[:\s]+(.*?)(?=lời khuyên|kết luận|\n\n)/isu', $response, $matches)) {
        $result['trend_analysis'] = trim($matches[1]);
    } else {
        // Fallback: lấy đoạn đầu tiên
        $lines = explode("\n", $response);
        $analysis_lines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !preg_match('/^(xác suất|lời khuyên|kết luận)/iu', $line)) {
                $analysis_lines[] = $line;
                if (count($analysis_lines) >= 3) break;
            }
        }
        $result['trend_analysis'] = implode(' ', $analysis_lines);
    }
    
    // Trích xuất lời khuyên - tìm các dòng bắt đầu bằng dấu - hoặc số
    preg_match_all('/[-•]\s*(.+?)(?=\n[-•]|\n\n|$)/su', $response, $advice_matches);
    if (!empty($advice_matches[1])) {
        $result['recommendations'] = array_map('trim', $advice_matches[1]);
    }
    
    // Trích xuất kết luận - tìm câu cuối hoặc sau "Kết luận"
    if (preg_match('/(?:kết luận)[:\s]+(.*?)$/isu', $response, $matches)) {
        $result['conclusion'] = trim($matches[1]);
    } else {
        // Fallback: lấy câu cuối cùng
        $sentences = preg_split('/[.!?]+/', $response);
        $result['conclusion'] = trim(end($sentences));
    }
    
    return $result;
}
