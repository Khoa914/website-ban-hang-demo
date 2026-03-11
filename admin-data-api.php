<?php
// admin-data-api.php: Cung cấp dữ liệu Đơn hàng và Người dùng cho Admin
// [FIX LỖI FONT] Đã xóa bỏ mb_convert_encoding

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
$db_pass = "Khoa8971";        // Mật khẩu CSDL (Đã xác minh)
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL: " . $conn->connect_error]);
    exit();
}
// Đặt kết nối là UTF-8 (Cách làm đúng)
mysqli_set_charset($conn, "utf8mb4"); 
$conn->query("SET time_zone = '+07:00'");


// ========== 3. LẤY DỮ LIỆU DỰA TRÊN YÊU CẦU ==========
$type = isset($_GET['type']) ? $_GET['type'] : '';
$response = [];

// --- LẤY DANH SÁCH NGƯỜI DÙNG ---
// --- LẤY DANH SÁCH NGƯỜI DÙNG (Đã thêm is_active) ---
if ($type === 'users') {
    // [BƯỚC 3.1] Thêm 'is_active' vào SELECT
    $sql = "SELECT id, ho_ten, email, role, is_active FROM users ORDER BY id ASC";
    $result = $conn->query($sql);
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ho_ten'] = htmlspecialchars($row['ho_ten'] ?? '', ENT_QUOTES, 'UTF-8');
            // [BƯỚC 3.2] Ép kiểu
            $row['is_active'] = (int) $row['is_active']; 
            $data[] = $row;
        }
    }
    $response = ["success" => true, "data" => $data];
}

/* DÁN CODE MỚI NÀY VÀO THAY THẾ CHO KHỐI CODE BẠN VỪA XÓA */

// --- LẤY DANH SÁCH ĐƠN HÀNG (ĐÃ NÂNG CẤP ĐỂ LỌC THEO USER) ---
else if ($type === 'orders') {
    
    // Kiểm tra xem có yêu cầu lọc theo user_id không
    $user_id_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    $sql = "SELECT d.id, u.ho_ten, u.dia_chi, d.tong_tien, d.thoi_gian_dat, d.trang_thai 
        FROM don_hang d
        JOIN users u ON d.user_id = u.id";
            
    $params = [];
    $types = "";

    // Nếu có lọc, thêm điều kiện WHERE
    if ($user_id_filter) {
        $sql .= " WHERE d.user_id = ?";
        $params[] = $user_id_filter; // Thêm biến vào mảng
        $types .= "i"; // Thêm kiểu dữ liệu
    }
    
    $sql .= " ORDER BY d.thoi_gian_dat DESC";

    $stmt = $conn->prepare($sql);
    
    // Bind param nếu có (tức là nếu $types không rỗng)
    if (!empty($types)) {
        // Cần dùng mảng tham chiếu cho bind_param
        $bind_params = [$types];
        foreach ($params as &$p) {
            $bind_params[] = &$p;
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
     if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['ho_ten'] = htmlspecialchars($row['ho_ten'] ?? '', ENT_QUOTES, 'UTF-8');
            // THÊM DÒNG NÀY
            $row['dia_chi'] = htmlspecialchars($row['dia_chi'] ?? 'Chưa cập nhật', ENT_QUOTES, 'UTF-8');
            $row['tong_tien'] = (int) $row['tong_tien'];
            $row['trang_thai'] = htmlspecialchars($row['trang_thai'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
            $data[] = $row;
        }
     }
    $response = ["success" => true, "data" => $data];
    $stmt->close();
}

// --- LẤY CHI TIẾT 1 ĐƠN HÀNG ---
else if ($type === 'order_details' && isset($_GET['order_id'])) {
    $order_id = (int) $_GET['order_id'];
    
    $sql = "SELECT p.ten, ct.so_luong, ct.gia_luc_mua 
            FROM chi_tiet_don_hang ct
            LEFT JOIN products p ON ct.product_id = p.id
            WHERE ct.order_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
         // [ĐÃ SỬA] Chỉ dùng htmlspecialchars
         $row['ten'] = $row['ten'] ? htmlspecialchars($row['ten'], ENT_QUOTES, 'UTF-8') : '[Sản phẩm đã bị xóa]';
         $row['gia_luc_mua'] = (int) $row['gia_luc_mua'];
         $data[] = $row;
    }
    $stmt->close();
    $response = ["success" => true, "data" => $data];
}

else {
    $response = ["success" => false, "message" => "Yêu cầu không hợp lệ."];
}

$conn->close();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
// Không có thẻ đóng ?>