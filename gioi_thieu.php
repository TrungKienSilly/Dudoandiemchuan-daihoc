<?php
$page_title = 'Giới thiệu - Hệ thống hỗ trợ việc chọn trường đại học cho học sinh';
// Ensure common helpers (escape, DB connection helpers) are available
require_once 'config/database.php';
require_once 'includes/header.php';
?>

<style>
    .predict-container {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: calc(100vh - 200px);
        padding: 40px 20px;
    }
    
    .predict-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 40px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .predict-header h1 {
        font-size: 2.5rem;
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 700;
    }
    
    .predict-header p {
        font-size: 1.2rem;
        color: #7f8c8d;
        margin: 0;
    }
    
    .info-box {
        background: white;
        padding: 30px;
        border-radius: 16px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        border-left: 5px solid #3498db;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .info-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    
    .info-box h4 {
        color: #3498db;
        font-size: 1.4rem;
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
    }
    
    .info-box h4::before {
        content: '📌';
        margin-right: 10px;
        font-size: 1.5rem;
    }
    
    .info-box p, .info-box ul {
        color: #555;
        line-height: 1.8;
        font-size: 1rem;
    }
    
    .info-box ul {
        padding-left: 20px;
    }
    
    .info-box li {
        margin-bottom: 10px;
    }
    
    .info-box a {
        color: #3498db;
        text-decoration: none;
    }
    
    .info-box a:hover {
        text-decoration: underline;
    }
    
    @media (max-width: 768px) {
        .predict-container {
            padding: 20px 10px;
        }
        
        .predict-header {
            padding: 30px 20px;
        }
        
        .predict-header h1 {
            font-size: 2rem;
        }
        
        .info-box {
            padding: 20px;
        }
    }
</style>

<div class="predict-container">

    <div class="intro-hero">
        <div class="hero-left">
            <h2>Giá trị cốt lõi</h2>
            <p>Với mục tiêu xây dựng một nền tảng hữu ích cho thí sinh và phụ huynh, <strong>Tuyensinh247</strong> cung cấp dữ liệu điểm chuẩn, công cụ tham chiếu và đề xuất nguyện vọng, giúp bạn tự tin hơn khi lựa chọn ngành học và trường đại học phù hợp. Giá trị cốt lõi của dự án: Đổi mới - Đoàn kết - Nhân văn.</p>
            <button class="btn-hero">Xem chi tiết</button>
        </div>
        <div class="hero-right">
            <img src="img/banner.jpg" alt="Hình minh hoạ" class="hero-image">
            <div style="width:14px"></div>
            <div class="logo-card">
                <img src="img/logo.jpg" alt="Logo hệ thống">
            </div>
        </div>
    </div>

    <div class="value-cards">
        <div class="value-card">
            <h4>ĐỔI MỚI</h4>
            <p>Ứng dụng các phương pháp phân tích dữ liệu và thuật toán để cung cấp các đề xuất thông minh cho thí sinh khi chọn trường và ngành học.</p>
        </div>
        <div class="value-card">
            <h4>ĐOÀN KẾT</h4>
            <p>Tạo nên cộng đồng người học, phụ huynh và chuyên gia chia sẻ dữ liệu, kinh nghiệm và hỗ trợ lẫn nhau trong quá trình xét tuyển.</p>
        </div>
        <div class="value-card">
            <h4>NHÂN VĂN</h4>
            <p>Luôn đặt quyền lợi người học lên hàng đầu, minh bạch dữ liệu và đảm bảo các khuyến nghị dựa trên thông tin chính xác và có trách nhiệm.</p>
        </div>
    </div>

    <p style="margin-top:18px; text-align: center;"><a class="btn-cta" href="search_score" aria-label="Quay về trang chính">Quay về trang chính</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
