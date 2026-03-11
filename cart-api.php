<?php
// cart-api.php: Đã sửa logic Cập nhật số lượng (PUT -> POST) từ code gốc của bạn

// Bắt buộc phải bắt đầu session để lấy user_id
session_start();

// Ép PHP hiển thị lỗi (để debug)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
// [SỬA ĐỔI] Chỉ cho phép GET và POST
header("Access-Control-Allow-Methods: GET, POST"); 

// ========== KIỂM TRA ĐĂNG NHẬP ==========
if (!isset($_SESSION['da_dang_nhap']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Lỗi: Bạn cần đăng nhập để sử dụng giỏ hàng.", "error_code" => "NOT_LOGGED_IN"]);
    exit();
}

$user_id = $_SESSION['user_id']; // Lấy user_id từ session

// ========== KẾT NỐI CƠ SỞ DỮ LIỆU ==========
$db_host = "sql306.infinityfree.com";
$db_user = "if0_40189376";
$db_pass = "Khoa8971";        // (Mật khẩu đã xác minh)
$db_name = "if0_40189376_tzshop";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL: " . $conn->connect_error]);
    exit();
}

mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

// ========== XỬ LÝ YÊU CẦU ==========
$method = $_SERVER['REQUEST_METHOD'];
// Đọc dữ liệu JSON thô từ body của request
$data = json_decode(file_get_contents('php://input'), true);
// Lấy action từ data (vì gửi bằng JSON body)
$action = isset($data['action']) ? $data['action'] : null;

// 1. LẤY GIỎ HÀNG (GET)
if ($method === 'GET') {
    // Lấy tất cả sản phẩm trong giỏ của user_id này
    // Cần JOIN với bảng products để lấy thông tin chi tiết (tên, giá, hình ảnh)
    $sql = "SELECT p.id, p.ten, p.gia, p.hinh, ci.so_luong
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.user_id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) { 
        echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị SQL (GET): " . $conn->error]);
        exit();
    }
    $stmt->bind_param("i", $user_id);
    if(!$stmt->execute()){ 
         echo json_encode(["success" => false, "message" => "Lỗi thực thi SQL (GET): " . $stmt->error]);
         exit();
    }
    $result = $stmt->get_result();

    $cart = [];
    while ($row = $result->fetch_assoc()) {
        // Fix kiểu dữ liệu trước khi gửi đi
        $row['id'] = (int) $row['id'];
        $row['gia'] = (int) $row['gia'];
        $row['so_luong'] = (int) $row['so_luong'];
        $row['ten'] = htmlspecialchars(mb_convert_encoding($row['ten'] ?? '', 'UTF-8', 'ISO-8859-1'), ENT_QUOTES, 'UTF-8');

        $cart[] = $row;
    }

    $stmt->close();
    echo json_encode(["success" => true, "cart" => $cart], JSON_UNESCAPED_UNICODE); 
}

// 2. THÊM / XÓA / CẬP NHẬT SẢN PHẨM (POST) - [ĐÃ SỬA]
else if ($method === 'POST') {
    
    // ===== HÀNH ĐỘNG XÓA =====
    if ($action === 'delete_cart_item' && isset($data['product_id'])) {
        $product_id = (int) $data['product_id'];

        $sql_delete = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
         if (!$stmt_delete) {
            echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị SQL (DELETE): " . $conn->error]);
            exit();
        }
        $stmt_delete->bind_param("ii", $user_id, $product_id);

        if($stmt_delete->execute()){
             if ($stmt_delete->affected_rows > 0) {
                 echo json_encode(["success" => true, "message" => "Đã xóa sản phẩm khỏi giỏ."]);
             } else {
                 echo json_encode(["success" => false, "message" => "Không tìm thấy sản phẩm trong giỏ để xóa."]);
             }
        } else {
             echo json_encode(["success" => false, "message" => "Lỗi khi xóa khỏi CSDL: " . $stmt_delete->error]);
        }
        $stmt_delete->close();
    }
    
    // ===== [MỚI] HÀNH ĐỘNG CẬP NHẬT SỐ LƯỢNG (Dùng POST) =====
    else if ($action === 'update_quantity' && isset($data['product_id']) && isset($data['so_luong'])) {
        $product_id = (int) $data['product_id'];
        $new_so_luong = (int) $data['so_luong'];

        if ($new_so_luong <= 0) {
            // Nếu số lượng <= 0, coi như là xóa
            $sql_delete = "DELETE FROM cart_items WHERE user_id = ? AND product_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            if (!$stmt_delete) { 
                 echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị SQL (Delete Qty): " . $conn->error]);
                 exit();
            }
            $stmt_delete->bind_param("ii", $user_id, $product_id);
            if($stmt_delete->execute()){
                 echo json_encode(["success" => true, "message" => "Đã xóa sản phẩm khỏi giỏ (số lượng <= 0)."]);
            } else {
                 echo json_encode(["success" => false, "message" => "Lỗi xóa SP (Qty <= 0): " . $stmt_delete->error]);
            }
            $stmt_delete->close();
            
        } else {
            // Cập nhật số lượng mới
            $sql_update = "UPDATE cart_items SET so_luong = ? WHERE user_id = ? AND product_id = ?";
            $stmt_update = $conn->prepare($sql_update);
             if (!$stmt_update) { 
                 echo json_encode(["success" => false, "message" => "Lỗi chuẩn bị SQL (Update Qty): " . $conn->error]);
                 exit();
             }
            $stmt_update->bind_param("iii", $new_so_luong, $user_id, $product_id);
            if ($stmt_update->execute()) {
                 echo json_encode(["success" => true, "message" => "Đã cập nhật số lượng."]);
            } else {
                 echo json_encode(["success" => false, "message" => "Lỗi cập nhật số lượng CSDL: " . $stmt_update->error]);
            }
            $stmt_update->close();
        }
    }

    // ===== HÀNH ĐỘNG THÊM (Mặc định nếu không phải delete hoặc update) =====
    else if (isset($data['product_id'])) {
        $product_id = (int) $data['product_id'];
        $so_luong_them = isset($data['so_luong']) ? (int) $data['so_luong'] : 1;
        if ($so_luong_them <= 0) $so_luong_them = 1; // Đảm bảo số lượng thêm > 0

        // Kiểm tra xem sản phẩm đã có trong giỏ chưa
        $sql_check = "SELECT id, so_luong FROM cart_items WHERE user_id = ? AND product_id = ?";
        $stmt_check = $conn->prepare($sql_check);
         if (!$stmt_check) { /* ... Kiểm tra lỗi ... */ exit(); }
        $stmt_check->bind_param("ii", $user_id, $product_id);
        if(!$stmt_check->execute()){ /* ... Kiểm tra lỗi ... */ exit(); }
        $result = $stmt_check->get_result();

        if ($row = $result->fetch_assoc()) {
            // Đã có: Cập nhật số lượng
            $cart_item_id = $row['id'];
            $new_so_luong = $row['so_luong'] + $so_luong_them;

            $sql_update = "UPDATE cart_items SET so_luong = ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
             if (!$stmt_update) { /* ... Kiểm tra lỗi ... */ exit(); }
            $stmt_update->bind_param("ii", $new_so_luong, $cart_item_id);
            $stmt_update->execute(); 
            $stmt_update->close();
        } else {
            // Chưa có: Thêm mới
            $sql_insert = "INSERT INTO cart_items (user_id, product_id, so_luong) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if (!$stmt_insert) { /* ... Kiểm tra lỗi ... */ exit(); }
            $stmt_insert->bind_param("iii", $user_id, $product_id, $so_luong_them);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();
        echo json_encode(["success" => true, "message" => "Đã thêm/cập nhật sản phẩm vào giỏ."], JSON_UNESCAPED_UNICODE);
    }
    // Nếu POST mà không có product_id hoặc action không hợp lệ
    else {
         echo json_encode(["success" => false, "message" => "Dữ liệu POST không hợp lệ."]);
    }

}

// 3. [XÓA] BỎ PHẦN XỬ LÝ PUT CŨ
// else if ($method === 'PUT' && isset($data['product_id']) && isset($data['so_luong'])) { ... }

// Nếu không khớp phương thức nào
else {
    echo json_encode(["success" => false, "message" => "Yêu cầu không hợp lệ hoặc phương thức không được hỗ trợ."]);
}

$conn->close();
// Không có thẻ đóng ?>