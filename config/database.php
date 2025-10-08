<?php
// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tuyensinh');

// Hàm kết nối database
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        // Ensure required tables exist (idempotent)
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS students (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    username VARCHAR(50) NOT NULL UNIQUE,\n" .
                "    password VARCHAR(255) NOT NULL,\n" .
                "    email VARCHAR(120),\n" .
                "    full_name VARCHAR(120),\n" .
                "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            );
            // Core data tables required by homepage
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS universities (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    name VARCHAR(255) NOT NULL,\n" .
                "    code VARCHAR(20) UNIQUE NOT NULL,\n" .
                "    province VARCHAR(100) NOT NULL,\n" .
                "    address TEXT,\n" .
                "    website VARCHAR(255),\n" .
                "    phone VARCHAR(20),\n" .
                "    email VARCHAR(100),\n" .
                "    description TEXT,\n" .
                "    established_year YEAR,\n" .
                "    university_type ENUM('Công lập','Dân lập','Tư thục') DEFAULT 'Công lập',\n" .
                "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n" .
                "    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS majors (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    university_id INT NOT NULL,\n" .
                "    code VARCHAR(20) NOT NULL,\n" .
                "    moet_code VARCHAR(20) NULL,\n" .
                "    name VARCHAR(255) NOT NULL,\n" .
                "    description TEXT,\n" .
                "    training_level ENUM('Đại học','Cao đẳng','Thạc sĩ','Tiến sĩ') DEFAULT 'Đại học',\n" .
                "    duration_years INT DEFAULT 4,\n" .
                "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n" .
                "    FOREIGN KEY (university_id) REFERENCES universities(id) ON DELETE CASCADE\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            );
            // Bổ sung cột moet_code nếu chưa tồn tại
            try { $pdo->exec("ALTER TABLE majors ADD COLUMN moet_code VARCHAR(20) NULL"); } catch (Exception $e) { /* ignore if exists */ }
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS admission_scores (\n" .
                "    id INT AUTO_INCREMENT PRIMARY KEY,\n" .
                "    major_id INT NOT NULL,\n" .
                "    year YEAR NOT NULL,\n" .
                "    block VARCHAR(10) NOT NULL,\n" .
                "    min_score DECIMAL(4,2) NOT NULL,\n" .
                "    quota INT DEFAULT 0,\n" .
                "    note TEXT,\n" .
                "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n" .
                "    FOREIGN KEY (major_id) REFERENCES majors(id) ON DELETE CASCADE,\n" .
                "    UNIQUE KEY unique_major_year_block (major_id, year, block)\n" .
                ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
            );
            // Seed a default student if table is empty
            $count = (int)$pdo->query("SELECT COUNT(*) AS c FROM students")->fetch()['c'];
            if ($count === 0) {
                $pdo->prepare("INSERT INTO students (username, password, email, full_name) VALUES (?, ?, ?, ?)")
                    ->execute([
                        'student',
                        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                        'student@example.com',
                        'Sample Student'
                    ]);
            }
            // Seed minimal universities/majors/scores if database is empty
            $ucount = (int)$pdo->query("SELECT COUNT(*) AS c FROM universities")->fetch()['c'];
            if ($ucount === 0) {
                $pdo->exec(
                    "INSERT INTO universities (name, code, province, address, website, phone, email, description, established_year, university_type) VALUES\n" .
                    "('Đại học Bách khoa Hà Nội','BKA','Hà Nội','Số 1 Đại Cồ Việt','https://www.hust.edu.vn','024 3869 4242','info@hust.edu.vn','Demo seed',1956,'Công lập'),\n" .
                    "('Đại học Kinh tế Quốc dân','NEU','Hà Nội','207 Giải Phóng','https://www.neu.edu.vn','024 3628 0280','info@neu.edu.vn','Demo seed',1956,'Công lập');"
                );
                $pdo->exec(
                    "INSERT INTO majors (university_id, code, name, description, training_level, duration_years) VALUES\n" .
                    "(1,'IT01','Công nghệ thông tin','Seed','Đại học',4),\n" .
                    "(1,'EE01','Điện tử viễn thông','Seed','Đại học',4),\n" .
                    "(2,'BA01','Quản trị kinh doanh','Seed','Đại học',4);"
                );
                $year = date('Y');
                $pdo->exec(
                    "INSERT INTO admission_scores (major_id, year, block, min_score, quota, note) VALUES\n" .
                    "(1, $year, 'A00', 26.5, 100, 'Seed'),\n" .
                    "(2, $year, 'A00', 25.0, 80, 'Seed'),\n" .
                    "(3, $year, 'A00', 24.0, 120, 'Seed');"
                );
            }
        } catch (Exception $ignored) {
            // Silent: table creation seeding best-effort to avoid breaking the app
        }
        return $pdo;
    } catch (PDOException $e) {
        die("Lỗi kết nối database: " . $e->getMessage());
    }
}

// Hàm helper để escape string
function escape($string) {
    // Chấp nhận null và các kiểu không phải chuỗi để tránh cảnh báo deprecation
    if ($string === null) {
        return '';
    }
    if (!is_string($string)) {
        $string = (string)$string;
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Hàm helper để format điểm số
function formatScore($score) {
    return number_format($score, 2);
}

// Hàm helper để format số lượng
function formatNumber($number) {
    return number_format($number);
}
?>
