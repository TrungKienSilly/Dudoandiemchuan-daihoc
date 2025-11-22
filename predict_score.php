<?php
session_start(); // Bắt đầu session để lưu dữ liệu cho AI
require_once 'config/database.php';
require_once 'config/ai.php';

$page_title = 'Dự đoán khả năng đậu - Hệ thống tuyển sinh';
$db = getDBConnection();

// Lấy danh sách các khối thi và chuẩn hóa
$exam_blocks = [];
$stmt = $db->query("SELECT DISTINCT TRIM(TRAILING ';' FROM block) as block FROM admission_scores WHERE block IS NOT NULL AND block != '' ORDER BY block");
$raw_blocks = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Loại bỏ trùng lặp và chuẩn hóa
$unique_blocks = [];
foreach ($raw_blocks as $block) {
    $normalized = trim($block, "; \t\n\r\0\x0B");
    if (!empty($normalized) && !in_array($normalized, $unique_blocks)) {
        $unique_blocks[] = $normalized;
    }
}
$exam_blocks = $unique_blocks;

// Lấy danh sách trường
$universities = [];
$stmt = $db->query("SELECT id, name, province FROM universities ORDER BY name");
$universities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách ngành theo trường (sẽ load bằng AJAX)
$majors = [];
$selected_university_id = null;

// Xử lý form submit
$prediction_result = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_block = $_POST['exam_block'] ?? '';
    $subject1_score = floatval($_POST['subject1_score'] ?? 0);
    $subject2_score = floatval($_POST['subject2_score'] ?? 0);
    $subject3_score = floatval($_POST['subject3_score'] ?? 0);
    $university_id = intval($_POST['university_id'] ?? 0);
    $major_id = intval($_POST['major_id'] ?? 0);
    
    // Validate input
    if (empty($exam_block) || $university_id <= 0 || $major_id <= 0) {
        $error_message = "Vui lòng điền đầy đủ thông tin!";
    } elseif ($subject1_score < 0 || $subject1_score > 10 || 
              $subject2_score < 0 || $subject2_score > 10 || 
              $subject3_score < 0 || $subject3_score > 10) {
        $error_message = "Điểm các môn phải nằm trong khoảng 0-10!";
    } else {
        // Tính tổng điểm
        $total_score = $subject1_score + $subject2_score + $subject3_score;
        
        // Lấy thông tin trường và ngành
        $stmt = $db->prepare("SELECT name FROM universities WHERE id = ?");
        $stmt->execute([$university_id]);
        $university_name = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT name FROM majors WHERE id = ?");
        $stmt->execute([$major_id]);
        $major_name = $stmt->fetchColumn();
        
        // Chuẩn hóa exam_block để tìm kiếm
        $normalized_exam_block = trim($exam_block, "; \t\n\r\0\x0B");
        
        // Lấy điểm chuẩn các năm gần nhất của ngành này
        // Tìm kiếm linh hoạt: khối thi có thể là "A00" hoặc "A00; A01;" hoặc nằm trong chuỗi nhiều khối
        $stmt = $db->prepare("
            SELECT year, min_score as score, block as exam_block 
            FROM admission_scores 
            WHERE major_id = ? 
            AND (
                block = ?
                OR block LIKE ?
                OR block LIKE ?
                OR block LIKE ?
                OR TRIM(TRAILING ';' FROM TRIM(block)) = ?
            )
            ORDER BY year DESC
            LIMIT 5
        ");
        
        // Các pattern tìm kiếm: bắt đầu, kết thúc, ở giữa, hoặc chính xác
        $pattern_start = $normalized_exam_block . ';%';  // "A00;..."
        $pattern_middle = '%; ' . $normalized_exam_block . ';%';  // "...; A00;..."
        $pattern_end = '%; ' . $normalized_exam_block;  // "...; A00"
        
        $stmt->execute([
            $major_id, 
            $normalized_exam_block,  // Khớp chính xác "A00"
            $pattern_start,          // Khớp "A00;..."
            $pattern_middle,         // Khớp "...; A00;..."
            $pattern_end,            // Khớp "...; A00"
            $normalized_exam_block   // Khớp sau khi trim "A00;"
        ]);
        $historical_scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($historical_scores)) {
            // Lấy danh sách các khối thi có sẵn của ngành này để gợi ý
            $stmt = $db->prepare("SELECT DISTINCT block FROM admission_scores WHERE major_id = ? LIMIT 10");
            $stmt->execute([$major_id]);
            $available_blocks = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($available_blocks)) {
                $blocks_list = implode(', ', array_map(function($b) {
                    return trim($b, "; \t\n\r\0\x0B");
                }, $available_blocks));
                $error_message = "Không tìm thấy dữ liệu điểm chuẩn cho khối thi <strong>$exam_block</strong>!<br>Ngành <strong>$major_name</strong> có điểm chuẩn cho các khối: <strong>$blocks_list</strong><br>Vui lòng chọn lại khối thi phù hợp.";
            } else {
                $error_message = "Không tìm thấy dữ liệu điểm chuẩn cho ngành <strong>$major_name</strong> và khối thi <strong>$exam_block</strong>!";
            }
        } else {
            // Tính điểm trung bình và xu hướng
            $scores_array = array_column($historical_scores, 'score');
            $avg_score = array_sum($scores_array) / count($scores_array);
            $max_score = max($scores_array);
            $min_score = min($scores_array);
            $latest_score = $scores_array[0]; // Điểm chuẩn năm gần nhất
            
            // Dự đoán đơn giản dựa trên điểm chuẩn
            $prediction_result = [
                'university_name' => $university_name,
                'major_name' => $major_name,
                'exam_block' => $exam_block,
                'total_score' => $total_score,
                'subject1_score' => $subject1_score,
                'subject2_score' => $subject2_score,
                'subject3_score' => $subject3_score,
                'avg_score' => $avg_score,
                'max_score' => $max_score,
                'min_score' => $min_score,
                'latest_score' => $latest_score,
                'historical_scores' => $historical_scores,
                'difference' => $total_score - $latest_score,
                'pass_probability' => 0,
                'ai_analysis' => null
            ];
            
            // KHÔNG gọi AI tự động nữa - chỉ gọi khi người dùng nhấn nút
            // Lưu dữ liệu vào session để dùng cho AJAX call
            $_SESSION['prediction_data'] = $prediction_result;
            
            // Tính xác suất đậu bằng logic cơ bản
            if ($prediction_result['pass_probability'] == 0) {
                if ($total_score >= $max_score + 1) {
                    $prediction_result['pass_probability'] = 95;
                    $prediction_result['status'] = 'high';
                    $prediction_result['message'] = 'Khả năng đậu rất cao!';
                } elseif ($total_score >= $latest_score + 0.5) {
                    $prediction_result['pass_probability'] = 80;
                    $prediction_result['status'] = 'high';
                    $prediction_result['message'] = 'Khả năng đậu cao!';
                } elseif ($total_score >= $latest_score) {
                    $prediction_result['pass_probability'] = 65;
                    $prediction_result['status'] = 'medium';
                    $prediction_result['message'] = 'Có khả năng đậu!';
                } elseif ($total_score >= $latest_score - 0.5) {
                    $prediction_result['pass_probability'] = 45;
                    $prediction_result['status'] = 'medium';
                    $prediction_result['message'] = 'Khả năng đậu trung bình, cần cân nhắc!';
                } elseif ($total_score >= $min_score) {
                    $prediction_result['pass_probability'] = 30;
                    $prediction_result['status'] = 'low';
                    $prediction_result['message'] = 'Khả năng đậu thấp, nên cân nhắc nguyện vọng khác!';
                } else {
                    $prediction_result['pass_probability'] = 15;
                    $prediction_result['status'] = 'low';
                    $prediction_result['message'] = 'Khả năng đậu rất thấp!';
                }
            }
            
            // Xác định status dựa trên xác suất
            if ($prediction_result['pass_probability'] >= 70) {
                $prediction_result['status'] = 'high';
                if (!isset($prediction_result['message'])) {
                    $prediction_result['message'] = 'Khả năng đậu cao!';
                }
            } elseif ($prediction_result['pass_probability'] >= 40) {
                $prediction_result['status'] = 'medium';
                if (!isset($prediction_result['message'])) {
                    $prediction_result['message'] = 'Khả năng đậu trung bình!';
                }
            } else {
                $prediction_result['status'] = 'low';
                if (!isset($prediction_result['message'])) {
                    $prediction_result['message'] = 'Khả năng đậu thấp!';
                }
            }
        }
    }
}

$page_title = 'Dự đoán khả năng đậu - Hệ thống tuyển sinh';

// Thêm class để ẩn banner và thêm CSS cho predict page
$additional_css = '<style>
.header { display: none !important; }
body.predict-page {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%) !important;
    min-height: 100vh;
}
</style>';

include 'includes/header.php';
?>

<div class="predict-container">
    <div class="predict-header">
        <h1>Dự đoán khả năng đậu đại học</h1>
        <p>Nhập điểm số và nguyện vọng của bạn để dự đoán khả năng trúng tuyển</p>
    </div>
    
    <?php if ($error_message): ?>
        <div class="alert-error">
            <?php echo escape($error_message); ?>
        </div>
    <?php endif; ?>
    
    <div class="info-box">
        <h4>Lưu ý:</h4>
        <ul>
            <li>Dự đoán dựa trên dữ liệu điểm chuẩn các năm trước</li>
            <li>Kết quả chỉ mang tính chất tham khảo</li>
            <li>Nên chuẩn bị nhiều nguyện vọng dự phòng</li>
            <li>Điểm chuẩn thực tế có thể thay đổi hàng năm</li>
        </ul>
    </div>
    
    <form method="POST" class="predict-form">
        <div class="form-section">
            <h3>1. Chọn nguyện vọng</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="university_id">Trường đại học *</label>
                    <select id="university_id" name="university_id" required onchange="loadMajors(this.value)">
                        <option value="">-- Chọn trường --</option>
                        <?php foreach ($universities as $uni): ?>
                            <option value="<?php echo $uni['id']; ?>" <?php echo (isset($_POST['university_id']) && $_POST['university_id'] == $uni['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($uni['name']); ?> (<?php echo escape($uni['province']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="major_id">Ngành học *</label>
                    <select id="major_id" name="major_id" required onchange="loadExamBlocks(this.value)">
                        <option value="">-- Chọn trường trước --</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3>2. Thông tin điểm thi</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="exam_block">Khối thi *</label>
                    <select id="exam_block" name="exam_block" required>
                        <option value="">-- Chọn ngành trước --</option>
                    </select>
                    <small style="color: #666; font-size: 0.9em;">Chỉ hiển thị các khối thi phù hợp với ngành đã chọn</small>
                </div>
            </div>
            
            <div class="score-inputs">
                <div class="form-group">
                    <label for="subject1_score">Điểm môn 1 </label>
                    <input type="number" id="subject1_score" name="subject1_score" 
                           min="0" max="10" step="0.25" 
                           value="<?php echo isset($_POST['subject1_score']) ? escape($_POST['subject1_score']) : ''; ?>"
                           placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label for="subject2_score">Điểm môn 2 </label>
                    <input type="number" id="subject2_score" name="subject2_score" 
                           min="0" max="10" step="0.25" 
                           value="<?php echo isset($_POST['subject2_score']) ? escape($_POST['subject2_score']) : ''; ?>"
                           placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label for="subject3_score">Điểm môn 3 </label>
                    <input type="number" id="subject3_score" name="subject3_score" 
                           min="0" max="10" step="0.25" 
                           value="<?php echo isset($_POST['subject3_score']) ? escape($_POST['subject3_score']) : ''; ?>"
                           placeholder="0.00" required>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn-predict">Dự đoán khả năng đậu</button>
    </form>
    
    <?php if ($prediction_result): ?>
        <div class="result-container">            
            <h3>Thông tin nguyện vọng</h3>
            <p><strong>Trường:</strong> <?php echo escape($prediction_result['university_name']); ?></p>
            <p><strong>Ngành:</strong> <?php echo escape($prediction_result['major_name']); ?></p>
            <p><strong>Khối thi:</strong> <?php echo escape($prediction_result['exam_block']); ?></p>
            
            <div class="result-details">
                <div class="detail-card">
                    <h4>Tổng điểm của bạn</h4>
                    <p><?php echo number_format($prediction_result['total_score'], 2); ?></p>
                </div>
                
                <div class="detail-card">
                    <h4>Điểm chuẩn năm gần nhất</h4>
                    <p><?php echo number_format($prediction_result['latest_score'], 2); ?></p>
                </div>
                
                <div class="detail-card">
                    <h4>Chênh lệch</h4>
                    <p class="<?php echo $prediction_result['difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $prediction_result['difference'] >= 0 ? '+' : ''; ?>
                        <?php echo number_format($prediction_result['difference'], 2); ?>
                    </p>
                </div>
                
                <div class="detail-card">
                    <h4>Điểm TB 5 năm</h4>
                    <p><?php echo number_format($prediction_result['avg_score'], 2); ?></p>
                </div>
            </div>
            
            <h3 class="mt-2">Lịch sử điểm chuẩn</h3>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Năm</th>
                        <th>Khối thi</th>
                        <th>Điểm chuẩn</th>
                        <th>So với điểm của bạn</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prediction_result['historical_scores'] as $score): ?>
                        <tr>
                            <td><?php echo escape($score['year']); ?></td>
                            <td><?php echo escape($score['exam_block']); ?></td>
                            <td><strong><?php echo number_format($score['score'], 2); ?></strong></td>
                            <td>
                                <?php 
                                $diff = $prediction_result['total_score'] - $score['score'];
                                $diff_class = $diff >= 0 ? 'result-difference-positive' : 'result-difference-negative';
                                ?>
                                <span class="<?php echo $diff_class; ?>">
                                    <?php echo $diff >= 0 ? '+' : ''; ?>
                                    <?php echo number_format($diff, 2); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            
            <!-- Nút gọi AI phân tích (ẩn đi vì sẽ tự động gọi) -->
            <div id="aiAnalysisBtn" style="display: none;">
                <!-- Button sẽ tự động trigger khi trang load -->
            </div>
            
            <!-- Khu vực hiển thị kết quả AI -->
            <div id="aiAnalysisContainer" style="display: none;">
                <!-- Loading state -->
                <div id="aiLoading" class="info-box" style="background: #e7f3ff; border-left: 4px solid #2196F3; display: none;">
                    <h4 style="margin: 0 0 10px 0; color: #1976D2;">Đang phân tích...</h4>
                    <p style="margin: 0; color: #1976D2;">AI đang xử lý dữ liệu của bạn. Vui lòng đợi trong giây lát...</p>
                    <div style="margin-top: 10px;">
                        <div style="width: 100%; height: 4px; background: #bbdefb; border-radius: 2px; overflow: hidden;">
                            <div style="width: 30%; height: 100%; background: #2196F3; animation: loading 1.5s infinite;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Success state -->
                <div id="aiSuccess" style="display: none;">
                    <div class="info-box" style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #155724;">AI phân tích thành công!</h4>
                        <p style="margin: 0; color: #155724;">AI đã phân tích dữ liệu và đưa ra nhận định chi tiết.</p>
                    </div>
                    
                    <div class="ai-analysis-section">
                        <h3>Phân tích từ AI</h3>
                        
                        <div id="aiTrendAnalysis" class="ai-card trend-card" style="display: none;">
                            <h4>Phân tích xu hướng</h4>
                            <p id="trendText"></p>
                        </div>
                        
                        <div id="aiRecommendations" class="ai-card recommendations-card" style="display: none;">
                            <h4>Lời khuyên từ AI</h4>
                            <ul id="recommendationsList"></ul>
                        </div>
                        
                        <div id="aiConclusion" class="ai-card conclusion-card" style="display: none;">
                            <h4>Kết luận</h4>
                            <p id="conclusionText"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Error state -->
                <div id="aiError" class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; display: none;">
                    <h4 style="margin: 0 0 10px 0; color: #856404;">Không thể kết nối với AI</h4>
                    <p id="errorMessage" style="margin: 0; color: #856404;"></p>
                    <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #856404;">
                        <em>Có thể do rate limit (quá 15 requests/phút) hoặc server đang bận. Vui lòng thử lại sau vài phút.</em>
                    </p>
                </div>
            </div>
            
            <?php if (isset($prediction_result['ai_status'])): ?>
                <?php if ($prediction_result['ai_status'] === 'unavailable'): ?>
                    <div class="info-box" style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #856404;">Thông báo về AI:</h4>
                        <p style="margin: 0; color: #856404;"><?php echo $prediction_result['ai_debug']; ?></p>
                        <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #856404;">
                            <em>Hệ thống đã thử kết nối 3 lần nhưng không thành công. Kết quả dự đoán vẫn chính xác dựa trên dữ liệu lịch sử 5 năm.</em>
                        </p>
                    </div>
                <?php elseif ($prediction_result['ai_status'] === 'success'): ?>
                    <div class="info-box" style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #155724;">AI phân tích thành công!</h4>
                        <p style="margin: 0; color: #155724;">AI đã phân tích dữ liệu và đưa ra nhận định chi tiết bên dưới.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes loading {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(400%); }
}

/* AI Analysis Cards Styling */
.ai-analysis-section {
    margin-top: 30px;
}

.ai-analysis-section > h3 {
    color: #5856d6;
    font-size: 1.5rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 3px solid #5856d6;
}

.ai-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.ai-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.ai-card h4 {
    margin: 0 0 15px 0;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-card p, .ai-card ul {
    margin: 0;
    line-height: 1.8;
    color: #333;
    white-space: pre-wrap; /* Giữ nguyên xuống dòng và spaces */
    word-wrap: break-word; /* Tự động xuống dòng khi text quá dài */
    overflow-wrap: break-word; /* Đảm bảo text không tràn ra ngoài */
}

.ai-card p {
    text-align: justify; /* Căn đều 2 bên */
}

/* Trend Card - Blue Theme */
.trend-card {
    border-left: 4px solid #5856d6;
    background: linear-gradient(135deg, #f5f7ff 0%, #ffffff 100%);
}

.trend-card h4 {
    color: #5856d6;
}

/* Recommendations Card - Green Theme */
.recommendations-card {
    border-left: 4px solid #34c759;
    background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
}

.recommendations-card h4 {
    color: #34c759;
}

.recommendations-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.recommendations-card li {
    padding: 10px 0 10px 30px;
    position: relative;
    border-bottom: 1px solid #e8f5e9;
}

.recommendations-card li:last-child {
    border-bottom: none;
}

.recommendations-card li:before {
    content: "✓";
    position: absolute;
    left: 0;
    top: 10px;
    background: #34c759;
    color: white;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

/* Conclusion Card - Purple Theme */
.conclusion-card {
    border-left: 4px solid #af52de;
    background: linear-gradient(135deg, #faf5ff 0%, #ffffff 100%);
}

.conclusion-card h4 {
    color: #af52de;
}

.conclusion-card p {
    font-size: 1.05rem;
    font-weight: 500;
}
</style>

<script>
// Load majors when university is selected
function loadMajors(universityId) {
    const majorSelect = document.getElementById('major_id');
    const examBlockSelect = document.getElementById('exam_block');
    
    if (!universityId) {
        majorSelect.innerHTML = '<option value="">-- Chọn trường trước --</option>';
        examBlockSelect.innerHTML = '<option value="">-- Chọn ngành trước --</option>';
        return;
    }
    
    majorSelect.innerHTML = '<option value="">Đang tải...</option>';
    examBlockSelect.innerHTML = '<option value="">-- Chọn ngành trước --</option>';
    
    fetch(`get_majors.php?university_id=${universityId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.majors.length > 0) {
                let options = '<option value="">-- Chọn ngành --</option>';
                data.majors.forEach(major => {
                    options += `<option value="${major.id}">${major.name} (${major.code})</option>`;
                });
                majorSelect.innerHTML = options;
            } else {
                majorSelect.innerHTML = '<option value="">Không có ngành nào</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            majorSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        });
}

// Load exam blocks when major is selected
function loadExamBlocks(majorId) {
    const examBlockSelect = document.getElementById('exam_block');
    
    if (!majorId) {
        examBlockSelect.innerHTML = '<option value="">-- Chọn ngành trước --</option>';
        return;
    }
    
    console.log(`Loading exam blocks for major_id: ${majorId}`);
    examBlockSelect.innerHTML = '<option value="">Đang tải khối thi...</option>';
    
    fetch(`get_exam_blocks.php?major_id=${majorId}`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.success && data.blocks && data.blocks.length > 0) {
                let options = '<option value="">-- Chọn khối thi --</option>';
                data.blocks.forEach(block => {
                    options += `<option value="${block}">Khối ${block}</option>`;
                });
                examBlockSelect.innerHTML = options;
                console.log(`Loaded ${data.blocks.length} exam blocks:`, data.blocks);
                if (data.debug) {
                    console.log('Debug info:', data.debug);
                }
            } else {
                examBlockSelect.innerHTML = '<option value="">Không có khối thi nào</option>';
                console.warn('No exam blocks found for this major');
                if (data.error) {
                    console.error('Error from API:', data.error);
                }
            }
        })
        .catch(error => {
            console.error('Error loading exam blocks:', error);
            examBlockSelect.innerHTML = '<option value="">Lỗi tải khối thi</option>';
        });
}

// Auto load majors and exam blocks if pre-selected
document.addEventListener('DOMContentLoaded', function() {
    const universitySelect = document.getElementById('university_id');
    const selectedUniversityId = universitySelect.value;
    const selectedMajorId = '<?php echo isset($_POST['major_id']) ? $_POST['major_id'] : ''; ?>';
    const selectedExamBlock = '<?php echo isset($_POST['exam_block']) ? $_POST['exam_block'] : ''; ?>';
    
    if (selectedUniversityId) {
        fetch(`get_majors.php?university_id=${selectedUniversityId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.majors.length > 0) {
                    let options = '<option value="">-- Chọn ngành --</option>';
                    data.majors.forEach(major => {
                        const selected = major.id == selectedMajorId ? ' selected' : '';
                        options += `<option value="${major.id}"${selected}>${major.name} (${major.code})</option>`;
                    });
                    document.getElementById('major_id').innerHTML = options;
                    
                    // Nếu có major được chọn, load exam blocks
                    if (selectedMajorId) {
                        fetch(`get_exam_blocks.php?major_id=${selectedMajorId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.blocks.length > 0) {
                                    let blockOptions = '<option value="">-- Chọn khối thi --</option>';
                                    data.blocks.forEach(block => {
                                        const selected = block == selectedExamBlock ? ' selected' : '';
                                        blockOptions += `<option value="${block}"${selected}>Khối ${block}</option>`;
                                    });
                                    document.getElementById('exam_block').innerHTML = blockOptions;
                                }
                            });
                    }
                }
            });
    }
});

// Hàm gọi AI (tách riêng để tái sử dụng)
async function callAIAnalysis() {
    const aiBtn = document.getElementById('aiAnalysisBtn');
    if (!aiBtn) return;
    
    // Bắt đầu phân tích
    // Bắt đầu phân tích
    // Hiển thị container và loading
    document.getElementById('aiAnalysisContainer').style.display = 'block';
    document.getElementById('aiLoading').style.display = 'block';
    document.getElementById('aiSuccess').style.display = 'none';
    document.getElementById('aiError').style.display = 'none';
    
    // Disable button và hiển thị trạng thái
    aiBtn.disabled = true;
    aiBtn.style.opacity = '0.6';
    aiBtn.innerHTML = '<span>Đang phân tích...</span>';
    
    // Scroll to AI section
    setTimeout(() => {
        document.getElementById('aiAnalysisContainer').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, 100);
    
    // Lấy dữ liệu từ PHP
    const predictionData = {
        totalScore: <?php echo isset($prediction_result['total_score']) ? $prediction_result['total_score'] : 0; ?>,
        examBlock: <?php echo json_encode($prediction_result['exam_block'] ?? ''); ?>,
        universityName: <?php echo json_encode($prediction_result['university_name'] ?? ''); ?>,
        majorName: <?php echo json_encode($prediction_result['major_name'] ?? ''); ?>,
        historicalScores: <?php echo json_encode($prediction_result['historical_scores'] ?? []); ?>,
        provider: 'groq'  // Mặc định dùng Groq (nhanh và free), có thể đổi thành 'gemini'
    };
    
    // Tạo prompt
    let prompt = `Phân tích tuyển sinh đại học:\n\n`;
    prompt += `Thí sinh: Khối ${predictionData.examBlock}, tổng điểm ${predictionData.totalScore}\n`;
    prompt += `Ngành: ${predictionData.majorName} - ${predictionData.universityName}\n\n`;
    prompt += `Điểm chuẩn:\n`;
    predictionData.historicalScores.forEach(score => {
        prompt += `- ${score.year}: ${score.score}\n`;
    });
    prompt += `\nYêu cầu (trả lời ngắn gọn 100-150 từ):\n`;
    prompt += `Xu hướng điểm: [1-2 câu]\n`;
    prompt += `. Lời khuyên: [2-3 điểm]\n`;
    prompt += `4. Kết luận: [nên giữ hay đổi]\n`;
    
    console.group('AI Request');
    console.log('Prompt:', prompt.substring(0, 200) + '...');
    console.log('Provider:', predictionData.provider);
    
    try {
        // Gọi Python backend API
        const PYTHON_API_URL = 'http://localhost:5000/analyze';
        
        const response = await fetch(PYTHON_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(predictionData)
        });
        
        console.log('HTTP Status:', response.status);
        
        const data = await response.json();
        console.log('Full Response:', data);
        
        // Kiểm tra lỗi
        if (!response.ok || !data.success) {
            throw new Error(data.error || `HTTP Error ${response.status}`);
        }
        
        console.groupEnd();
        
        // Hide loading
        document.getElementById('aiLoading').style.display = 'none';
        
        if (data.success && data.analysis) {
            const analysis = data.analysis;
            console.log('AI Analysis:', analysis);
            console.log('Provider used:', data.provider);
            
            // Debug: Log từng phần
            console.group('Parsed Analysis');
            console.log('Trend:', analysis.trend_analysis ? 'YES' : 'NO');
            console.log('Recommendations:', analysis.recommendations ? `${analysis.recommendations.length} items` : 'NO');
            console.log('Conclusion:', analysis.conclusion ? 'YES' : 'NO');
            console.groupEnd();
            
            // CẬP NHẬT vòng tròn xác suất với dữ liệu từ AI
            if (analysis.probability) {
                const probabilityCircle = document.querySelector('.probability-circle');
                if (probabilityCircle) {
                    // Animation hiệu ứng đếm số
                    const oldValue = parseInt(probabilityCircle.textContent);
                    const newValue = parseInt(analysis.probability);
                    
                    if (oldValue !== newValue) {
                        let current = oldValue;
                        const step = (newValue - oldValue) / 20; // 20 bước animation
                        const interval = setInterval(() => {
                            current += step;
                            if ((step > 0 && current >= newValue) || (step < 0 && current <= newValue)) {
                                current = newValue;
                                clearInterval(interval);
                            }
                            probabilityCircle.textContent = Math.round(current) + '%';
                        }, 30);
                    }
                    
                    console.log(`Cập nhật xác suất: ${oldValue}% -> ${newValue}%`);
                }
            }
            
            // Hiển thị kết quả thành công
            document.getElementById('aiSuccess').style.display = 'block';
            
            // Phân tích xu hướng
            if (analysis.trend_analysis) {
                document.getElementById('aiTrendAnalysis').style.display = 'block';
                document.getElementById('trendText').innerHTML = analysis.trend_analysis.replace(/\n/g, '<br>');
            }
            
            // Lời khuyên
            if (analysis.recommendations && analysis.recommendations.length > 0) {
                document.getElementById('aiRecommendations').style.display = 'block';
                const list = document.getElementById('recommendationsList');
                list.innerHTML = '';
                analysis.recommendations.forEach(rec => {
                    const li = document.createElement('li');
                    li.innerHTML = rec;  // Dùng innerHTML để hiển thị HTML tags (<strong>, <br>, etc.)
                    list.appendChild(li);
                });
                console.log(`Hiển thị ${analysis.recommendations.length} lời khuyên`);
            } else {
                console.warn('Không có recommendations từ AI');
            }
            
            // Kết luận
            if (analysis.conclusion) {
                document.getElementById('aiConclusion').style.display = 'block';
                document.getElementById('conclusionText').innerHTML = analysis.conclusion.replace(/\n/g, '<br>');
            }
            
            // Ẩn nút sau khi thành công
            aiBtn.style.display = 'none';
            
        } else {
            throw new Error('Invalid response structure from Python API');
        }
        
    } catch (error) {
        console.error('Error:', error);
        console.groupEnd();
        
        // Hide loading, show error
        document.getElementById('aiLoading').style.display = 'none';
        document.getElementById('aiError').style.display = 'block';
        document.getElementById('errorMessage').textContent = error.message || 'Đã xảy ra lỗi khi kết nối với Python backend';
        
        // Enable lại button để retry
        aiBtn.disabled = false;
        aiBtn.style.opacity = '1';
        aiBtn.innerHTML = '<span>Thử lại</span>';
    }
}

// Tự động gọi AI khi có kết quả dự đoán
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($prediction_result)): ?>
        // Có kết quả -> Tự động gọi AI
        console.log('Tự động gọi AI để phân tích...');
        callAIAnalysis();
    <?php endif; ?>
});

// Xử lý nút phân tích AI (cho trường hợp retry thủ công)
document.addEventListener('DOMContentLoaded', function() {
    const aiBtn = document.getElementById('aiAnalysisBtn');
    if (aiBtn) {
        aiBtn.addEventListener('click', callAIAnalysis);
    }
});

// Debug console (chỉ khi có dữ liệu từ form submit)
<?php if (isset($prediction_result)): ?>
console.group('Prediction Result');
console.log('Total Score:', <?php echo $prediction_result['total_score'] ?? 0; ?>);
console.log('Probability:', <?php echo $prediction_result['pass_probability'] ?? 0; ?>);
console.log('University:', <?php echo json_encode($prediction_result['university_name'] ?? ''); ?>);
console.log('Major:', <?php echo json_encode($prediction_result['major_name'] ?? ''); ?>);
console.groupEnd();
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
