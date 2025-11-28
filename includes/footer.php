    </div>

    <!-- Chat Bubble Widget -->
    <?php include __DIR__ . '/chat_bubble.php'; ?>

    <!-- Footer main large area (logo, contacts, branches, map) -->
    <div class="footer-large">
        <div class="container">
            <div class="footer-grid">
                <!-- Left: logo, address, short contact and map -->
                <div class="footer-left">
                    <div class="logo">
                        <img src="<?php echo $base_path; ?>img/logo.jpg" alt="Logo">
                        <div>
                            <div style="font-weight:700; font-size:1.1rem;">Hệ thống hỗ trợ việc chọn trường</div>
                            <div style="font-size:0.9rem; opacity:0.9;">Tra cứu điểm chuẩn, nguyện vọng và dự đoán</div>
                        </div>
                    </div>

                    <div class="contact">
                        <div> Địa chỉ: Số 56 Thành Lộc 26, Quận 12, TP. Hồ Chí Minh</div>
                        <div> ĐT: 0908 878 473</div>
                        <div> Email: <a href="mailto:kiennguyen300803@gmail.com" style="color:white; text-decoration: underline;">kiennguyen300803@gmail.com</a></div>
                    </div>

                    <div class="map-box">
                        <iframe src="https://www.google.com/maps?q=56+Th%C3%A0nh+L%E1%BB%8Bch+26,+Qu%E1%BA%A7n+12,+Th%C3%A0nh+ph%E1%BB%91+H%E1%BB%93+Ch%C3%AD+Minh&z=17&output=embed" width="100%" height="180" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>

                <!-- Right: branches and panels -->
                <div class="footer-right">
                    <div class="branch-block">
                        <h5>Các cơ sở và phân hiệu</h5>
                        <div class="branch-item">London, United Kingdom <div style="opacity:0.85; font-weight:400;">Địa chỉ: 10 Downing St, Westminster, London SW1A 2AA, United Kingdom</div> <a class="btn-map" href="https://maps.google.com?q=10+Downing+St+London" target="_blank">Xem bản đồ</a></div>
                        <div class="branch-item">Paris, France <div style="opacity:0.85; font-weight:400;">Địa chỉ: 5 Avenue des Champs-Élysées, 75008 Paris, France</div> <a class="btn-map" href="https://maps.google.com?q=5+Avenue+des+Champs-Elysees+Paris" target="_blank">Xem bản đồ</a></div>
                        <div class="branch-item">Madrid, Spain <div style="opacity:0.85; font-weight:400;">Địa chỉ: Plaza Mayor, 28012 Madrid, Spain</div> <a class="btn-map" href="https://maps.google.com?q=Plaza+Mayor+Madrid" target="_blank">Xem bản đồ</a></div>
                    </div>

                    <div class="branch-block">
                        <h5>Phân hiệu & chi nhánh</h5>
                        <div class="branch-item">Berlin, Germany <div style="opacity:0.85; font-weight:400;">Địa chỉ: Alexanderplatz 1, 10178 Berlin, Germany</div> <a class="btn-map" href="https://maps.google.com?q=Alexanderplatz+1+Berlin" target="_blank">Xem bản đồ</a></div>
                        <div class="branch-item">Venice, Italy <div style="opacity:0.85; font-weight:400;">Địa chỉ: Piazza San Marco, 30124 Venice VE, Italy</div> <a class="btn-map" href="https://maps.google.com?q=Piazza+San+Marco+Venice" target="_blank">Xem bản đồ</a></div>
                        <div class="branch-item">Amsterdam, Netherlands <div style="opacity:0.85; font-weight:400;">Địa chỉ: Dam Square, 1012 Amsterdam, Netherlands</div> <a class="btn-map" href="https://maps.google.com?q=Dam+Square+Amsterdam" target="_blank">Xem bản đồ</a></div>
                    </div>

                    <div class="branch-block">
                        <h5>Liên hệ nhanh</h5>
                        <div class="branch-item">Hotline: 0908 878 473</div>
                        <div class="branch-item">Email: <a href="mailto:kiennguyen300803@gmail.com" style="color:white; text-decoration: underline;">kiennguyen300803@gmail.com</a></div>
                        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
                            <a class="btn-map" href="search_score">Tìm trường</a>
                            <a class="btn-map" href="recommend_major">Gợi ý ngành</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Small bottom bar with counters and copyright -->
            <div class="footer-bottom-bar">
                <div class="visit-stats">
                    <div class="stat"> <span class="value"><?php echo number_format($total_visitors ?? 0); ?></span> <span style="opacity:0.85;">Số lượng truy cập</span></div>
                    <div class="stat"> <span class="value"><?php echo $online_users ?? 0; ?></span> <span style="opacity:0.85;">Đang online</span></div>
                </div>
                <div style="opacity:0.9;">&copy; 2025 Hệ thống hỗ trợ việc chọn trường đại học - All rights reserved.</div>
            </div>
        </div>
    </div>
</body>
</html>
