<?php
$page_title = 'Gợi ý ngành - Tuyensinh247';
require_once 'config/database.php';
require_once 'includes/header.php';
// Include AI helpers to call Groq
require_once 'config/ai_config.php';

$pdo = getDBConnection();

// Display label for each interest
$interest_labels = [
    'cong_nghe' => 'Công nghệ',
    'toan' => 'Toán',
    'kinh_doanh' => 'Kinh doanh',
    'y_te' => 'Y tế',
    'co_khi' => 'Cơ khí',
    'luat' => 'Luật',
    'nghe_thuat' => 'Nghệ thuật',
    'su_pham' => 'Sư phạm',
    'nong_nghiep' => 'Nông nghiệp'
];

$interests_map = [
    'cong_nghe' => ['công nghệ', 'công nghệ thông tin', 'công nghệ thông tin', 'tin học', 'it', 'phần mềm', 'kỹ thuật phần mềm', 'trí tuệ nhân tạo', 'ai', 'robot'],
    'toan' => ['toán', 'toan', 'toán ứng dụng', 'mathematics'],
    'kinh_doanh' => ['kinh tế', 'quản trị', 'marketing', 'kinh doanh', 'tài chính', 'kế toán', 'finance'],
    'y_te' => ['y', 'y tế', 'bác sĩ', 'dược', 'điều dưỡng', 'y học'],
    'co_khi' => ['cơ khí', 'kỹ thuật', 'kỹ thuật cơ khí', 'cơ khí chế tạo'],
    'luat' => ['luật', 'pháp lý'],
    'nghe_thuat' => ['thiết kế', 'nghệ thuật', 'truyền thông', 'mỹ thuật', 'media', 'đồ họa', 'design'],
    'su_pham' => ['sư phạm', 'giáo dục'],
    'nong_nghiep' => ['nông', 'môi trường', 'nông nghiệp']
];

// region -> array of provinces (basic list, can be extended)
$region_provinces = [
    'north' => ['Hà Nội','Hải Phòng','Quảng Ninh','Bắc Ninh','Hưng Yên','Vĩnh Phúc','Nam Định','Thái Bình','Ninh Bình','Hà Nam','Hải Dương','Hòa Bình','Sơn La','Lào Cai'],
    'central' => ['Đà Nẵng','Thừa Thiên-Huế','Quảng Nam','Quảng Ngãi','Bình Định','Phú Yên','Khánh Hòa','Nghệ An','Hà Tĩnh','Quảng Trị','Bình Thuận','Kon Tum','Gia Lai'],
    'south' => ['Hồ Chí Minh','Bình Dương','Đồng Nai','Bà Rịa-Vũng Tàu','Cần Thơ','An Giang','Đắk Lắk','Lâm Đồng','Tiền Giang','Long An','Bình Phước']
];

// Additional keywords for sports and personality to improve search matching
$sports_keywords = ['thể thao', 'thể dục', 'giáo dục thể chất', 'quản lý thể thao', 'huấn luyện viên', 'vật lý trị liệu', 'khoa học thể dục', 'sport', 'sports', 'physiotherapy'];
$personality_map = [
    'introvert' => ['nghiên cứu', 'phân tích', 'toán', 'lập trình', 'kỹ thuật', 'research', 'analyst', 'scientist'],
    'extrovert' => ['truyền thông', 'marketing', 'quản trị', 'bán hàng', 'sự kiện', 'marketing', 'public relations', 'sales', 'management'],
    'balanced' => []
];

$selected_interests = [];
$selected_region = '';
$likes_sports = false;
$personality = '';
$suggestions = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_interests = isset($_POST['interests']) ? (array)$_POST['interests'] : [];
    $selected_region = isset($_POST['region']) ? trim($_POST['region']) : '';
    $likes_sports = isset($_POST['likes_sports']) && $_POST['likes_sports'] === '1';
    $personality = isset($_POST['personality']) ? trim($_POST['personality']) : '';
    if (empty($selected_interests) && !$likes_sports && empty($personality) && empty($selected_region)) {
        $message = 'Vui lòng chọn ít nhất một sở thích hoặc tiêu chí (vùng, thể thao, phong cách) để hệ thống gợi ý ngành phù hợp.';
    } else {
        // Build keywords array
        $keywords = [];
        foreach ($selected_interests as $key) {
            if (isset($interests_map[$key])) {
                $keywords = array_merge($keywords, $interests_map[$key]);
            }
        }
        // Include sports keywords if selected
        if ($likes_sports) {
            $keywords = array_merge($keywords, $sports_keywords);
        }
        // Include personality keywords
        if (!empty($personality) && isset($personality_map[$personality])) {
            $keywords = array_merge($keywords, $personality_map[$personality]);
        }
        // unique and lowercase
        $keywords = array_values(array_unique(array_map('mb_strtolower', $keywords)));

    // Build SQL to fetch majors matching keywords; also optionally filter by region
        $where_parts = [];
        $params = [];
        $i = 0;
        foreach ($keywords as $kw) {
            $i++;
            // Use distinct placeholders for name and description to avoid PDO driver issues
            $pName = ":kw{$i}_name";
            $pDesc = ":kw{$i}_desc";
            $where_parts[] = "(LOWER(m.name) LIKE $pName OR LOWER(m.description) LIKE $pDesc)";
            $params[$pName] = '%' . $kw . '%';
            $params[$pDesc] = '%' . $kw . '%';
        }

    $where_sql = implode(' OR ', $where_parts);
        // Region filter - translate to province list
        $region_sql = '';
        if (!empty($selected_region) && isset($region_provinces[$selected_region])) {
            $provList = $region_provinces[$selected_region];
            // Build a region filter with placeholders
            $provParams = [];
            $idx = 0;
            foreach ($provList as $prov) {
                $idx++;
                $param = ":prov{$idx}";
                $provParams[] = $param;
                $params[$param] = '%' . mb_strtolower($prov) . '%';
            }
            if (!empty($provParams)) {
                $region_sql = " AND (" . implode(' OR ', array_map(function($p){ return "LOWER(u.province) LIKE $p"; }, $provParams)) . ")";
            }
        }
        if (empty($where_sql)) {
            // If no keywords but region filter present, fetch by region only
            if (!empty($region_sql)) {
                $query = "SELECT m.*, u.name as university_name, u.province, u.id as university_id FROM majors m JOIN universities u ON m.university_id = u.id WHERE 1=1" . $region_sql . " LIMIT 50";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } else {
                $message = 'Không có từ khóa để tìm kiếm. Vui lòng chọn sở thích hoặc vùng miền.';
                $rows = [];
            }
        } else {
            // Limit results for performance; we'll pick the best candidate later
            $query = "SELECT m.*, u.name as university_name, u.province, u.id as university_id FROM majors m JOIN universities u ON m.university_id = u.id WHERE ($where_sql)" . $region_sql . " LIMIT 50";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        }
        

    // Score and rank by number of keyword matches
        $results = [];
        foreach ($rows as $row) {
            $text = mb_strtolower(($row['name'] ?? '') . ' ' . ($row['description'] ?? ''));
            $score = 0;
            $matched = [];
            foreach ($keywords as $kw) {
                if (strpos($text, $kw) !== false) {
                    $score++;
                    $matched[] = $kw;
                }
            }
            // Give extra weight if university province matches region
            if (!empty($selected_region) && isset($region_provinces[$selected_region])) {
                $prov = mb_strtolower($row['province'] ?? '');
                foreach ($region_provinces[$selected_region] as $pr) {
                    if (strpos($prov, mb_strtolower($pr)) !== false) {
                        $score += 2; // bump score if matches region
                        break;
                    }
                }
            }
            // Give extra weight for sports or personality keywords
            if ($likes_sports) {
                foreach ($sports_keywords as $sk) {
                    if (strpos($text, $sk) !== false) {
                        $score += 2; // sports preference important
                        break;
                    }
                }
            }
            if (!empty($personality) && isset($personality_map[$personality])) {
                foreach ($personality_map[$personality] as $pk) {
                    if (strpos($text, $pk) !== false) {
                        $score += 1; // personality hint smaller bump
                        break;
                    }
                }
            }
            if ($score > 0) {
                // compute meta flags
                $region_match = false;
                if (!empty($selected_region) && isset($region_provinces[$selected_region])) {
                    $prov = mb_strtolower($row['province'] ?? '');
                    foreach ($region_provinces[$selected_region] as $pr) {
                        if (strpos($prov, mb_strtolower($pr)) !== false) { $region_match = true; break; }
                    }
                }
                $sports_match = false;
                foreach ($sports_keywords as $sk) { if (strpos($text,$sk) !== false) { $sports_match = true; break; } }
                $personality_match = false;
                if (!empty($personality) && isset($personality_map[$personality])) {
                    foreach ($personality_map[$personality] as $pk) { if (strpos($text,$pk) !== false) { $personality_match = true; break; } }
                }
                $results[] = ['row' => $row, 'score' => $score, 'matched' => $matched, 'meta' => ['region' => $region_match, 'sports' => $sports_match, 'personality' => $personality_match]];
            }
        }

        usort($results, function($a, $b) {
            if ($b['score'] === $a['score']) return strcmp($a['row']['name'], $b['row']['name']);
            return $b['score'] - $a['score'];
        });

        $suggestions = $results;

        // If we have suggestions, pick only the top one and request a detailed recommendation from Groq
        $ai_response = null;
    if (!empty($suggestions)) {
            $top = $suggestions[0];
            $row = $top['row'];
            $matchedKeywords = implode(', ', $top['matched']);
            $interestLabels = array_map(function($k) use ($interest_labels) { return $interest_labels[$k] ?? $k; }, $selected_interests);
            $interestLabelsStr = implode(', ', $interestLabels);

            // Build a detailed prompt for Groq requesting a single recommendation
            $prompt = "Bạn là trợ lý tuyển sinh đại học chuyên nghiệp. Dựa trên thông tin ngành, mô tả và sở thích của học sinh, hãy đưa ra MỘT khuyến nghị duy nhất (1 ngành) và phân tích rất chi tiết (200-400 từ) bao gồm: lý do phù hợp (năng lực, sở thích), mô tả nghề nghiệp và triển vọng, rủi ro/challenges, hành động mà học sinh cần làm để chuẩn bị (kỹ năng học tập, chứng chỉ, hoạt động ngoại khóa), và gợi ý nguyện vọng dự phòng nếu cần.\n\n";
            $prompt .= "Dữ liệu: Ngành: " . ($row['name'] ?? '') . " (Mã: " . ($row['code'] ?? '') . ")\n";
            $prompt .= "Trường: " . ($row['university_name'] ?? '') . "\n";
            $prompt .= "Mô tả: " . (mb_substr($row['description'] ?? '', 0, 1200)) . "\n";
            $prompt .= "Các từ khóa matched: " . $matchedKeywords . "\n";
            $prompt .= "Sở thích học sinh (tag): " . $interestLabelsStr . "\n";
            if (!empty($selected_region)) {
                $prompt .= "Vùng miền mong muốn: " . ($selected_region === 'north' ? 'Bắc' : ($selected_region === 'central' ? 'Trung' : 'Nam')) . "\n";
            }
            $prompt .= "Sở thích thể thao: " . ($likes_sports ? 'Có' : 'Không') . "\n";
            $pMapLabel = ['introvert' => 'Hướng nội', 'extrovert' => 'Hướng ngoại', 'balanced' => 'Cân bằng'];
            $prompt .= "Phong cách cá nhân: " . (!empty($personality) ? ($pMapLabel[$personality] ?? $personality) : 'Không rõ') . "\n\n";
            $prompt .= "Yêu cầu: Trả lời duy nhất một ngành, sử dụng ngôn ngữ rõ ràng, nhiệt huyết, và không liệt kê nhiều ngành. Không giới thiệu AI hoặc quá nhiều cách dẫn dắt. Cứ nêu ra khuyến nghị và phân tích sâu. Nếu không có đủ dữ liệu, hãy nhấn mạnh các thông tin cần bổ sung. Trả về văn bản thuần. Cảm ơn.";

            $aiRes = callGroqAPI($prompt);
            if (is_array($aiRes) && isset($aiRes['success']) && $aiRes['success']) {
                $ai_response = $aiRes['content'] ?? null;
            } else {
                $ai_response = null; // fallback handled later
            }
            // For UI, reduce suggestions to top 1 (we keep the array form for compatibility)
            $suggestions = array_slice($suggestions, 0, 1);
    } else if (!empty($keywords)) {
            // No DB matches found, ask Groq to suggest 1 major name + reasons based on interests
            $interestLabels = array_map(function($k) use ($interest_labels) { return $interest_labels[$k] ?? $k; }, $selected_interests);
            $interestLabelsStr = implode(', ', $interestLabels);

            $prompt = "Bạn là chuyên gia tư vấn ngành học. Thí sinh có sở thích: " . $interestLabelsStr . ".";
            if (!empty($selected_region)) $prompt .= " Vùng miền mong muốn: " . ($selected_region === 'north' ? 'Bắc' : ($selected_region === 'central' ? 'Trung' : 'Nam')) . ".";
            $pMapLabel = ['introvert' => 'Hướng nội', 'extrovert' => 'Hướng ngoại', 'balanced' => 'Cân bằng'];
            $prompt .= " Sở thích thể thao: " . ($likes_sports ? 'Có' : 'Không') . ". Phong cách cá nhân: " . (!empty($personality) ? ($pMapLabel[$personality] ?? $personality) : 'Không rõ') . ".";
            $prompt .= " Hãy gợi ý MỘT ngành phù hợp nhất, kèm mô tả ngắn (tối đa 120 từ), lý do phù hợp, kỹ năng cần chuẩn bị và 3 hành động cụ thể để ứng viên chuẩn bị. Trả lời ngay, không liệt kê nhiều ngành.";
            $aiRes = callGroqAPI($prompt);
            if (is_array($aiRes) && isset($aiRes['success']) && $aiRes['success']) {
                $ai_response = $aiRes['content'] ?? null;
            } else {
                $ai_response = null;
            }
            // Build a fake suggestion entry so the front-end can show the AI suggestion box
            $suggestions = [
                ['row' => ['name' => 'Gợi ý AI: Xem chi tiết', 'code' => '', 'description' => 'AI gợi ý 1 ngành dựa trên sở thích', 'university_name' => 'Không có dữ liệu trường'], 'score' => 0, 'matched' => []]
            ];
        } else if (!empty($selected_region)) {
            // No keywords but region preference provided, ask AI for recommendations for that region
            $interestLabels = array_map(function($k) use ($interest_labels) { return $interest_labels[$k] ?? $k; }, $selected_interests);
            $interestLabelsStr = implode(', ', $interestLabels);
            $regionName = ($selected_region === 'north' ? 'Bắc' : ($selected_region === 'central' ? 'Trung' : 'Nam'));
            $prompt = "Bạn là chuyên gia tư vấn ngành học. Thí sinh ưu tiên vùng miền: " . $regionName . ". ";
            if (!empty($interestLabelsStr)) $prompt .= "Sở thích: " . $interestLabelsStr . ". ";
            $pMapLabel = ['introvert' => 'Hướng nội', 'extrovert' => 'Hướng ngoại', 'balanced' => 'Cân bằng'];
            $prompt .= "Sở thích thể thao: " . ($likes_sports ? 'Có' : 'Không') . ". Phong cách cá nhân: " . (!empty($personality) ? ($pMapLabel[$personality] ?? $personality) : 'Không rõ') . ". ";
            $prompt .= "Hãy gợi ý MỘT ngành phù hợp cho thí sinh trong vùng này, kèm mô tả ngắn (tối đa 120 từ), lý do phù hợp và 3 hành động cụ thể để ứng viên chuẩn bị.";
            $aiRes = callGroqAPI($prompt);
            if (is_array($aiRes) && isset($aiRes['success']) && $aiRes['success']) {
                $ai_response = $aiRes['content'] ?? null;
            } else {
                $ai_response = null;
            }
            $suggestions = [ ['row' => ['name' => 'Gợi ý AI: Xem chi tiết', 'code' => '', 'description' => 'AI gợi ý 1 ngành dựa trên vùng miền', 'university_name' => 'Không có dữ liệu trường'], 'score' => 0, 'matched' => []] ];
        }
    }
}

// Render page
?>
<style>
    .suggest-container { max-width: 1100px; margin: 40px auto; }
    .interest-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:12px; }
    .interest-card { position:relative; display:block; width:100%; padding:0; border-radius:10px; box-shadow: none; overflow:hidden; box-sizing:border-box; cursor:pointer; }
    /* Make checkbox invisible but focusable and clickable by overlaying it; keep accessible */
    .interest-card input[type=checkbox] { position:absolute; left:0; top:0; width:100%; height:100%; opacity:0; z-index:5; cursor:pointer; }
    .interest-card .card-inner { background: white; padding:14px; border-radius:10px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); position:relative; transition: all 0.18s ease; cursor:pointer; z-index:1; }
    /* Hide the small corner square completely */
    .interest-card .card-inner .card-inner-head { display:none; }
    .interest-card .card-inner .card-inner-head::after { content:''; display:none; }
    /* Checked state styles */
    .interest-card input[type=checkbox]:checked + .card-inner { background: linear-gradient(135deg, #e6f7f7 0%, #ffffff 100%); border: 1px solid #13a0a0; box-shadow: 0 8px 20px rgba(19,160,160,0.08); }
    .interest-card input[type=checkbox]:checked + .card-inner .card-inner-head { background: #13a0a0; border-color: #13a0a0; }
    .interest-card input[type=checkbox]:checked + .card-inner .card-inner-head::after { display:none; }
    .interest-card .card-inner:hover, .interest-card:hover .card-inner { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.08); }
    /* Visual focus when using keyboard */
    .interest-card input[type=checkbox]:focus + .card-inner { outline: 3px solid rgba(19,160,160,0.14); outline-offset: 2px; }
    .suggest-list { margin-top: 20px; }
    .suggest-item { background:white; padding:14px; border-radius:10px; margin-bottom:10px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); }
    .match-badge { display:inline-block; background:#e8f8f8; color:#087f8a; padding:6px 8px; border-radius:999px; font-weight:700; font-size:0.85rem; margin-right:8px; }
    .ai-result { border: 1px solid #e6eef0; box-shadow: 0 6px 14px rgba(14,86,96,0.06); }
    .ai-result h4 { margin:0 0 8px; }
</style>

<div class="container suggest-container">
    <h1>Gợi ý ngành theo sở thích</h1>
    <p>Chọn các sở thích của bạn, hệ thống sẽ gợi ý ngành phù hợp dựa trên tên ngành và mô tả hiện có.</p>

    <?php if (!empty($message)): ?>
        <div class="alert alert-error"><?php echo escape($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="interest-grid">
            <?php foreach ($interests_map as $key => $vals): ?>
                <label class="interest-card">
                    <input type="checkbox" name="interests[]" value="<?php echo escape($key); ?>" <?php echo in_array($key, $selected_interests) ? 'checked' : ''; ?>>
                    <div class="card-inner">
                        <div class="card-inner-head" aria-hidden="true"></div>
                        <strong style="display:block; margin-top:8px;" class="card-title"><?php echo escape($interest_labels[$key] ?? str_replace('_', ' ', $key)); ?></strong>
                        <small style="color:#666; display:block; margin-top:6px;" class="card-sub">Keywords: <?php echo escape(implode(', ', $vals)); ?></small>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <div style="display:flex; gap:12px; align-items:center; margin-top:16px; flex-wrap:wrap;">
            <div style="flex:1; min-width:220px;">
                <label for="region"><strong>Vùng miền mong muốn</strong></label>
                <select id="region" name="region" class="form-control">
                    <option value="">-- Bất kỳ --</option>
                    <option value="north" <?php echo ($selected_region === 'north') ? 'selected' : '';?> >Bắc</option>
                    <option value="central" <?php echo ($selected_region === 'central') ? 'selected' : '';?> >Trung</option>
                    <option value="south" <?php echo ($selected_region === 'south') ? 'selected' : '';?> >Nam</option>
                </select>
            </div>

            <div style="min-width:220px;">
                <label style="display:block; font-weight:700;">Sở thích thể thao</label>
                <label style="display:inline-block; margin-right:8px;">
                    <input type="checkbox" name="likes_sports" value="1" <?php echo $likes_sports ? 'checked' : ''; ?>> Thích thể thao
                </label>
            </div>

            <div style="min-width:220px;">
                <label style="display:block; font-weight:700;">Người hướng </label>
                <label style="margin-right:10px;"><input type="radio" name="personality" value="introvert" <?php echo ($personality === 'introvert') ? 'checked' : ''; ?>> Hướng nội</label>
                <label style="margin-right:10px;"><input type="radio" name="personality" value="extrovert" <?php echo ($personality === 'extrovert') ? 'checked' : ''; ?>> Hướng ngoại</label>
                <label><input type="radio" name="personality" value="balanced" <?php echo ($personality === 'balanced') ? 'checked' : ''; ?>> Cân bằng</label>
            </div>
        </div>

        <div style="margin-top:16px; text-align:center;">
            <button type="submit" class="btn btn-predict">Gợi ý ngành</button>
        </div>
    </form>

    <div class="suggest-list">
        <?php if (!empty($selected_region) || $likes_sports || !empty($personality)): ?>
            <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
                <?php if (!empty($selected_region)): ?>
                    <div class="match-badge">Vùng: <?php echo escape(($selected_region === 'north') ? 'Bắc' : (($selected_region === 'central') ? 'Trung' : 'Nam')); ?></div>
                <?php endif; ?>
                <?php if ($likes_sports): ?>
                    <div class="match-badge">Thích thể thao</div>
                <?php endif; ?>
                <?php if (!empty($personality)): ?>
                    <?php $pMap = ['introvert' => 'Hướng nội', 'extrovert' => 'Hướng ngoại', 'balanced' => 'Cân bằng']; ?>
                    <div class="match-badge">Phong cách: <?php echo escape($pMap[$personality] ?? $personality); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($suggestions)): ?>
            <h2>Gợi ý cho bạn</h2>
            <?php foreach ($suggestions as $s): $row = $s['row']; ?>
                <div class="suggest-item">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div style="font-weight:700; font-size:1.05rem;"><?php echo escape($row['name']); ?> <small style="font-weight:600; color:#666;">(<?php echo escape($row['code']); ?>)</small></div>
                            <div style="color:#666; font-size:0.95rem;">Trường: <?php echo escape($row['university_name']); ?></div>
                        </div>
                        <div style="text-align:right;">
                            <a class="result-btn result-btn-secondary" href="university.php?id=<?php echo $row['university_id']; ?>">Xem trường</a>
                        </div>
                    </div>
                    <div style="margin-top:10px; color:#444;"><?php echo nl2br(escape(substr($row['description'] ?? '', 0, 300))); ?><?php echo (strlen($row['description'] ?? '') > 300) ? '...' : ''; ?></div>
                    <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <span class="match-badge">Khớp <?php echo intval($s['score']); ?> yếu tố</span>
                        <span style="color:#666;">Từ khóa trùng: <?php echo escape(implode(', ', $s['matched'])); ?></span>
                        <?php if (isset($s['meta']) && !empty($s['meta']['region'])): ?><span class="match-badge">Vùng trùng</span><?php endif; ?>
                        <?php if (isset($s['meta']) && !empty($s['meta']['sports'])): ?><span class="match-badge">Thể thao</span><?php endif; ?>
                        <?php if (isset($s['meta']) && !empty($s['meta']['personality'])): ?><span class="match-badge">Phong cách phù hợp</span><?php endif; ?>
                    </div>
                    <?php if (!empty($ai_response)): ?>
                        <div class="ai-result" style="margin-top:12px; background:linear-gradient(180deg,#fff,#f8f9fa); padding:12px; border-radius:8px;">
                            <div style="font-weight:700; color:#0e5660; margin-bottom:8px;">Phân tích chi tiết (AI):</div>
                            <div style="color:#333; line-height:1.6;"><?php echo nl2br(escape($ai_response)); ?></div>
                        </div>
                    <?php else: ?>
                        <div style="margin-top:12px;">
                            <em>AI không thể đưa ra phân tích chi tiết vào lúc này. Bạn vẫn có thể tham khảo thông tin ngành và các gợi ý ở trên.</em>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($message)): ?>
            <div class="alert alert-info">Không tìm thấy ngành phù hợp với lựa chọn của bạn. Thử chọn thêm sở thích khác hoặc mở rộng phạm vi.</div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
