<?php
// Khởi tạo session nếu chưa có
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Visitor counter
$counter_file = __DIR__ . '/../counter.txt';
if (!file_exists($counter_file)) {
    file_put_contents($counter_file, '0');
}
$total_visitors = (int)file_get_contents($counter_file);
$total_visitors++;
file_put_contents($counter_file, $total_visitors);

// Online users counter
$online_file = __DIR__ . '/../online.txt';
$session_id = session_id();
$timeout = 300; // 5 minutes

if (file_exists($online_file)) {
    $online_data = json_decode(file_get_contents($online_file), true);
} else {
    $online_data = [];
}

// Remove expired sessions
$current_time = time();
foreach ($online_data as $id => $time) {
    if ($current_time - $time > $timeout) {
        unset($online_data[$id]);
    }
}

// Add current session
$online_data[$session_id] = $current_time;
file_put_contents($online_file, json_encode($online_data));
$online_users = count($online_data);

// Xác định đường dẫn base dựa trên vị trí hiện tại
$current_dir = dirname($_SERVER['PHP_SELF']);
$base_path = '';

// Nếu đang ở trong thư mục con, cần điều chỉnh đường dẫn
if (strpos($current_dir, '/student') !== false) {
    $base_path = '../';
} elseif (strpos($current_dir, '/admin') !== false) {
    $base_path = '../';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? escape($page_title) : 'Hệ thống hỗ trợ việc chọn trường đại học cho học sinh'; ?></title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body<?php echo (strpos($_SERVER['PHP_SELF'], 'predict_score') !== false) ? ' class="predict-page"' : ''; ?>>
    <script>
        // Compute application root path reliably (ensures correct API fetch paths even with pretty URLs)
        (function(){
            // Try to use PHP computed base_path (simple fallback for student/admin folder)
            var phpBase = '<?php echo isset($base_path) ? $base_path : ''; ?>';
            if (phpBase && phpBase.indexOf('/') === 0) {
                window.BASE_PATH = phpBase;
                return;
            }
            // If phpBase is relative or empty, compute from current location
            var parts = window.location.pathname.split('/');
            // If app is in a top-level folder, parts[1] contains folder name (e.g., 'tuyensinh')
            if (parts.length > 1 && parts[1] !== '') {
                window.BASE_PATH = '/' + parts[1] + '/';
            } else {
                // otherwise assume root
                window.BASE_PATH = '/';
            }
        })();
    </script>
    <!-- Navigation -->
    <nav class="nav">
    <div class="container" style="display:flex;align-items:center;gap:24px;padding:8px 0;">
            <!-- Brand / Logo -->
            <a class="nav-brand" href="<?php echo $base_path; ?>search_score" style="display:inline-flex;align-items:center;text-decoration:none;">
                <img src="<?php echo $base_path; ?>img/logo.jpg" alt="Logo" style="height:64px;max-height:64px;margin-right:12px;display:block;">
            </a>

            <!-- Navigation links -->
            <ul style="display:flex;gap:12px;list-style:none;margin:0;padding:0;align-items:center;flex:1;justify-content:flex-end;">
                <li><a href="<?php echo $base_path; ?>search_score">Trang chủ</a></li>
                <li><a href="<?php echo $base_path; ?>gioi_thieu">Giới thiệu</a></li>
                <li><a href="<?php echo $base_path; ?>predict_score">Dự đoán điểm</a></li>
                <li><a href="<?php echo $base_path; ?>recommend_major">Gợi ý ngành</a></li>
                <li><a href="<?php echo $base_path; ?>admin/">Quản trị</a></li>
                <?php if (!empty($_SESSION['student_logged_in'])): ?>
                    <li><a href="<?php echo $base_path; ?>student/logout">Đăng xuất</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $base_path; ?>student/login">Đăng nhập</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <style>
    /* Responsive logo sizing */
    @media (max-width: 768px) {
        .nav .nav-brand img { height:40px !important; max-height:40px !important; }
    }
    @media (min-width: 1200px) {
        .nav .nav-brand img { height:72px !important; max-height:72px !important; }
    }
    </style>

    <!-- Header with Banner (skip if $no_banner = true) -->
    <?php if (empty($no_banner)): ?>
    <header class="header">
        <div class="header-banner">
            <img src="<?php echo $base_path; ?>img/banner.jpg" alt="University Banner" class="banner-image">
            <div class="header-overlay">
                <div class="container">
                    <h1>Hệ thống hỗ trợ việc chọn trường đại học cho học sinh</h1>
                    <p>Tra cứu thông tin tuyển sinh các trường đại học trên toàn quốc</p>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <div class="container">
