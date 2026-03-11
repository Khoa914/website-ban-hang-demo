<?php
// manage-user-api.php: Xử lý Khóa / Mở khóa user

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

$user_id = $data['user_id'] ?? null;
$new_status = $data['new_status'] ?? null; // Sẽ là 0 hoặc 1
$admin_id = $_SESSION['user_id']; // Lấy ID của admin đang thao tác

// Kiểm tra dữ liệu
if ($user_id === null || ($new_status !== 0 && $new_status !== 1)) {
     echo json_encode(["success" => false, "message" => "Dữ liệu không hợp lệ."]);
     exit();
}

// Ngăn admin tự khóa chính mình
if ($user_id == $admin_id) {
     echo json_encode(["success" => false, "message" => "Không thể tự khóa tài khoản của chính mình."]);
     exit();
}


// ========== 4. THỰC THI UPDATE ==========
$sql = "UPDATE users SET is_active = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $new_status, $user_id);

if ($stmt->execute()) {
    $action_text = ($new_status == 0) ? "khóa" : "mở khóa";
    echo json_encode(["success" => true, "message" => "Đã $action_text tài khoản."]);
} else {
     echo json_encode(["success" => false, "message" => "Lỗi khi cập nhật CSDL."]);
}

$stmt->close();
$conn->close();
?>