<?php
require_once 'config/database.php';

// Xử lý tìm kiếm trường
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$university_type = isset($_GET['university_type']) ? trim($_GET['university_type']) : '';

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

// Query tìm trường
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

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Đếm tổng số trường
$count_query = "SELECT COUNT(*) as total FROM universities u $where_clause";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_results = $count_stmt->fetch()['total'];
$total_pages = ceil($total_results / $limit);

// Lấy danh sách trường với số ngành
$query = "
    SELECT 
        u.*,
        COUNT(DISTINCT m.id) as major_count,
        AVG(a.min_score) as avg_score
    FROM universities u
    LEFT JOIN majors m ON u.id = m.university_id
    LEFT JOIN admission_scores a ON m.id = a.major_id AND a.year = $year
    $where_clause
    GROUP BY u.id
    ORDER BY u.name
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
    
    /* Hàng 2: Search + Button */
    .form-row-search {
        display: grid;
        grid-template-columns: 1fr auto;
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
    
    /* Responsive */
    @media (max-width: 1024px) {
        .form-row-search {
            grid-template-columns: 1fr;
            gap: 12px;
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
            <a href="search_score.php" class="search-tab">Tìm theo điểm</a>
            <a href="search_university.php" class="search-tab active">Tìm theo trường</a>
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

            <!-- Hàng 2: Search box + Button -->
            <div class="form-row-search">
                <div class="search-box-wrapper">
                    <input type="text" 
                           name="search" 
                           value="<?php echo escape($search); ?>"
                           placeholder="Tìm trường: nhập tên trường, mã trường..."
                           class="form-input-search">
                </div>

                <button type="submit" class="btn-search">
                    Tìm kiếm
                </button>
            </div>
        </form>

            <!-- Province pills -->
            <div class="province-pills">
                <span class="pill-label">Nhanh:</span>
                <a href="?province=Hà Nội" class="province-pill <?php echo $province === 'Hà Nội' ? 'active' : ''; ?>">Hà Nội</a>
                <a href="?province=TP Hồ Chí Minh" class="province-pill <?php echo $province === 'TP Hồ Chí Minh' ? 'active' : ''; ?>">TP HCM</a>
                <a href="?province=Đà Nẵng" class="province-pill <?php echo $province === 'Đà Nẵng' ? 'active' : ''; ?>">Đà Nẵng</a>
                <a href="?province=Cần Thơ" class="province-pill <?php echo $province === 'Cần Thơ' ? 'active' : ''; ?>">Cần Thơ</a>
                <a href="?province=Thừa Thiên Huế" class="province-pill <?php echo $province === 'Thừa Thiên Huế' ? 'active' : ''; ?>">Huế</a>
                <?php if (!empty($province)): ?>
                    <a href="?" class="province-pill btn-clear-filter">X Xóa bộ lọc</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['search']) || isset($_GET['province']) || isset($_GET['university_type']))): ?>
            <div class="results-summary">
                Tìm thấy <strong><?php echo formatNumber($total_results); ?></strong> trường đại học
            </div>

            <style>
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
            </style>

            <!-- Hiển thị kết quả -->
            <?php foreach ($results as $result): ?>
                <div class="result-card">
                    <div class="result-header">
                        <div class="result-major">
                            <div class="result-major-name"><?php echo escape($result['name'] ?? ''); ?></div>
                            <div class="result-university">
                                Mã trường: <span class="text-muted"><?php echo escape($result['code'] ?? ''); ?>)</span>
                            </div>
                        </div>
                        <div class="result-score">
                            <div class="result-score-value"><?php echo formatNumber($result['major_count'] ?? 0); ?></div>
                            <div class="result-score-label">Ngành đào tạo</div>
                        </div>
                    </div>
                    
                    <div class="result-details">
                        <div class="result-detail-item">
                            <span class="result-detail-label">Tỉnh/TP:</span>
                            <span><?php echo escape($result['province'] ?? ''); ?></span>
                        </div>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Loại hình:</span>
                            <span><?php echo escape($result['university_type'] ?? ''); ?></span>
                        </div>
                        <?php if (!empty($result['avg_score'])): ?>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Điểm TB:</span>
                            <span class="text-orange"><?php echo formatScore($result['avg_score']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($result['established_year'])): ?>
                        <div class="result-detail-item">
                            <span class="result-detail-label">Thành lập:</span>
                            <span><?php echo $result['established_year']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="result-actions">
                        <a href="university.php?id=<?php echo $result['id'] ?? 0; ?>" 
                           class="result-btn result-btn-primary">
                            Xem chi tiết trường
                        </a>
                        <?php if (!empty($result['website'])): ?>
                        <a href="<?php echo escape($result['website']); ?>" target="_blank"
                           class="result-btn result-btn-secondary">
                            Website
                        </a>
                        <?php endif; ?>
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
</script>

<?php include 'includes/footer.php'; ?>
