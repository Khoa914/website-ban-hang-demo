<?php
// check-admin.php: Đã tối ưu (Đọc role từ Session) + FIX LỖI FONT

session_start();
header('Content-Type: application/json');

// Chuẩn bị response mặc định
$response = ["loggedIn" => false, "role" => "guest", "name" => ""];

// Đọc trực tiếp từ session
if (isset($_SESSION['da_dang_nhap']) && $_SESSION['da_dang_nhap'] === true) {
    $response['loggedIn'] = true;
    
    // [FIX LỖI FONT]
    // Lấy tên từ session
    $name = $_SESSION['user_name'] ?? 'User';
    
    // Kiểm tra xem chuỗi có phải là UTF-8 hợp lệ không
    if (!mb_check_encoding($name, 'UTF-8')) {
        // Nếu không, giả định nó là latin1/ISO-8859-1 (lỗi phổ biến) và chuyển về UTF-8
        $name = mb_convert_encoding($name, 'UTF-8', 'ISO-8859-1');
    }
    
    // Chỉ gán vào response sau khi đã đảm bảo là UTF-8
    $response['name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $response['role'] = $_SESSION['role'] ?? 'user'; // Lấy role từ session
}

// Không cần kết nối CSDL

// Thêm cờ JSON_UNESCAPED_UNICODE để đảm bảo tiếng Việt hiển thị đúng
echo json_encode($response, JSON_UNESCAPED_UNICODE);
// Không có thẻ đóng ?>