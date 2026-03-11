<?php
// user-profile-api.php: Cung cấp thông tin user và lịch sử đơn hàng

session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// ========== 1. KIỂM TRA ĐĂNG NHẬP ==========
if (!isset($_SESSION['da_dang_nhap']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Lỗi: Bạn cần đăng nhập."]);
    exit();
}
// Lấy ID của user đang đăng nhập
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
$conn->query("SET time_zone = '+07:00'");

// Chuẩn bị response
$response = [
    "success" => false,
    "userInfo" => null,
    "orderHistory" => []
];

try {
   // ========== 3. LẤY THÔNG TIN TÀI KHOẢN (Đã thêm SĐT + ĐỊA CHỈ) ==========
    
    // [THAY ĐỔI] Thêm `so_dien_thoai`, `dia_chi`
    $sql_user = "SELECT ho_ten, email, so_dien_thoai, dia_chi FROM users WHERE id = ?";
    $stmt_user = $conn->prepare($sql_user);
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($row_user = $result_user->fetch_assoc()) {
        $row_user['ho_ten'] = htmlspecialchars($row_user['ho_ten'], ENT_QUOTES, 'UTF-8');
        // [THAY ĐỔI] Thêm 2 dòng xử lý
        $row_user['so_dien_thoai'] = htmlspecialchars($row_user['so_dien_thoai'] ?? 'Chưa cập nhật', ENT_QUOTES, 'UTF-8');
        $row_user['dia_chi'] = htmlspecialchars($row_user['dia_chi'] ?? 'Chưa cập nhật', ENT_QUOTES, 'UTF-8');
        
        $response['userInfo'] = $row_user;
    }
    $stmt_user->close();

    // ========== 4. LẤY LỊCH SỬ ĐƠN HÀNG (Đã thêm TRANG_THAI) ==========
    
    // ========== 4. LẤY LỊCH SỬ ĐƠN HÀNG (ĐÃ thêm TRANG_THAI) ==========
    
    // [THAY ĐỔI] Thêm `trang_thai` vào câu SELECT
    $sql_orders = "SELECT id, tong_tien, thoi_gian_dat, trang_thai 
                   FROM don_hang 
                   WHERE user_id = ? 
                   ORDER BY thoi_gian_dat DESC";
                   
    $stmt_orders = $conn->prepare($sql_orders);
    $stmt_orders->bind_param("i", $user_id);
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();

    while ($row_order = $result_orders->fetch_assoc()) {
        $row_order['tong_tien'] = (int) $row_order['tong_tien'];
        // [THAY ĐỔI] Lấy và xử lý 'trang_thai'
        $row_order['trang_thai'] = htmlspecialchars($row_order['trang_thai'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $response['orderHistory'][] = $row_order;
    }
    $stmt_orders->close();

    $response['success'] = true;

} catch (Exception $e) {
    $response['message'] = "Lỗi máy chủ: " . $e->getMessage();
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>