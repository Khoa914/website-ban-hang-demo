<?php
// confirm-delivery-api.php: Xử lý User xác nhận đã nhận hàng

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// ========== 1. KIỂM TRA ĐĂNG NHẬP (Bắt buộc) ==========
if (!isset($_SESSION['da_dang_nhap']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Lỗi: Bạn cần đăng nhập."]);
    exit();
}
// Lấy user_id từ session
$user_id = $_SESSION['user_id'];

// ========== 2. KẾT NỐI CƠ SỞ DỮ LIỆU ==========
$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL."]);
    exit();
}
mysqli_set_charset($conn, "utf8mb4"); 

// ========== 3. LẤY DỮ LIỆU POST (Từ JS) ==========
$data = json_decode(file_get_contents('php://input'), true);

$order_id = $data['order_id'] ?? null;
$new_status = 'Hoàn tất'; // Trạng thái cuối cùng

if ($order_id === null) {
     echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
     exit();
}

// ========== 4. THỰC THI UPDATE (CỰC KỲ QUAN TRỌNG) ==========
// Cập nhật trạng thái VÀ KIỂM TRA user_id, trang_thai cũ
$sql = "UPDATE don_hang 
        SET trang_thai = ? 
        WHERE id = ? 
          AND user_id = ? 
          AND trang_thai = 'Đang vận chuyển'";
          
$stmt = $conn->prepare($sql);
// "sii" = string (Hoàn tất), int (order_id), int (user_id)
$stmt->bind_param("sii", $new_status, $order_id, $user_id); 

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        // Cập nhật thành công (tìm thấy 1 dòng khớp)
        echo json_encode(["success" => true, "message" => "Cảm ơn bạn! Đơn hàng đã được hoàn tất."]);
    } else {
        // Không có dòng nào được cập nhật (Có thể do sai user_id hoặc trạng thái không phải 'Đang vận chuyển')
        echo json_encode(["success" => false, "message" => "Không thể xác nhận đơn hàng này."]);
    }
} else {
     echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật CSDL."]);
}

$stmt->close();
$conn->close();
?>