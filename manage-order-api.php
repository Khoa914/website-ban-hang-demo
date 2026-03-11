<?php
// manage-order-api.php: Xử lý cập nhật trạng thái đơn hàng

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// ========== 1. KIỂM TRA QUYỀN ADMIN ==========
if (!isset($_SESSION['da_dang_nhap']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Lỗi: Không có quyền truy cập."]);
    exit();
}

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
$new_status = $data['new_status'] ?? null; // Sẽ là 'Đang vận chuyển'

// Kiểm tra dữ liệu
if ($order_id === null || empty($new_status)) {
     echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
     exit();
}

// ========== 4. THỰC THI UPDATE ==========
// Chỉ cập nhật nếu trạng thái hiện tại là "Chờ xác nhận"
$sql = "UPDATE don_hang SET trang_thai = ? WHERE id = ? AND trang_thai = 'Chờ xác nhận'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_status, $order_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["success" => true, "message" => "Đã cập nhật đơn hàng thành: $new_status"]);
    } else {
        echo json_encode(["success" => false, "message" => "Đơn hàng không ở trạng thái 'Chờ xác nhận' hoặc không tìm thấy."]);
    }
} else {
     echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật CSDL."]);
}

$stmt->close();
$conn->close();
?>