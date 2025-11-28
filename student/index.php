<?php
require_once '../config/database.php';

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$page_title = 'Khu vực học sinh';
include '../includes/header.php';
?>
    <div class="hero">
        <h1>Chào, <?php echo escape($_SESSION['student_username'] ?? 'Học sinh'); ?>!</h1>
        <p>Bạn đã đăng nhập thành công. Bạn có thể tra cứu trường/ngành và xem điểm chuẩn mới nhất.</p>
        <div class="actions">
            <a class="btn btn-primary" href="../search_score">Tìm kiếm trường/ngành</a>
            <a class="btn btn-secondary" href="logout.php">Đăng xuất</a>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>