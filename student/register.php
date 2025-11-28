<?php
require_once '../config/database.php';

$pdo = getDBConnection();

$error = '';
$success = '';

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');

    if ($username === '' || $password === '' || $password_confirm === '') {
        $error = 'Vui lòng nhập đầy đủ Tên đăng nhập và Mật khẩu!';
    } elseif ($password !== $password_confirm) {
        $error = 'Mật khẩu nhập lại không khớp!';
    } else {
        try {
            // Kiểm tra trùng tên đăng nhập trong bảng students
            $check = $pdo->prepare('SELECT id FROM students WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = 'Tên đăng nhập đã tồn tại!';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare('INSERT INTO students (username, password, email, full_name) VALUES (?, ?, ?, ?)');
                $ins->execute([$username, $hash, $email, $full_name]);
                $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay.';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi: ' . $e->getMessage();
        }
    }
}

$page_title = 'Đăng ký học sinh';
include '../includes/header.php';
?>
    <div class="auth">
        <h1>Đăng ký học sinh</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo escape($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo escape($success); ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Tên đăng nhập *</label>
                <input id="username" name="username" value="<?php echo escape($_POST['username'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu *</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div class="form-group">
                <label for="password_confirm">Nhập lại mật khẩu *</label>
                <input id="password_confirm" name="password_confirm" type="password" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?php echo escape($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="full_name">Họ và tên</label>
                <input id="full_name" name="full_name" value="<?php echo escape($_POST['full_name'] ?? ''); ?>">
            </div>
            <button class="btn" type="submit">Đăng ký</button>
        </form>
        <div class="links">
            <a href="login">← Đăng nhập</a>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>