<?php
require_once 'config/database.php';

// Xử lý tìm kiếm theo điểm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$university_type = isset($_GET['university_type']) ? trim($_GET['university_type']) : '';
$major_name = isset($_GET['major_name']) ? trim($_GET['major_name']) : '';
$min_score = isset($_GET['min_score']) ? (float)$_GET['min_score'] : 0;
$max_score = isset($_GET['max_score']) ? (float)$_GET['max_score'] : 30;

$pdo = getDBConnection();

// Lấy danh sách tỉnh thành
$provinces_query = "SELECT DISTINCT province FROM universities ORDER BY province";
$provinces = $pdo->query($provinces_query)->fetchAll();

// Lấy danh sách năm có dữ liệu
$years_query = "SELECT DISTINCT year FROM admission_scores ORDER BY year DESC";
$years = $pdo->query($years_query)->fetchAll();
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)$years[0]['year'];

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Query tìm theo điểm
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE :search_name OR u.code LIKE :search_code)";
    $params[':search_name'] = "%$search%";
    $params[':search_code'] = "%$search%";
}

if (!empty($province)) {
    $where_conditions[] = "u.province = :province";
    $params[':province'] = $province;
}

if (!empty($university_type)) {
    $where_conditions[] = "u.university_type = :university_type";
    $params[':university_type'] = $university_type;
}

if (!empty($major_name)) {
    $where_conditions[] = "m.name LIKE :major_name";
    $params[':major_name'] = "%$major_name%";
}

if ($min_score > 0) {
    $where_conditions[] = "a.min_score >= :min_score";
    $params[':min_score'] = $min_score;
}

if ($max_score > 0 && $max_score < 30) {
    $where_conditions[] = "a.min_score <= :max_score";
    $params[':max_score'] = $max_score;
}

// Luôn filter theo năm
$where_conditions[] = "a.year = $year";

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Đếm tổng số kết quả
$count_query = "
    SELECT COUNT(DISTINCT CONCAT(u.id, '-', m.id)) as total
    FROM universities u
    INNER JOIN majors m ON u.id = m.university_id
    INNER JOIN admission_scores a ON m.id = a.major_id
    $where_clause
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_results = $count_stmt->fetch()['total'];
$total_pages = ceil($total_results / $limit);

// Lấy kết quả tìm kiếm
$query = "
    SELECT 
        u.id as university_id,
        u.name as university_name,
        u.code as university_code,
        u.province,
        u.university_type,
        m.id as major_id,
        m.name as major_name,
        m.code as major_code,
        a.year,
        a.block,
        a.min_score,
        a.quota
    FROM universities u
    INNER JOIN majors m ON u.id = m.university_id
    INNER JOIN admission_scores a ON m.id = a.major_id
    $where_clause
    ORDER BY a.min_score DESC, u.name, m.name
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

include 'includes/header.php';
?>

<style>
    <?php include 'assets/css/style.css'; ?>
    
    /* Form Search Mới - Layout cân đối */
    .search-form-new {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    /* Hàng 1: 3 dropdown cân bằng */
    .form-row-filters {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-bottom: 15px;
    }
    
    /* Hàng 2: Search + Button + Score range */
    .form-row-search {
        display: grid;
        grid-template-columns: 1fr auto auto;
        gap: 15px;
        align-items: center;
    }
    
    /* Select dropdown */
    .form-select {
        width: 100%;
        padding: 14px 16px;
        font-size: 1rem;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        color: #333;
    }
    
    .form-select:hover {
        border-color: #c87400;
        background: #fff8f0;
    }
    
    .form-select:focus {
        outline: none;
        border-color: #c87400;
        box-shadow: 0 0 0 3px rgba(200,116,0,0.1);
    }
    
    /* Search input */
    .search-box-wrapper {
        width: 100%;
    }
    
    .form-input-search {
        width: 100%;
        padding: 14px 16px;
        font-size: 1rem;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    
    .form-input-search:focus {
        outline: none;
        border-color: #c87400;
        box-shadow: 0 0 0 3px rgba(200,116,0,0.1);
    }
    
    /* Button tìm kiếm */
    .btn-search {
        padding: 14px 35px;
        background: linear-gradient(135deg, #c87400 0%, #e67e22 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        box-shadow: 0 4px 12px rgba(200,116,0,0.25);
    }
    
    .btn-search:hover {
        background: linear-gradient(135deg, #a85f00 0%, #d35400 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(200,116,0,0.35);
    }
    
    .btn-search:active {
        transform: translateY(0);
    }
    
    /* Score range wrapper */
    .score-range-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        white-space: nowrap;
    }
    
    .form-input-score {
        width: 80px;
        padding: 14px 12px;
        font-size: 1rem;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .form-input-score:focus {
        outline: none;
        border-color: #c87400;
        box-shadow: 0 0 0 3px rgba(200,116,0,0.1);
    }
    
    .score-dash {
        font-weight: 600;
        color: #666;
        font-size: 1.2rem;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .form-row-search {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .score-range-wrapper {
            justify-content: center;
        }
        
        .btn-search {
            width: 100%;
        }
    }
    
    @media (max-width: 768px) {
        .form-row-filters {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container mt-80">
    <div class="search-container">
        <div class="search-tabs">
            <a href="search_score.php" class="search-tab active">Tìm theo điểm</a>
            <a href="search_university.php" class="search-tab">Tìm theo trường</a>
            <a href="search_major.php" class="search-tab">Tìm theo ngành</a>
        </div>

        <form method="GET" id="searchForm" class="search-form-new">
            <!-- Hàng 1: 3 dropdown filters -->
            <div class="form-row-filters">
                <select name="province" class="form-select">
                    <option value="">Tất cả tỉnh/TP</option>
                    <?php foreach ($provinces as $p): ?>
                        <option value="<?php echo escape($p['province']); ?>" 
                                <?php echo $province === $p['province'] ? 'selected' : ''; ?>>
                            <?php echo escape($p['province']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="university_type" class="form-select">
                    <option value="">Tất cả loại hình</option>
                    <option value="Công lập" <?php echo $university_type === 'Công lập' ? 'selected' : ''; ?>>Công lập</option>
                    <option value="Dân lập" <?php echo $university_type === 'Dân lập' ? 'selected' : ''; ?>>Dân lập</option>
                    <option value="Tư thục" <?php echo $university_type === 'Tư thục' ? 'selected' : ''; ?>>Tư thục</option>
                </select>

                <select name="year" class="form-select">
                    <?php foreach ($years as $y): ?>
                        <option value="<?php echo $y['year']; ?>" 
                                <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                            Năm <?php echo $y['year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Hàng 2: Search box + Button + Score range -->
            <div class="form-row-search">
                <div class="search-box-wrapper">
                    <input type="text" 
                           name="major_name" 
                           value="<?php echo escape($major_name); ?>"
                           placeholder="Nhập tên ngành hoặc tên trường..."
                           class="form-input-search">
                </div>

                <button type="submit" class="btn-search">
                    Tìm kiếm
                </button>

                <div class="score-range-wrapper">
                    <input type="number" 
                           name="min_score" 
                           value="<?php echo $min_score; ?>"
                           placeholder="0"
                           min="0"
                           max="30"
                           step="0.5"
                           class="form-input-score">
                    <span class="score-dash">-</span>
                    <input type="number" 
                           name="max_score" 
                           value="<?php echo $max_score; ?>"
                           placeholder="30"
                           min="0"
                           max="30"
                           step="0.5"
                           class="form-input-score">
                </div>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'GET'): ?>
            <div class="results-summary">
                Tìm thấy <strong><?php echo formatNumber($total_results); ?></strong> ngành học với điểm chuẩn từ <?php echo $min_score; ?> đến <?php echo $max_score; ?>
            </div>            <style>
                /* Styles cho result cards */
                .result-card {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 16px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    transition: all 0.3s ease;
                }
                .result-card:hover {
                    box-shadow: 0 4px 16px rgba(200,116,0,0.15);
                    transform: translateY(-2px);
                }
                .result-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 15px;
                    padding-bottom: 15px;
                    border-bottom: 1px solid #eee;
                }
                .result-major {
                    flex: 1;
                }
                .result-major-name {
                    font-size: 1.3rem;
                    font-weight: 600;
                    color: #2c3e50;
                    margin-bottom: 8px;
                }
                .result-university {
                    color: #666;
                    font-size: 0.95rem;
                }
                .result-score {
                    text-align: center;
                    min-width: 100px;
                }
                .result-score-value {
                    font-size: 2rem;
                    font-weight: 700;
                    color: #c87400;
                }
                .result-score-label {
                    font-size: 0.85rem;
                    color: #999;
                    margin-top: 4px;
                }
                .result-details {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 12px;
                    margin-bottom: 15px;
                }
                .result-detail-item {
                    font-size: 0.9rem;
                    color: #555;
                }
                .result-detail-label {
                    font-weight: 600;
                    color: #666;
                    margin-right: 6px;
                }
                .result-actions {
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .result-btn {
                    padding: 10px 20px;
                    border-radius: 8px;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 0.9rem;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                }
                .result-btn-primary {
                    background: linear-gradient(135deg, #c87400 0%, #e67e22 100%);
                    color: white;
                }
                .result-btn-primary:hover {
                    background: linear-gradient(135deg, #a85f00 0%, #d35400 100%);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(200,116,0,0.3);
                }
                .result-btn-secondary {
                    background: white;
                    color: #c87400;
                    border: 2px solid #c87400;
                }
                .result-btn-secondary:hover {
                    background: #c87400;
                    color: white;
                }

                /* Score range slider */
                .score-range-container {
                    margin: 20px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 12px;
                }
                .score-range-label {
                    display: block;
                    font-weight: 600;
                    color: #2c3e50;
                    margin-bottom: 15px;
                    font-size: 1.05rem;
                }
                #minScoreDisplay, #maxScoreDisplay {
                    color: #c87400;
                    font-size: 1.2rem;
                }
                .score-range-slider {
                    position: relative;
                    height: 40px;
                    margin-bottom: 10px;
                }
                .range-input {
                    position: absolute;
                    width: 100%;
                    height: 5px;
                    -webkit-appearance: none;
                    appearance: none;
                    background: transparent;
                    pointer-events: none;
                }
                .range-input::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    background: #c87400;
                    cursor: pointer;
                    pointer-events: all;
                    border: 3px solid white;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
                }
                .range-input::-moz-range-thumb {
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    background: #c87400;
                    cursor: pointer;
                    pointer-events: all;
                    border: 3px solid white;
                    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
                }
                .score-range-bar {
                    height: 5px;
                    background: linear-gradient(to right, #27ae60, #f39c12, #e74c3c);
                    border-radius: 5px;
                    position: relative;
                }
                .score-range-fill {
                    position: absolute;
                    height: 100%;
                    background: rgba(255,255,255,0.3);
                    border-radius: 5px;
                }
            </style>

            <!-- Hiển thị kết quả -->
            <?php foreach ($results as $result): ?>
                <div class="result-card">
                    <div class="result-header">
                        <div class="result-major">
                            <div class="result-major-name"><?php echo escape($result['major_name'] ?? ''); ?></div>
                            <div class="result-university">
                                <?php echo escape($result['university_name'] ?? ''); ?> 
                                <span class="text-muted">(<?php echo escape($result['university_code'] ?? ''); ?>)</span>
                            </div>
                        </div>
                        <div class="result-score">
                            <div class="result-score-value"><?php echo formatScore($result['min_score'] ?? 0); ?></div>
                            <div class="result-score-label">Điểm chuẩn</div>
                        </div>
                    </div>
                    
                    <div class="result-details">
                        <div class="result-detail-item">
                            <span class="result-detail-label">Tỉnh/TP:</span>
                            <span><?php echo escape($result['province'] ?? ''); ?></span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Mã ngành:</span>
                            <span><?php echo escape($result['major_code'] ?? ''); ?></span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Khối thi:</span>
                            <span class="major-code"><?php echo escape($result['block'] ?? ''); ?></span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Chỉ tiêu:</span>
                            <span><?php echo formatNumber($result['quota'] ?? 0); ?> sinh viên</span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Loại:</span>
                            <span><?php echo escape($result['university_type'] ?? ''); ?></span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Năm:</span>
                            <span><?php echo escape($result['year'] ?? ''); ?></span>
                        </div>
                    </div>
                    
                    <div class="result-actions">
                        <a href="university.php?id=<?php echo $result['university_id'] ?? 0; ?>" 
                           class="result-btn result-btn-primary">
                            Xem chi tiết trường
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">« Trước</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">Sau »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-submit form when filters change
document.querySelectorAll('#searchForm select').forEach(select => {
    select.addEventListener('change', function() {
        document.getElementById('searchForm').submit();
    });
});

// Show loading indicator on form submit
document.getElementById('searchForm').addEventListener('submit', function() {
    const button = document.querySelector('.search-button');
    if (button) {
        button.textContent = 'Đang tìm...';
        button.disabled = true;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
