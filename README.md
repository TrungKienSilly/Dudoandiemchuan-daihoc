# Hệ thống Dự đoán Điểm chuẩn Đại học

Hệ thống tra cứu và dự đoán điểm chuẩn tuyển sinh đại học với tích hợp AI thông minh, giúp thí sinh đưa ra quyết định chọn ngành, chọn trường phù hợp.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php)](https://www.php.net/)
[![Python](https://img.shields.io/badge/Python-3.10+-3776AB?logo=python)](https://www.python.org/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql)](https://www.mysql.com/)

## Tính năng chính

### Tra cứu thông tin
- **Tra cứu điểm chuẩn**: Xem điểm chuẩn các năm theo trường, ngành, khối thi
- **Thông tin trường đại học**: Danh sách 10+ trường đại học lớn tại Việt Nam
- **Thông tin ngành học**: Chi tiết 50+ ngành đào tạo phổ biến
- **Lịch sử điểm chuẩn**: Theo dõi xu hướng điểm chuẩn qua các năm

### Dự đoán thông minh với AI
- **Phân tích xu hướng**: AI phân tích xu hướng điểm chuẩn ngành học
- **Đánh giá khả năng**: So sánh điểm thí sinh với điểm chuẩn
- **Gợi ý 3 lời khuyên**: Tư vấn cụ thể về nguyện vọng và phương án dự phòng
- **Kết luận**: Đề xuất nên giữ hay đổi nguyện vọng
- **Hỗ trợ 2 AI**: Groq (Llama 3.3 70B) và Google Gemini 2.0 Flash

### Chat AI tư vấn
- **Chat bubble**: Widget chat nổi ở góc phải màn hình
- **Trả lời tức thì**: Hỏi đáp nhanh về tuyển sinh, ngành học, điểm chuẩn
- **Câu hỏi gợi ý**: 3 câu hỏi thường gặp để bắt đầu hội thoại

### Dành cho học sinh
- **Đăng ký/Đăng nhập**: Tài khoản cá nhân cho học sinh
- **Lưu lịch sử**: Theo dõi các lần tra cứu và dự đoán
- **Giao diện thân thiện**: Thiết kế hiện đại, dễ sử dụng

### Dành cho quản trị viên
- **Quản lý trường đại học**: Thêm, sửa, xóa thông tin trường
- **Quản lý ngành học**: CRUD đầy đủ cho ngành đào tạo
- **Quản lý điểm chuẩn**: Cập nhật điểm chuẩn hàng năm
- **Dashboard**: Thống kê tổng quan hệ thống

## Kiến trúc hệ thống

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT (Browser)                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │
│  │   PHP Pages  │  │  JavaScript  │  │   CSS/HTML   │   │
│  └──────────────┘  └──────────────┘  └──────────────┘   │
└─────────────────────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   BACKEND SERVERS                       │
│  ┌────────────────────────┐  ┌────────────────────────┐ │
│  │    PHP Backend         │  │  Python Flask AI API   │ │
│  │  (Apache/WAMP)         │  │  (Port 5000)           │ │
│  │  - User Management     │  │  - AI Analysis         │ │
│  │  - Data Processing     │  │  - Chat Bot            │ │
│  │  - Admin Functions     │  │  - Groq Integration    │ │
│  └────────────────────────┘  └────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
                         │
                         ▼
┌────────────────────────────────────────────────────────┐
│                  DATABASE & AI SERVICES                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │   MySQL DB   │  │  Groq API    │  │  Gemini API  │  │
│  │  - 4 tables  │  │  (Primary)   │  │  (Fallback)  │  │
│  └──────────────┘  └──────────────┘  └──────────────┘  │
└────────────────────────────────────────────────────────┘
```

## Cấu trúc Database

### Bảng `universities` (Thông tin trường đại học)
```sql
- id: INT (Primary Key)
- name: VARCHAR(255) - Tên trường
- code: VARCHAR(20) - Mã trường (unique)
- province: VARCHAR(100) - Tỉnh/Thành phố
- address: TEXT - Địa chỉ chi tiết
- website: VARCHAR(255)
- phone: VARCHAR(20)
- email: VARCHAR(100)
- description: TEXT - Mô tả về trường
- established_year: YEAR - Năm thành lập
- university_type: ENUM('Công lập','Dân lập','Tư thục')
```

### Bảng `majors` (Ngành đào tạo)
```sql
- id: INT (Primary Key)
- university_id: INT (Foreign Key → universities.id)
- code: VARCHAR(20) - Mã ngành
- name: VARCHAR(255) - Tên ngành
- description: TEXT - Mô tả ngành
- training_level: ENUM('Đại học','Cao đẳng','Thạc sĩ','Tiến sĩ')
- duration_years: INT - Số năm đào tạo (4, 5, 6)
```

### Bảng `admission_scores` (Điểm chuẩn)
```sql
- id: INT (Primary Key)
- major_id: INT (Foreign Key → majors.id)
- year: YEAR - Năm tuyển sinh
- block: VARCHAR(10) - Khối thi (A00, A01, B00, D01...)
- min_score: DECIMAL(4,2) - Điểm chuẩn
- quota: INT - Chỉ tiêu tuyển sinh
- note: TEXT - Ghi chú
- UNIQUE(major_id, year, block)
```

### Bảng `students` (Tài khoản học sinh)
```sql
- id: INT (Primary Key)
- username: VARCHAR(50) (unique)
- password: VARCHAR(255) - Mã hóa bcrypt
- email: VARCHAR(120)
- full_name: VARCHAR(120)
- created_at: TIMESTAMP
```

### Bảng `admin_users` (Quản trị viên)
```sql
- id: INT (Primary Key)
- username: VARCHAR(50) (unique)
- password: VARCHAR(255) - Mã hóa bcrypt
- email: VARCHAR(100)
- full_name: VARCHAR(100)
- role: ENUM('admin', 'moderator')
```

## Cấu trúc thư mục

```
tuyensinh/
├── admin/                    # Khu vực quản trị
│   ├── index.php            # Dashboard admin
│   ├── login.php            # Đăng nhập admin
│   ├── universities.php     # Quản lý trường
│   ├── majors.php           # Quản lý ngành
│   ├── scores.php           # Quản lý điểm chuẩn
│   └── admin.css            # CSS riêng cho admin
│
├── student/                  # Khu vực học sinh
│   ├── index.php            # Trang chủ học sinh
│   ├── login.php            # Đăng nhập
│   ├── register.php         # Đăng ký
│   └── logout.php           # Đăng xuất
│
├── assets/                   # Tài nguyên tĩnh
│   └── css/
│       └── style.css        # CSS chính
│
├── config/                   # Cấu hình
│   ├── database.php         # Kết nối MySQL
│   └── ai.php               # Cấu hình AI API
│
├── database/                 # Database scripts
│   ├── tuyensinh.sql        # Schema database
│   └── sample_data.sql      # Dữ liệu mẫu
│
├── includes/                 # Components dùng chung
│   ├── header.php           # Header & navigation
│   ├── footer.php           # Footer
│   └── chat_bubble.php      # Widget chat AI
│
├── img/                      # Hình ảnh
│   ├── banner.jpg           # Banner trang chủ
│   └── chat-box-flat-design...jpg  # Icon chat
│
├── ai_api.py                # Python Flask AI server
├── index.php                # Trang chủ chính
├── predict_score.php        # Dự đoán điểm chuẩn
├── search_score.php         # Tra cứu điểm chuẩn
├── search_major.php         # Tìm kiếm ngành
├── search_university.php    # Tìm kiếm trường
├── university.php           # Chi tiết trường
├── get_exam_blocks.php      # API: Lấy khối thi
├── get_majors.php           # API: Lấy danh sách ngành
├── requirements.txt         # Python dependencies
├── .env                     # Environment variables (local)
├── .env.example             # Template cho .env
└── .gitignore               # Git ignore rules
```

## Cài đặt và Triển khai

### Yêu cầu hệ thống

- **PHP**: >= 8.0
- **Python**: >= 3.10
- **MySQL**: >= 8.0
- **Apache/WAMP/XAMPP**: Web server
- **Composer**: (tùy chọn) Nếu cần dependencies PHP

### Bước 1: Clone repository

```bash
git clone https://github.com/TrungKienSilly/Dudoandiemchuan-daihoc.git
cd Dudoandiemchuan-daihoc
```

### Bước 2: Cấu hình Database

1. Tạo database MySQL:
```sql
CREATE DATABASE tuyensinh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import schema và dữ liệu mẫu:
```bash
mysql -u root -p tuyensinh < database/tuyensinh.sql
mysql -u root -p tuyensinh < database/sample_data.sql
```

3. Cập nhật thông tin database trong `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'tuyensinh');
```

### Bước 3: Cấu hình AI API Keys

1. Copy file `.env.example` thành `.env`:
```bash
cp .env.example .env
```

2. Điền API keys vào file `.env`:
```env
# Lấy từ https://aistudio.google.com/app/apikey
GEMINI_API_KEY=your_gemini_api_key_here

# Lấy từ https://console.groq.com/keys
GROQ_API_KEY=your_groq_api_key_here
```

### Bước 4: Cài đặt Python dependencies

1. Tạo virtual environment (khuyến nghị):
```bash
python -m venv .venv
```

2. Kích hoạt virtual environment:
```bash
# Windows
.venv\Scripts\activate

# Linux/Mac
source .venv/bin/activate
```

3. Cài đặt packages:
```bash
pip install -r requirements.txt
```

### Bước 5: Chạy ứng dụng

1. **Khởi động Python AI Server**:
```bash
python ai_api.py
```
Server sẽ chạy tại `http://localhost:5000`

2. **Khởi động PHP/Apache**:
   - Nếu dùng WAMP/XAMPP: Bật Apache từ control panel
   - Truy cập: `http://localhost/tuyensinh`

3. **Mở trình duyệt**:
   - Trang chủ: `http://localhost/tuyensinh/index.php`
   - Admin: `http://localhost/tuyensinh/admin/`
   - Student: `http://localhost/tuyensinh/student/`

## Tài khoản mặc định

### Quản trị viên
- **Username**: `admin`
- **Password**: `password`

### Học sinh
- **Username**: `student`
- **Password**: `password`

**Lưu ý**: Đổi mật khẩu ngay sau khi đăng nhập lần đầu!

## API Documentation

### Python Flask AI API

#### POST `/analyze`
Phân tích dự đoán điểm chuẩn với AI

**Request Body:**
```json
{
  "totalScore": 25.5,
  "examBlock": "A00",
  "universityName": "Đại học Bách Khoa Hà Nội",
  "majorName": "Công nghệ thông tin",
  "historicalScores": [
    {"year": "2024", "score": "27.0"},
    {"year": "2023", "score": "26.5"}
  ],
  "provider": "groq"
}
```

**Response:**
```json
{
  "success": true,
  "analysis": {
    "trend_analysis": "Xu hướng điểm chuẩn...",
    "recommendations": ["Lời khuyên 1", "Lời khuyên 2", "Lời khuyên 3"],
    "conclusion": "Kết luận và đề xuất...",
    "probability": 75
  },
  "provider": "groq"
}
```

#### POST `/chat`
Chat với AI tư vấn

**Request Body:**
```json
{
  "message": "Ngành CNTT có triển vọng không?",
  "provider": "groq"
}
```

**Response:**
```json
{
  "success": true,
  "response": "Ngành CNTT có triển vọng rất tốt...",
  "provider": "groq"
}
```

#### GET `/health`
Kiểm tra trạng thái server

**Response:**
```json
{
  "status": "ok",
  "service": "AI Backend (Gemini & Groq)"
}
```

### PHP API Endpoints

#### GET `/get_majors.php?university_id=1`
Lấy danh sách ngành của trường

**Response:**
```json
{
  "success": true,
  "majors": [
    {"id": 1, "code": "IT01", "name": "Công nghệ thông tin"},
    {"id": 2, "code": "EE01", "name": "Điện tử viễn thông"}
  ]
}
```

#### GET `/get_exam_blocks.php?major_id=1`
Lấy danh sách khối thi của ngành

**Response:**
```json
{
  "success": true,
  "blocks": ["A00", "A01", "D01"]
}
```

## Giao diện người dùng

### Trang chủ
- Banner với thông tin hệ thống
- 2 tab: "Tìm theo điểm" và "Tìm theo trường"
- Form tra cứu nhanh với dropdown cascading
- Hiển thị kết quả dạng bảng với phân trang

### Trang dự đoán điểm
- Form nhập điểm 3 môn + chọn trường/ngành/khối
- Hiển thị điểm chuẩn các năm gần nhất
- Đánh giá khả năng đậu (Cao/Trung bình/Thấp)
- **AI tự động phân tích** ngay sau khi dự đoán:
  - Xu hướng điểm chuẩn
  - 3 lời khuyên cụ thể
  - Kết luận nên giữ/đổi nguyện vọng

### Chat Bubble AI
- Icon chat nổi ở góc phải dưới
- Animation float nhẹ nhàng
- Click để mở chat box
- 3 câu hỏi gợi ý ban đầu
- Typing indicator khi AI đang trả lời
- Hiển thị avatar và thời gian tin nhắn

### Admin Dashboard
- Thống kê tổng quan (số trường, ngành, điểm chuẩn)
- Menu điều hướng rõ ràng
- CRUD forms với validation
- Bảng dữ liệu với search và pagination

## Cấu hình nâng cao

### Thay đổi AI Provider mặc định

Trong `ai_api.py`:
```python
DEFAULT_PROVIDER = 'groq'  # Đổi thành 'gemini' nếu muốn
```

### Tăng/giảm độ dài response AI

Trong `ai_api.py`, function `call_ai_model()`:
```python
# Groq
max_tokens=500,  # Tăng lên 1000, 2000...

# Gemini
# Chỉnh trong generation_config
```

### Thêm trường đại học mới

Vào Admin → Universities → Thêm mới, hoặc chạy SQL:
```sql
INSERT INTO universities (name, code, province, address, website, phone, email, description, established_year, university_type)
VALUES ('Tên trường', 'CODE', 'Tỉnh/TP', 'Địa chỉ', 'website', 'phone', 'email', 'Mô tả', 2000, 'Công lập');
```

## Troubleshooting

### Lỗi: "Cannot connect to AI server"
- Kiểm tra Python server có đang chạy: `python ai_api.py`
- Kiểm tra port 5000 có bị chiếm: `netstat -ano | findstr :5000`
- Xem console log trong browser (F12)

### Lỗi: Database connection failed
- Kiểm tra MySQL service đã bật chưa
- Xác nhận username/password trong `config/database.php`
- Kiểm tra database `tuyensinh` đã tạo chưa

### Lỗi: API keys invalid
- Xác nhận đã tạo file `.env` từ `.env.example`
- Kiểm tra API keys có đúng không
- Groq: https://console.groq.com/keys
- Gemini: https://aistudio.google.com/app/apikey

### Chat bubble không hiện
- Kiểm tra file `includes/chat_bubble.php` có tồn tại
- Xem Console log (F12) có lỗi JavaScript không
- Đảm bảo `includes/footer.php` đã include chat_bubble

## Đóng góp

Chúng tôi hoan nghênh mọi đóng góp! Để contribute:

1. Fork repository
2. Tạo branch mới: `git checkout -b feature/TenTinhNang`
3. Commit changes: `git commit -m 'Add TinhNang'`
4. Push to branch: `git push origin feature/TenTinhNang`
5. Tạo Pull Request

### Coding Standards
- **PHP**: Tuân theo PSR-12
- **Python**: Tuân theo PEP 8
- **JavaScript**: ES6+ syntax
- **SQL**: Sử dụng prepared statements, tránh SQL injection

## License

Dự án này được phát hành dưới [MIT License](LICENSE).

## Tác giả

- **TrungKienSilly** - [GitHub](https://github.com/TrungKienSilly)

## Lời cảm ơn

- [Google Gemini](https://ai.google.dev/) - AI API
- [Groq](https://groq.com/) - Fast inference AI
- [Flask](https://flask.palletsprojects.com/) - Python web framework
- [Bootstrap](https://getbootstrap.com/) - CSS framework (nếu dùng)
- Cộng đồng developers Việt Nam

## Liên hệ và Hỗ trợ

- **Repository**: https://github.com/TrungKienSilly/Dudoandiemchuan-daihoc
- **Issues**: https://github.com/TrungKienSilly/Dudoandiemchuan-daihoc/issues
- **Email**: (Thêm email của bạn nếu muốn)

---

Nếu dự án hữu ích, hãy cho một Star để ủng hộ!
