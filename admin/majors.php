<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
	header('Location: login.php');
	exit;
}

$pdo = getDBConnection();

// Chọn trường (nếu có truyền university_id thì lock vào trường đó)
$selected_university_id = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;

// Bộ lọc theo tên/mã ngành
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Lấy danh sách trường phục vụ select
$universities = $pdo->query("SELECT id, name, code FROM universities ORDER BY name")->fetchAll();

$message = '';
$message_type = '';

if ($_POST) {
	$action = $_POST['action'] ?? '';
	$id = (int)($_POST['id'] ?? 0);
	
	$university_id = (int)($_POST['university_id'] ?? 0);
    $code = trim($_POST['code'] ?? '');
    $moet_code = trim($_POST['moet_code'] ?? '');
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$training_level = $_POST['training_level'] ?? 'Đại học';
	$duration_years = (int)($_POST['duration_years'] ?? 4);
	
	if ($action === 'add' || $action === 'edit') {
		if (!$university_id || empty($code) || empty($name)) {
			$message = 'Vui lòng nhập đủ Trường, Mã ngành, Tên ngành';
			$message_type = 'error';
		} else {
			try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO majors (university_id, code, moet_code, name, description, training_level, duration_years) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$university_id, $code, ($moet_code !== '' ? $moet_code : null), $name, $description, $training_level, $duration_years]);
					$message = 'Thêm ngành thành công';
				} else {
                    $stmt = $pdo->prepare("UPDATE majors SET university_id=?, code=?, moet_code=?, name=?, description=?, training_level=?, duration_years=? WHERE id=?");
                    $stmt->execute([$university_id, $code, ($moet_code !== '' ? $moet_code : null), $name, $description, $training_level, $duration_years, $id]);
					$message = 'Cập nhật ngành thành công';
				}
				$message_type = 'success';
			} catch (PDOException $e) {
				$message = 'Lỗi: ' . $e->getMessage();
				$message_type = 'error';
			}
		}
	} elseif ($action === 'delete') {
		try {
			$stmt = $pdo->prepare("DELETE FROM majors WHERE id = ?");
			$stmt->execute([$id]);
			$message = 'Xóa ngành thành công';
			$message_type = 'success';
		} catch (PDOException $e) {
			$message = 'Lỗi: ' . $e->getMessage();
			$message_type = 'error';
		}
	}
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

// Lọc theo trường và q
$where = [];
$params = [];
if ($selected_university_id) {
	$where[] = 'm.university_id = :uid';
	$params[':uid'] = $selected_university_id;
}
if ($q !== '') {
	$where[] = '(m.name LIKE :q_name OR m.code LIKE :q_code)';
	$params[':q_name'] = "%$q%";
	$params[':q_code'] = "%$q%";
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

// Đếm tổng số
$sqlCount = "SELECT COUNT(*) as total FROM majors m LEFT JOIN universities u ON m.university_id = u.id $whereSql";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total = $stmtCount->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách ngành
$sql = "
    SELECT m.*, u.name as university_name, u.code as university_code,
        (SELECT AVG(a.min_score) FROM admission_scores a WHERE a.major_id = m.id) as avg_score
    FROM majors m
    LEFT JOIN universities u ON m.university_id = u.id
    $whereSql
    ORDER BY COALESCE(u.name, ''), m.name
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$majors = $stmt->fetchAll();

// Nếu edit
$edit_major = null;
if (isset($_GET['edit'])) {
	$edit_id = (int)$_GET['edit'];
	$s = $pdo->prepare("SELECT * FROM majors WHERE id = ?");
	$s->execute([$edit_id]);
	$edit_major = $s->fetch();
	if ($edit_major) {
		$selected_university_id = $edit_major['university_id'];
	}
}

$show_form = isset($_GET['new']) || !empty($edit_major);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Quản lý ngành - Admin</title>
	<link rel="stylesheet" href="../assets/css/style.css">
	<link rel="stylesheet" href="admin.css">
	<style>
		.form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 2rem; }
		.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
		.form-group { margin-bottom: 1rem; }
		.form-group label { display: block; margin-bottom: 0.5rem; color: #555; font-weight: 500; }
		.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s ease; }
		.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
		.form-group textarea { height: 100px; resize: vertical; }
		.btn-group { display: flex; gap: 1rem; }
		.btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem; font-weight: 500; text-decoration: none; display: inline-block; text-align: center; transition: all 0.3s ease; }
		.btn-primary { background: #3498db; color: white; }
		.btn-primary:hover { background: #2980b9; transform: translateY(-2px); }
		.btn-secondary { background: #6c757d; color: white; }
		.btn-secondary:hover { background: #5a6268; }
		.btn-danger { background: #dc3545; color: white; }
		.btn-danger:hover { background: #c82333; }
		.btn-success { background: #28a745; color: white; }
		.btn-success:hover { background: #218838; }
		.search-box { margin-bottom: 2rem; }
		.search-box input { width: 100%; max-width: 500px; padding: 0.75rem 1rem; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
		.search-box input:focus { outline: none; border-color: #3498db; }
		.alert { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; }
		.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
		.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
		.data-table { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; }
		.table-header { background: #f8f9fa; padding: 1.5rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
		.table-content { overflow-x: auto; }
		.score-table { width: 100%; border-collapse: collapse; white-space: nowrap; font-size: 0.95rem; }
		.score-table th, .score-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #eee; }
		.score-table th { background: #f8f9fa; font-weight: 600; color: #555; }
		.score-table tr:hover { background: #f8f9fa; }
		.score-table th:nth-child(1), .score-table td:nth-child(1) { width: 50px; }
		.score-table th:nth-child(2), .score-table td:nth-child(2) { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.score-table th:nth-child(3), .score-table td:nth-child(3) { width: 90px; text-align: center; }
		.score-table th:nth-child(4), .score-table td:nth-child(4) { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.score-table th:nth-child(5), .score-table td:nth-child(5) { width: 80px; text-align: center; }
		.score-table th:nth-child(6), .score-table td:nth-child(6) { width: 80px; text-align: center; }
		.score-table th:nth-child(7), .score-table td:nth-child(7) { width: 260px; }
		.action-buttons { display: flex; gap: 0.4rem; flex-wrap: nowrap; }
		.action-buttons .btn { padding: 0.4rem 0.8rem; font-size: 0.85rem; white-space: nowrap; }
	</style>
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
				<a href="index.php" class="menu-item">
					Dashboard
				</a>
				<a href="universities.php" class="menu-item">
					Quản lý trường
				</a>
				<a href="majors.php" class="menu-item active">
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
				<h1>Quản lý ngành</h1>
				<p>Thêm, sửa, xóa thông tin các ngành đào tạo</p>
			</div>
			<div class="admin-main-content">

		<?php if ($message): ?>
			<div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom:1rem;"><?php echo escape($message); ?></div>
		<?php endif; ?>

		<!-- Filter -->
		<div class="form-section" style="padding:1rem 1.5rem; margin-bottom:1rem;">
			<form method="GET" class="search-form" style="display:grid; grid-template-columns:1fr 1fr auto auto; gap:1rem;">
				<div class="form-group">
					<label>Trường</label>
					<select name="university_id" onchange="this.form.submit()">
						<option value="">-- Tất cả --</option>
						<?php foreach ($universities as $u): ?>
							<option value="<?php echo $u['id']; ?>" <?php echo (int)$selected_university_id === (int)$u['id'] ? 'selected' : ''; ?>><?php echo escape($u['name']) . ' (' . escape($u['code']) . ')'; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label>Tìm theo tên/mã ngành</label>
					<input type="text" name="q" value="<?php echo escape($q); ?>" placeholder="VD: IT01 hoặc CNTT">
				</div>
				<div class="form-group" style="display:flex; gap:.5rem; align-items:end;">
					<button class="btn btn-primary" type="submit">Lọc</button>
					<a class="btn btn-secondary" href="majors.php">Xóa</a>
				</div>
				<div class="form-group" style="display:flex; align-items:end;">
					<a class="btn btn-success" href="majors.php?new=1<?php echo $selected_university_id ? '&university_id='.$selected_university_id : ''; ?><?php echo $q !== '' ? '&q='.urlencode($q) : ''; ?>">+ Thêm ngành mới</a>
				</div>
			</form>
		</div>

		<?php if ($show_form): ?>
		<div class="form-section">
			<h2><?php echo $edit_major ? 'Chỉnh sửa ngành' : 'Thêm ngành mới'; ?></h2>
			<form method="POST">
				<input type="hidden" name="action" value="<?php echo $edit_major ? 'edit' : 'add'; ?>">
				<?php if ($edit_major): ?><input type="hidden" name="id" value="<?php echo $edit_major['id']; ?>"><?php endif; ?>
				<div class="form-row">
					<div class="form-group">
						<label>Trường</label>
						<select name="university_id" required>
							<option value="">-- Chọn trường --</option>
							<?php foreach ($universities as $u): ?>
								<option value="<?php echo $u['id']; ?>" <?php echo (int)($edit_major['university_id'] ?? $selected_university_id) === (int)$u['id'] ? 'selected' : ''; ?>><?php echo escape($u['name']) . ' (' . escape($u['code']) . ')'; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label>Mã ngành</label>
						<input type="text" name="code" value="<?php echo escape($edit_major['code'] ?? ''); ?>" required>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label>Tên ngành</label>
						<input type="text" name="name" value="<?php echo escape($edit_major['name'] ?? ''); ?>" required>
					</div>
					<div class="form-group">
						<label>Mã MOET (nếu có)</label>
						<input type="text" name="moet_code" value="<?php echo escape($edit_major['moet_code'] ?? ''); ?>" placeholder="VD: 7140201">
					</div>
					<div class="form-group">
						<label>Trình độ</label>
						<select name="training_level">
							<?php $levels=['Đại học','Cao đẳng','Thạc sĩ','Tiến sĩ']; foreach($levels as $lv): ?>
								<option value="<?php echo $lv; ?>" <?php echo (($edit_major['training_level'] ?? 'Đại học')===$lv)?'selected':''; ?>><?php echo $lv; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label>Thời gian đào tạo (năm)</label>
						<input type="number" name="duration_years" min="1" max="10" value="<?php echo (int)($edit_major['duration_years'] ?? 4); ?>">
					</div>
					<div class="form-group">
						<label>Mô tả</label>
						<input type="text" name="description" value="<?php echo escape($edit_major['description'] ?? ''); ?>">
					</div>
				</div>
				<button class="btn btn-primary" type="submit"><?php echo $edit_major ? 'Cập nhật' : 'Thêm mới'; ?></button>
				<?php if ($edit_major): ?><a class="btn btn-secondary" href="majors.php">Hủy</a><?php else: ?><a class="btn btn-secondary" href="majors.php">Đóng</a><?php endif; ?>
			</form>
		</div>
		<?php endif; ?>

		<div class="data-table">
			<div class="table-header"><h2>Danh sách ngành (<?php echo $total; ?> - Trang <?php echo $page; ?>/<?php echo $total_pages; ?>)</h2></div>
			<div class="table-content">
				<table class="score-table">
					<thead>
						<tr>
							<th>STT</th>
							<th>Trường</th>
                            <th>Mã ngành</th>
							<th>Tên ngành</th>
							<th>Thời gian</th>
                            <th>Điểm TB</th>
							<th>Thao tác</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($majors as $i => $m): ?>
						<tr>
							<td><?php echo $offset + $i+1; ?></td>
							<td><?php echo escape($m['university_name']); ?></td>
                            <td><span class="major-code"><?php echo escape($m['code']); ?></span></td>
							<td><?php echo escape($m['name']); ?></td>
							<td><?php echo (int)$m['duration_years']; ?> năm</td>
                            <td><?php echo $m['avg_score'] !== null ? formatScore($m['avg_score']) : 'N/A'; ?></td>
							<td>
								<div class="action-buttons">
									<a class="btn btn-success" href="?edit=<?php echo $m['id']; ?>">Sửa</a>
									<a class="btn btn-primary" href="scores.php?major_id=<?php echo $m['id']; ?>">Điểm chuẩn</a>
									<form method="POST" style="display:inline" onsubmit="return confirm('Xóa ngành này?')">
										<input type="hidden" name="action" value="delete">
										<input type="hidden" name="id" value="<?php echo $m['id']; ?>">
										<button class="btn btn-danger" type="submit">Xóa</button>
									</form>
								</div>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			
			<!-- Pagination -->
			<?php if ($total_pages > 1): ?>
			<div style="padding: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; align-items: center; font-size: 1rem;">
				<?php if ($page > 1): ?>
					<a href="?page=1<?php echo $selected_university_id ? '&university_id=' . $selected_university_id : ''; ?><?php echo $q ? '&q=' . urlencode($q) : ''; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">Trước</a>
					<span style="color: #ccc;">|</span>
				<?php else: ?>
					<span style="color: #ccc; padding: 0.5rem 0.75rem;">Trước</span>
					<span style="color: #ccc;">|</span>
				<?php endif; ?>
				
				<?php 
				$start = max(1, $page - 2);
				$end = min($total_pages, $page + 2);
				
				if ($start > 1): ?>
					<a href="?page=1<?php echo $selected_university_id ? '&university_id=' . $selected_university_id : ''; ?><?php echo $q ? '&q=' . urlencode($q) : ''; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">1</a>
					<?php if ($start > 2): ?>
						<span style="color: #666;">...</span>
					<?php endif; ?>
				<?php endif; ?>
				
				<?php for ($i = $start; $i <= $end; $i++): ?>
					<?php if ($i == $page): ?>
						<strong style="color: #2c3e50; padding: 0.5rem 0.75rem; background: #ecf0f1; border-radius: 4px;"><?php echo $i; ?></strong>
					<?php else: ?>
						<a href="?page=<?php echo $i; ?><?php echo $selected_university_id ? '&university_id=' . $selected_university_id : ''; ?><?php echo $q ? '&q=' . urlencode($q) : ''; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;"><?php echo $i; ?></a>
					<?php endif; ?>
					<?php if ($i < $end): ?>
						<span style="color: #ccc;">|</span>
					<?php endif; ?>
				<?php endfor; ?>
				
				<?php if ($end < $total_pages): ?>
					<?php if ($end < $total_pages - 1): ?>
						<span style="color: #666;">...</span>
					<?php endif; ?>
					<span style="color: #ccc;">|</span>
					<a href="?page=<?php echo $total_pages; ?><?php echo $selected_university_id ? '&university_id=' . $selected_university_id : ''; ?><?php echo $q ? '&q=' . urlencode($q) : ''; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;"><?php echo $total_pages; ?></a>
				<?php endif; ?>
				
				<?php if ($page < $total_pages): ?>
					<span style="color: #ccc;">|</span>
					<a href="?page=<?php echo $total_pages; ?><?php echo $selected_university_id ? '&university_id=' . $selected_university_id : ''; ?><?php echo $q ? '&q=' . urlencode($q) : ''; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">Sau</a>
				<?php else: ?>
					<span style="color: #ccc;">|</span>
					<span style="color: #ccc; padding: 0.5rem 0.75rem;">Sau</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
			</div>
		</main>
	</div>
</body>
</html>
