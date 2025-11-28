<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
	header('Location: login.php');
	exit;
}

$pdo = getDBConnection();

// Filters
$selected_university_id = isset($_GET['university_id']) ? (int)$_GET['university_id'] : 0;
$selected_major_id = isset($_GET['major_id']) ? (int)$_GET['major_id'] : 0;
$score_min = isset($_GET['score_min']) && $_GET['score_min'] !== '' ? (float)$_GET['score_min'] : null;
$score_max = isset($_GET['score_max']) && $_GET['score_max'] !== '' ? (float)$_GET['score_max'] : null;

// Load universities for select
$universities = $pdo->query("SELECT id, name FROM universities ORDER BY name")->fetchAll();

// Load majors for select (optionally filtered by university)
if ($selected_university_id) {
	$stmtMajors = $pdo->prepare("SELECT m.id, m.name, m.code, u.name AS university_name FROM majors m JOIN universities u ON m.university_id = u.id WHERE m.university_id = :uid ORDER BY m.name");
	$stmtMajors->execute([':uid' => $selected_university_id]);
	$majors = $stmtMajors->fetchAll();
} else {
	$majors = $pdo->query("SELECT m.id, m.name, m.code, u.name AS university_name FROM majors m JOIN universities u ON m.university_id = u.id ORDER BY u.name, m.name")->fetchAll();
}

$message = '';
$message_type = '';

if ($_POST) {
	$action = $_POST['action'] ?? '';
	$id = (int)($_POST['id'] ?? 0);
	$major_id = (int)($_POST['major_id'] ?? 0);
	$year = (int)($_POST['year'] ?? date('Y'));
	$block = trim($_POST['block'] ?? 'A00');
	$min_score = (float)($_POST['min_score'] ?? 0);
	$quota = (int)($_POST['quota'] ?? 0);
	$note = trim($_POST['note'] ?? '');
	
	if ($action === 'add' || $action === 'edit') {
		if (!$major_id || !$year || empty($block)) {
			$message = 'Vui lòng chọn ngành, năm và khối';
			$message_type = 'error';
		} else {
			try {
				if ($action === 'add') {
					$stmt = $pdo->prepare("INSERT INTO admission_scores (major_id, year, block, min_score, quota, note) VALUES (?, ?, ?, ?, ?, ?)");
					$stmt->execute([$major_id, $year, $block, $min_score, $quota, $note]);
					$message = 'Thêm điểm chuẩn thành công';
				} else {
					$stmt = $pdo->prepare("UPDATE admission_scores SET major_id=?, year=?, block=?, min_score=?, quota=?, note=? WHERE id=?");
					$stmt->execute([$major_id, $year, $block, $min_score, $quota, $note, $id]);
					$message = 'Cập nhật điểm chuẩn thành công';
				}
				$message_type = 'success';
			} catch (PDOException $e) {
				$message = 'Lỗi: ' . $e->getMessage();
				$message_type = 'error';
			}
		}
	} elseif ($action === 'delete') {
		try {
			$stmt = $pdo->prepare("DELETE FROM admission_scores WHERE id = ?");
			$stmt->execute([$id]);
			$message = 'Xóa bản ghi thành công';
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

// Filter
$where = [];
$params = [];
if ($selected_major_id) {
	$where[] = 'a.major_id = :mid';
	$params[':mid'] = $selected_major_id;
} elseif ($selected_university_id) {
	$where[] = 'm.university_id = :uid';
	$params[':uid'] = $selected_university_id;
}
if ($score_min !== null) {
	$where[] = 'a.min_score >= :smin';
	$params[':smin'] = $score_min;
}
if ($score_max !== null) {
	$where[] = 'a.min_score <= :smax';
	$params[':smax'] = $score_max;
}
$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

// Đếm tổng số
$sqlCount = "
	SELECT COUNT(*) as total
	FROM admission_scores a
	JOIN majors m ON a.major_id = m.id
	JOIN universities u ON m.university_id = u.id
	$whereSql
";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total = $stmtCount->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Fetch scores
$sql = "
	SELECT a.*, m.name AS major_name, m.code AS major_code, u.name AS university_name
	FROM admission_scores a
	JOIN majors m ON a.major_id = m.id
	JOIN universities u ON m.university_id = u.id
	$whereSql
	ORDER BY a.year DESC, u.name, m.name, a.block
	LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$scores = $stmt->fetchAll();

// Edit item
$edit_item = null;
if (isset($_GET['edit'])) {
	$edit_id = (int)$_GET['edit'];
	$s = $pdo->prepare("SELECT * FROM admission_scores WHERE id = ?");
	$s->execute([$edit_id]);
	$edit_item = $s->fetch();
	if ($edit_item) {
		$selected_major_id = $edit_item['major_id'];
	}
}

$show_form = isset($_GET['new']) || !empty($edit_item);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Quản lý điểm chuẩn - Admin</title>
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
		.score-table { width: 100%; border-collapse: collapse; white-space: nowrap; }
		.score-table th, .score-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #eee; }
		.score-table th { background: #f8f9fa; font-weight: 600; color: #555; }
		.score-table tr:hover { background: #f8f9fa; }
		.score-table th:nth-child(3), .score-table td:nth-child(3) { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.action-buttons { display: flex; gap: 0.5rem; flex-wrap: nowrap; }
		.action-buttons .btn { padding: 0.5rem 1rem; font-size: 0.9rem; white-space: nowrap; }
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
				<a href="majors.php" class="menu-item">
					Quản lý ngành
				</a>
				<a href="scores.php" class="menu-item active">
					Quản lý điểm chuẩn
				</a>
				<a href="../search_score" class="menu-item">
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
				<h1>Quản lý điểm chuẩn</h1>
				<p>Thêm, sửa, xóa điểm chuẩn các trường đại học</p>
			</div>
			<div class="admin-main-content">
		<?php if ($message): ?>
			<div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom:1rem;"><?php echo escape($message); ?></div>
		<?php endif; ?>

		<div class="form-section" style="padding:1rem 1.5rem;">
			<h2 style="margin-bottom:1rem; font-size:1.2rem;">Bộ lọc</h2>
			<form method="GET">
				<div style="display:grid; grid-template-columns:1fr 2fr; gap:1rem; align-items:end;">
					<div class="form-group" style="margin-bottom:0;">
						<label>Trường</label>
						<select name="university_id" onchange="this.form.submit()">
							<option value="">-- Chọn trường --</option>
							<?php foreach ($universities as $u): ?>
								<option value="<?php echo $u['id']; ?>" <?php echo (int)$selected_university_id === (int)$u['id'] ? 'selected' : ''; ?>><?php echo escape($u['name']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group" style="margin-bottom:0;">
						<label>Thang điểm</label>
						<div style="display:flex; gap:.5rem; align-items:center;">
							<input type="number" name="score_min" step="0.1" min="0" max="30" placeholder="Min" value="<?php echo $score_min !== null ? $score_min : ''; ?>" style="width:100px;">
							<input type="number" name="score_max" step="0.1" min="0" max="30" placeholder="Max" value="<?php echo $score_max !== null ? $score_max : ''; ?>" style="width:100px;">
							<button class="btn btn-primary" type="submit">Lọc</button>
							<a class="btn btn-secondary" href="scores.php">Xóa</a>
							<a class="btn btn-success" href="scores_fetch.php">Tải dữ liệu</a>
						</div>
					</div>
				</div>
				<?php if ($selected_major_id): ?>
					<input type="hidden" name="major_id" value="<?php echo $selected_major_id; ?>">
				<?php endif; ?>
			</form>

			<?php if ($show_form): ?>
			<h2><?php echo $edit_item ? 'Chỉnh sửa điểm chuẩn' : 'Thêm điểm chuẩn'; ?></h2>
			<form method="POST">
				<input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
				<?php if ($edit_item): ?><input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>"><?php endif; ?>
				<div class="form-row">
					<div class="form-group">
						<label>Ngành</label>
						<select name="major_id" required>
							<option value="">-- Chọn ngành --</option>
							<?php foreach ($majors as $m): ?>
								<option value="<?php echo $m['id']; ?>" <?php echo (int)($edit_item['major_id'] ?? $selected_major_id) === (int)$m['id'] ? 'selected' : ''; ?>><?php echo escape($m['university_name'].' - '.$m['name'].' ('.$m['code'].')'); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label>Năm</label>
						<input type="number" name="year" min="2000" max="<?php echo date('Y')+1; ?>" value="<?php echo (int)($edit_item['year'] ?? date('Y')); ?>" required>
					</div>
					<div class="form-group">
						<label>Khối</label>
						<input type="text" name="block" value="<?php echo escape($edit_item['block'] ?? 'A00'); ?>" required>
					</div>
				</div>
				<div class="form-row">
					<div class="form-group">
						<label>Điểm chuẩn</label>
						<input type="number" name="min_score" min="0" max="30" step="0.1" value="<?php echo (float)($edit_item['min_score'] ?? 0); ?>">
					</div>
					<div class="form-group">
						<label>Chỉ tiêu</label>
						<input type="number" name="quota" min="0" max="10000" value="<?php echo (int)($edit_item['quota'] ?? 0); ?>">
					</div>
					<div class="form-group">
						<label>Ghi chú</label>
						<input type="text" name="note" value="<?php echo escape($edit_item['note'] ?? ''); ?>">
					</div>
				</div>
				<button class="btn btn-primary" type="submit"><?php echo $edit_item ? 'Cập nhật' : 'Thêm mới'; ?></button>
				<?php if ($edit_item): ?><a class="btn btn-secondary" href="scores.php">Hủy</a><?php else: ?><a class="btn btn-secondary" href="scores.php">Đóng</a><?php endif; ?>
			</form>
			<?php endif; ?>
		</div>

		<div class="data-table">
			<div class="table-header"><h2>Danh sách điểm chuẩn (<?php echo $total; ?> - Trang <?php echo $page; ?>/<?php echo $total_pages; ?>)</h2></div>
			<div class="table-content">
				<table class="score-table">
					<thead>
						<tr>
							<th>STT</th>
							<th>Trường</th>
							<th>Ngành</th>
							<th>Khối</th>
							<th>Năm</th>
							<th>Điểm</th>
							<th>Chỉ tiêu</th>
							<th>Thao tác</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($scores as $i => $s): ?>
						<tr>
							<td><?php echo $offset + $i+1; ?></td>
							<td><?php echo escape($s['university_name']); ?></td>
							<td><?php echo escape($s['major_name'].' ('.$s['major_code'].')'); ?></td>
							<td><span class="major-code"><?php echo escape($s['block']); ?></span></td>
							<td><?php echo (int)$s['year']; ?></td>
							<td><?php echo formatScore($s['min_score']); ?></td>
							<td><?php echo formatNumber($s['quota']); ?></td>
							<td>
								<div class="action-buttons">
									<a class="btn btn-success" href="?edit=<?php echo $s['id']; ?>">Sửa</a>
									<form method="POST" style="display:inline" onsubmit="return confirm('Xóa bản ghi này?')">
										<input type="hidden" name="action" value="delete">
										<input type="hidden" name="id" value="<?php echo $s['id']; ?>">
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
				<?php 
				$url_params = [];
				if ($selected_university_id) $url_params[] = 'university_id=' . $selected_university_id;
				if ($selected_major_id) $url_params[] = 'major_id=' . $selected_major_id;
				if ($score_min !== null) $url_params[] = 'score_min=' . $score_min;
				if ($score_max !== null) $url_params[] = 'score_max=' . $score_max;
				$url_query = !empty($url_params) ? '&' . implode('&', $url_params) : '';
				?>
				
				<?php if ($page > 1): ?>
					<a href="?page=1<?php echo $url_query; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">Trước</a>
					<span style="color: #ccc;">|</span>
				<?php else: ?>
					<span style="color: #ccc; padding: 0.5rem 0.75rem;">Trước</span>
					<span style="color: #ccc;">|</span>
				<?php endif; ?>
				
				<?php 
				$start = max(1, $page - 2);
				$end = min($total_pages, $page + 2);
				
				if ($start > 1): ?>
					<a href="?page=1<?php echo $url_query; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">1</a>
					<?php if ($start > 2): ?>
						<span style="color: #666;">...</span>
					<?php endif; ?>
				<?php endif; ?>
				
				<?php for ($i = $start; $i <= $end; $i++): ?>
					<?php if ($i == $page): ?>
						<strong style="color: #2c3e50; padding: 0.5rem 0.75rem; background: #ecf0f1; border-radius: 4px;"><?php echo $i; ?></strong>
					<?php else: ?>
						<a href="?page=<?php echo $i; ?><?php echo $url_query; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;"><?php echo $i; ?></a>
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
					<a href="?page=<?php echo $total_pages; ?><?php echo $url_query; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;"><?php echo $total_pages; ?></a>
				<?php endif; ?>
				
				<?php if ($page < $total_pages): ?>
					<span style="color: #ccc;">|</span>
					<a href="?page=<?php echo $total_pages; ?><?php echo $url_query; ?>" style="color: #3498db; text-decoration: none; padding: 0.5rem 0.75rem;">Sau</a>
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