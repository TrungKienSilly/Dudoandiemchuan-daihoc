<?php
session_start();
require_once '../config/database.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();

// Lấy thống kê tổng quan
$stats = [];

// Tổng số trường
$stats['total_universities'] = $pdo->query("SELECT COUNT(*) as count FROM universities")->fetch()['count'];

// Tổng số ngành
$stats['total_majors'] = $pdo->query("SELECT COUNT(*) as count FROM majors")->fetch()['count'];

// Tổng số điểm chuẩn
$stats['total_scores'] = $pdo->query("SELECT COUNT(*) as count FROM admission_scores")->fetch()['count'];

// Số trường theo loại
$university_types = $pdo->query("
    SELECT university_type, COUNT(*) as count 
    FROM universities 
    GROUP BY university_type
")->fetchAll();

// Số ngành theo trường (top 10)
$top_universities = $pdo->query("
    SELECT u.name, COUNT(m.id) as major_count
    FROM universities u
    LEFT JOIN majors m ON u.id = m.university_id
    GROUP BY u.id, u.name
    ORDER BY major_count DESC
    LIMIT 10
")->fetchAll();

// Điểm chuẩn cao nhất năm hiện tại
$current_year = date('Y');
$highest_scores = $pdo->query("
    SELECT u.name as university_name, m.name as major_name, a.min_score, a.block
    FROM admission_scores a
    JOIN majors m ON a.major_id = m.id
    JOIN universities u ON m.university_id = u.id
    WHERE a.year = $current_year
    ORDER BY a.min_score DESC
    LIMIT 10
")->fetchAll();

$page_title = 'Quản trị - Hệ thống quản lý trường đại học';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2>Admin Panel</h2>
                <p>Quản lý tuyển sinh</p>
            </div>
            
            <nav class="admin-menu">
                <a href="index.php" class="menu-item active">
                    Dashboard
                </a>
                <a href="universities.php" class="menu-item">
                    Quản lý trường
                </a>
                <a href="majors.php" class="menu-item">
                    Quản lý ngành
                </a>
                <a href="scores.php" class="menu-item">
                    Quản lý điểm chuẩn
                </a>
                <a href="../search_score.php" class="menu-item">
                    Xem website
                </a>
            </nav>
            
            <div class="admin-logout">
                <a href="logout.php" class="logout-btn">Đăng xuất</a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header-bar">
                <h1>Dashboard</h1>
                <p>Tổng quan hệ thống quản lý tuyển sinh</p>
            </div>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo formatNumber($stats['total_universities']); ?></div>
                <div class="stat-label">Tổng số trường đại học</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatNumber($stats['total_majors']); ?></div>
                <div class="stat-label">Tổng số ngành đào tạo</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo formatNumber($stats['total_scores']); ?></div>
                <div class="stat-label">Tổng số điểm chuẩn</div>
            </div>
        </div>

        <!-- University Types Chart -->
        <div class="data-table">
            <div class="table-header">
                <h3>Phân bố trường theo loại</h3>
            </div>
            <div class="table-content">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Loại trường</th>
                            <th>Số lượng</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($university_types as $type): ?>
                            <tr>
                                <td><?php echo escape($type['university_type']); ?></td>
                                <td><?php echo formatNumber($type['count']); ?></td>
                                <td><?php echo round(($type['count'] / $stats['total_universities']) * 100, 1); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Universities by Major Count -->
        <div class="data-table">
            <div class="table-header">
                <h3>Top 10 trường có nhiều ngành đào tạo nhất</h3>
            </div>
            <div class="table-content">
                <table class="table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên trường</th>
                            <th>Số ngành</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_universities as $index => $university): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo escape($university['name']); ?></td>
                                <td><?php echo formatNumber($university['major_count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Highest Scores -->
        <div class="data-table">
            <div class="table-header">
                <h3>Top 10 điểm chuẩn cao nhất năm <?php echo $current_year; ?></h3>
            </div>
            <div class="table-content">
                <table class="table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Trường</th>
                            <th>Ngành</th>
                            <th>Khối</th>
                            <th>Điểm chuẩn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($highest_scores as $index => $score): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo escape($score['university_name']); ?></td>
                                <td><?php echo escape($score['major_name']); ?></td>
                                <td><?php echo escape($score['block']); ?></td>
                                <td><?php echo formatScore($score['min_score']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </main>
    </div>
</body>
</html>