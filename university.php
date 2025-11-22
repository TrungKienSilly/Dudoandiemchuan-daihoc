<?php
require_once 'config/database.php';

$university_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$university_id) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

// Lấy thông tin trường đại học
$university_query = "SELECT * FROM universities WHERE id = :id";
$university_stmt = $pdo->prepare($university_query);
$university_stmt->execute([':id' => $university_id]);
$university = $university_stmt->fetch();

if (!$university) {
    header('Location: index.php');
    exit;
}

// Phân trang cho danh sách ngành
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Hiển thị 5 ngành mỗi trang
$offset = ($page - 1) * $limit;

// Đếm tổng số ngành
$count_query = "SELECT COUNT(*) as total FROM majors WHERE university_id = :university_id";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute([':university_id' => $university_id]);
$total_majors = $count_stmt->fetch()['total'];
$total_pages = ceil($total_majors / $limit);

// Lấy danh sách ngành đào tạo (có phân trang)
$majors_query = "
    SELECT 
        m.*,
        COUNT(a.id) as score_count,
        AVG(a.min_score) as avg_score,
        MIN(a.min_score) as min_score,
        MAX(a.min_score) as max_score
    FROM majors m
    LEFT JOIN admission_scores a ON m.id = a.major_id AND a.year = YEAR(CURDATE())
    WHERE m.university_id = :university_id
    GROUP BY m.id
    ORDER BY m.name
    LIMIT :limit OFFSET :offset
";
$majors_stmt = $pdo->prepare($majors_query);
$majors_stmt->bindValue(':university_id', $university_id, PDO::PARAM_INT);
$majors_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$majors_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$majors_stmt->execute();
$majors = $majors_stmt->fetchAll();

// Lấy điểm chuẩn chi tiết cho ngành được chọn
$selected_major_id = isset($_GET['major_id']) ? (int)$_GET['major_id'] : 0;
$scores = [];
if ($selected_major_id) {
    $scores_query = "
        SELECT * FROM admission_scores 
        WHERE major_id = :major_id 
        ORDER BY year DESC, block
    ";
    $scores_stmt = $pdo->prepare($scores_query);
    $scores_stmt->execute([':major_id' => $selected_major_id]);
    $scores = $scores_stmt->fetchAll();
}

$page_title = escape($university['name']) . ' - Thông tin tuyển sinh';
include 'includes/header.php';
?>

<style>
    /* Ẩn banner cho trang này */
    .header {
        display: none !important;
    }
    
    /* Container */
    .detail-container {
        max-width: 1200px;
        margin: 80px auto 40px;
        padding: 0 20px;
    }
    
    /* University Header Card */
    .university-detail-card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 30px;
    }
    
    .university-detail-header {
        background: linear-gradient(135deg, #c87400 0%, #e67e22 100%);
        color: white;
        padding: 25px 30px;
        text-align: center;
    }
    
    .university-detail-name {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 8px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
    }
    
    .university-detail-code {
        font-size: 0.95rem;
        opacity: 0.95;
        font-weight: 500;
        background: rgba(255,255,255,0.2);
        display: inline-block;
        padding: 6px 16px;
        border-radius: 20px;
    }
    
    .university-detail-body {
        padding: 35px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }
    
    .info-card {
        background: #f8f9fa;
        padding: 18px;
        border-radius: 10px;
        border-left: 4px solid #c87400;
        transition: all 0.3s ease;
    }
    
    .info-card:hover {
        background: #fff8f0;
        transform: translateX(5px);
        box-shadow: 0 2px 10px rgba(200,116,0,0.1);
    }
    
    .info-card-label {
        font-weight: 600;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: block;
    }
    
    .info-card-value {
        color: #333;
        font-size: 1.05rem;
    }
    
    .info-card-value a {
        color: #c87400;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    
    .info-card-value a:hover {
        color: #a85f00;
        text-decoration: underline;
    }
    
    .university-description {
        margin-top: 25px;
        padding-top: 25px;
        border-top: 2px solid #e9ecef;
    }
    
    .university-description h3 {
        color: #333;
        margin-bottom: 15px;
        font-size: 1.4rem;
        font-weight: 600;
    }
    
    .university-description p {
        line-height: 1.8;
        color: #555;
        font-size: 1rem;
    }
    
    /* Majors Section */
    .majors-section {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .majors-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
    }
    
    .majors-header h2 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 600;
    }
    
    .major-card {
        padding: 25px 30px;
        border-bottom: 1px solid #e9ecef;
        transition: all 0.3s ease;
    }
    
    .major-card:last-child {
        border-bottom: none;
    }
    
    .major-card:hover {
        background: #f8f9fa;
    }
    
    .major-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .major-card-name {
        font-size: 1.3rem;
        font-weight: 600;
        color: #2c3e50;
    }
    
    .major-card-code {
        background: #667eea;
        color: white;
        padding: 6px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .major-card-info {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 15px;
    }
    
    .major-info-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        color: #555;
    }
    
    .major-info-item strong {
        color: #333;
    }
    
    .major-info-item .score-badge {
        background: #c87400;
        color: white;
        padding: 4px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .btn-view-score {
        display: inline-block;
        padding: 12px 25px;
        background: linear-gradient(135deg, #c87400 0%, #e67e22 100%);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(200,116,0,0.25);
    }
    
    .btn-view-score:hover {
        background: linear-gradient(135deg, #a85f00 0%, #d35400 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(200,116,0,0.35);
    }
    
    /* Score Table */
    .score-detail-section {
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-top: 30px;
    }
    
    .score-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .score-table thead {
        background: #f8f9fa;
    }
    
    .score-table th {
        padding: 18px;
        text-align: left;
        font-weight: 600;
        color: #333;
        border-bottom: 2px solid #e9ecef;
    }
    
    .score-table td {
        padding: 15px 18px;
        border-bottom: 1px solid #e9ecef;
        color: #555;
    }
    
    .score-table tbody tr:hover {
        background: #f8f9fa;
    }
    
    .score-high {
        background: #27ae60;
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
    }
    
    .score-medium {
        background: #f39c12;
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
    }
    
    .score-low {
        background: #95a5a6;
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
    }
    
    .block-badge {
        background: #667eea;
        color: white;
        padding: 6px 12px;
        border-radius: 15px;
        font-weight: 600;
        font-size: 0.9rem;
    }
    
    .alert-info {
        background: #e3f2fd;
        color: #1976d2;
        padding: 20px;
        border-radius: 10px;
        border-left: 4px solid #1976d2;
        margin: 20px;
    }
    
    .alert-empty {
        text-align: center;
        padding: 40px;
        color: #999;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .university-detail-name {
            font-size: 1.6rem;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .major-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .major-card-info {
            flex-direction: column;
            gap: 10px;
        }
    }
    
    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        padding: 30px 20px;
        background: white;
        border-radius: 0 0 16px 16px;
    }
    
    .pagination a,
    .pagination span {
        padding: 10px 16px;
        text-decoration: none;
        color: #333;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }
    
    .pagination a:hover {
        background: #c87400;
        color: white;
        border-color: #c87400;
        transform: translateY(-2px);
    }
    
    .pagination span.current {
        background: linear-gradient(135deg, #c87400 0%, #e67e22 100%);
        color: white;
        border-color: #c87400;
    }
    
    .pagination .page-info {
        color: #666;
        font-size: 0.95rem;
        padding: 0 10px;
        border: none;
    }
    
    /* Modal Popup */
    .score-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease;
    }
    
    .score-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .score-modal-content {
        background-color: white;
        border-radius: 16px;
        max-width: 1100px;
        width: 95%;
        max-height: 85vh;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        animation: slideUp 0.3s ease;
    }
    
    .score-modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .score-modal-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    
    .score-modal-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .score-modal-close:hover {
        background: rgba(255,255,255,0.3);
        transform: rotate(90deg);
    }
    
    .score-modal-body {
        padding: 0;
        max-height: calc(85vh - 80px);
        overflow-y: auto;
    }
    
    /* Chỉnh độ rộng cột trong modal */
    .score-modal .score-table th:nth-child(1),
    .score-modal .score-table td:nth-child(1) {
        width: 80px;
        text-align: center;
    }
    
    .score-modal .score-table th:nth-child(2),
    .score-modal .score-table td:nth-child(2) {
        width: 150px;
        text-align: center;
    }
    
    .score-modal .score-table th:nth-child(3),
    .score-modal .score-table td:nth-child(3) {
        width: 140px;
        text-align: center;
    }
    
    .score-modal .score-table th:nth-child(4),
    .score-modal .score-table td:nth-child(4) {
        width: 140px;
        text-align: center;
    }
    
    .score-modal .score-table th:nth-child(5),
    .score-modal .score-table td:nth-child(5) {
        width: auto;
        min-width: 300px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="detail-container">
        <!-- University Info -->
        <div class="university-detail-card">
            <div class="university-detail-header">
                <div class="university-detail-name"><?php echo escape($university['name']); ?></div>
                <div class="university-detail-code"><?php echo escape($university['code']); ?></div>
            </div>
            <div class="university-detail-body">
                <div class="info-grid">
                    <?php if ($university['address']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Địa chỉ</span>
                        <span class="info-card-value"><?php echo escape($university['address']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($university['province']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Tỉnh/Thành phố</span>
                        <span class="info-card-value"><?php echo escape($university['province']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                        <span class="info-card-label">Loại trường</span>
                        <span class="info-card-value"><?php echo escape($university['university_type']); ?></span>
                    </div>
                    
                    <?php if ($university['established_year']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Thành lập</span>
                        <span class="info-card-value">Năm <?php echo escape($university['established_year']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($university['website']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Website</span>
                        <span class="info-card-value">
                            <a href="<?php echo escape($university['website']); ?>" target="_blank">
                                <?php echo escape($university['website']); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($university['phone']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Điện thoại</span>
                        <span class="info-card-value"><?php echo escape($university['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($university['email']): ?>
                    <div class="info-card">
                        <span class="info-card-label">Email</span>
                        <span class="info-card-value">
                            <a href="mailto:<?php echo escape($university['email']); ?>">
                                <?php echo escape($university['email']); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($university['description']): ?>
                <div class="university-description">
                    <h3>Giới thiệu</h3>
                    <p><?php echo nl2br(escape($university['description'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Majors Section -->
        <div class="majors-section">
            <div class="majors-header">
                <h2>Danh sách ngành đào tạo (<?php echo $total_majors; ?> ngành)</h2>
            </div>
            
            <?php if (empty($majors)): ?>
                <div class="alert-empty">
                    <strong>Chưa có thông tin ngành đào tạo!</strong>
                </div>
            <?php else: ?>
                <?php foreach ($majors as $major): ?>
                    <div class="major-card">
                        <div class="major-card-header">
                            <div class="major-card-name"><?php echo escape($major['name']); ?></div>
                            <div class="major-card-code"><?php echo escape($major['code']); ?></div>
                        </div>
                        <div class="major-card-info">
                            <div class="major-info-item">
                                <strong>Trình độ:</strong> <?php echo escape($major['training_level']); ?>
                            </div>
                            <div class="major-info-item">
                                <strong>Thời gian:</strong> <?php echo escape($major['duration_years']); ?> năm
                            </div>
                            <?php if ($major['avg_score']): ?>
                            <div class="major-info-item">
                                <strong>Điểm TB:</strong> 
                                <span class="score-badge"><?php echo formatScore($major['avg_score']); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="major-info-item">
                                <strong>Điểm chuẩn:</strong>
                                <?php if ($major['min_score'] && $major['max_score']): ?>
                                    <?php echo formatScore($major['min_score']); ?> - <?php echo formatScore($major['max_score']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Chưa có dữ liệu</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="#" 
                               onclick="openScoreModal(<?php echo $major['id']; ?>, '<?php echo escape($major['name'], ENT_QUOTES); ?>'); return false;" 
                               class="btn-view-score">
                                Xem điểm chuẩn chi tiết
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?id=<?php echo $university_id; ?>&page=1">‹ Trước</a>
                    <?php endif; ?>
                    
                    <?php
                    // Hiển thị các số trang
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?id=<?php echo $university_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?id=<?php echo $university_id; ?>&page=<?php echo $total_pages; ?>">Sau ›</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Score Modal -->
        <div id="scoreModal" class="score-modal">
            <div class="score-modal-content">
                <div class="score-modal-header">
                    <h2 class="score-modal-title" id="modalTitle">Điểm chuẩn chi tiết</h2>
                    <button class="score-modal-close" onclick="closeScoreModal()">X</button>
                </div>
                <div class="score-modal-body" id="modalBody">
                    <div class="modal-loading">
                        <p>Đang tải...</p>
                    </div>
                </div>
            </div>
        </div>
</div>

<script>
// Data điểm chuẩn cho mỗi ngành (embedded từ PHP)
const scoresData = {
    <?php foreach ($majors as $major): ?>
        <?php
        // Lấy điểm chuẩn cho ngành này
        $major_scores_query = "
            SELECT * FROM admission_scores 
            WHERE major_id = :major_id 
            ORDER BY year DESC, block
        ";
        $major_scores_stmt = $pdo->prepare($major_scores_query);
        $major_scores_stmt->execute([':major_id' => $major['id']]);
        $major_scores = $major_scores_stmt->fetchAll();
        ?>
        "<?php echo $major['id']; ?>": <?php echo json_encode($major_scores); ?><?php if ($major !== end($majors)): ?>,<?php endif; ?>
    <?php endforeach; ?>
};

function openScoreModal(majorId, majorName) {
    const modal = document.getElementById('scoreModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = 'Điểm chuẩn chi tiết - ' + majorName;
    
    const scores = scoresData[majorId];
    
    if (!scores || scores.length === 0) {
        modalBody.innerHTML = `
            <div class="modal-no-data">
                <p>Chưa có dữ liệu điểm chuẩn cho ngành này!</p>
            </div>
        `;
    } else {
        let tableHTML = `
            <table class="score-table">
                <thead>
                    <tr>
                        <th>Năm</th>
                        <th>Khối thi</th>
                        <th>Điểm chuẩn</th>
                        <th>Chỉ tiêu</th>
                        <th>Ghi chú</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        scores.forEach(score => {
            let scoreClass = 'score-low';
            if (score.min_score >= 25) scoreClass = 'score-high';
            else if (score.min_score >= 20) scoreClass = 'score-medium';
            
            tableHTML += `
                <tr>
                    <td><strong>${score.year}</strong></td>
                    <td><span class="block-badge">${score.block}</span></td>
                    <td><span class="${scoreClass}">${parseFloat(score.min_score).toFixed(2)}</span></td>
                    <td><strong>${parseInt(score.quota).toLocaleString()}</strong> sinh viên</td>
                    <td>${score.note || '-'}</td>
                </tr>
            `;
        });
        
        tableHTML += `
                </tbody>
            </table>
        `;
        
        modalBody.innerHTML = tableHTML;
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeScoreModal() {
    const modal = document.getElementById('scoreModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

// Close modal khi click bên ngoài
document.getElementById('scoreModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeScoreModal();
    }
});

// Close modal với ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeScoreModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>

