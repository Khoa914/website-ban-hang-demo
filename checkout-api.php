<?php
// checkout-api.php: (NÂNG CẤP HOÀN CHỈNH - KIỂM TRA KHO VÀ TRỪ KHO)

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// ========== 1. KIỂM TRA ĐĂNG NHẬP ==========
if (!isset($_SESSION['da_dang_nhap']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Lỗi: Bạn cần đăng nhập để thanh toán.", "error_code" => "NOT_LOGGED_IN"]);
    exit();
}
$user_id = $_SESSION['user_id'];

// ========== 2. KẾT NỐI CƠ SỞ DỮ LIỆU ==========
$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL: " . $conn->connect_error]);
    exit();
}
mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

// ========== 3. LẤY GIỎ HÀNG HIỆN TẠI ==========
// Lấy sản phẩm trong giỏ (JOIN với products để lấy giá VÀ TÊN SẢN PHẨM)
$sql_cart = "SELECT p.id as product_id, p.ten, p.gia, ci.so_luong 
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            WHERE ci.user_id = ?";
            
$stmt_cart = $conn->prepare($sql_cart);
$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$result_cart = $stmt_cart->get_result();

$cart_items = [];
$tong_tien_don_hang = 0;

while ($row = $result_cart->fetch_assoc()) {
    $row['gia'] = (int) $row['gia'];
    $row['so_luong'] = (int) $row['so_luong'];
    $tong_tien_don_hang += ($row['gia'] * $row['so_luong']);
    $cart_items[] = $row;
}
$stmt_cart->close();

if (count($cart_items) === 0) {
    echo json_encode(["success" => false, "message" => "Giỏ hàng của bạn đang trống, không thể thanh toán."]);
    exit();
}

// ========== 4. XỬ LÝ ĐƠN HÀNG (Transaction) ==========
$conn->begin_transaction(); // BẮT ĐẦU TRANSACTION

try {
    // [MỚI] 4.1: KIỂM TRA KHO VÀ TRỪ KHO (Cực kỳ quan trọng)
    
    // Chuẩn bị 2 câu lệnh
    $sql_check_stock = "SELECT ten, so_luong FROM products WHERE id = ? FOR UPDATE"; // "FOR UPDATE" để khóa hàng, chống 2 người mua cùng lúc
    $stmt_check_stock = $conn->prepare($sql_check_stock);
    
    $sql_update_stock = "UPDATE products SET so_luong = so_luong - ? WHERE id = ?";
    $stmt_update_stock = $conn->prepare($sql_update_stock);

    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $so_luong_mua = $item['so_luong'];
        
        // A. Khóa và Kiểm tra hàng
        $stmt_check_stock->bind_param("i", $product_id);
        $stmt_check_stock->execute();
        $result_stock = $stmt_check_stock->get_result();
        $product_data = $result_stock->fetch_assoc();
        
        if (!$product_data || $product_data['so_luong'] < $so_luong_mua) {
            // Nếu hàng không tồn tại hoặc không đủ
            $ten_sp = $product_data['ten'] ?? "Sản phẩm (ID: $product_id)";
            $so_luong_con = $product_data['so_luong'] ?? 0;
            // Ném lỗi -> Lệnh catch sẽ bắt được và rollback
            throw new Exception("Rất tiếc, sản phẩm '$ten_sp' chỉ còn $so_luong_con cái. Vui lòng giảm số lượng trong giỏ hàng.");
        }
        
        // B. Nếu hàng đủ, Trừ kho
        $stmt_update_stock->bind_param("ii", $so_luong_mua, $product_id);
        if (!$stmt_update_stock->execute()) {
             throw new Exception("Lỗi khi cập nhật kho cho sản phẩm ID: $product_id.");
        }
    }
    $stmt_check_stock->close();
    $stmt_update_stock->close();

    // 4.2. Tạo 1 dòng trong bảng `don_hang`
    $trang_thai_mac_dinh = 'Chờ xác nhận'; 
    $sql_order = "INSERT INTO don_hang (user_id, tong_tien, trang_thai) VALUES (?, ?, ?)";
    $stmt_order = $conn->prepare($sql_order);
    $stmt_order->bind_param("iis", $user_id, $tong_tien_don_hang, $trang_thai_mac_dinh); 
    $stmt_order->execute();
    
    $order_id = $conn->insert_id; 
    $stmt_order->close();

    // 4.3. Thêm các sản phẩm vào `chi_tiet_don_hang`
    $sql_details = "INSERT INTO chi_tiet_don_hang (order_id, product_id, so_luong, gia_luc_mua) VALUES (?, ?, ?, ?)";
    $stmt_details = $conn->prepare($sql_details);
    foreach ($cart_items as $item) {
        $stmt_details->bind_param("iiii", $order_id, $item['product_id'], $item['so_luong'], $item['gia']);
        $stmt_details->execute();
    }
    $stmt_details->close();

    // 4.4. Xóa sạch giỏ hàng (`cart_items`) của người dùng
    $sql_clear_cart = "DELETE FROM cart_items WHERE user_id = ?";
    $stmt_clear = $conn->prepare($sql_clear_cart);
    $stmt_clear->bind_param("i", $user_id);
    $stmt_clear->execute();
    $stmt_clear->close();

    // 4.5. Nếu mọi thứ thành công
    $conn->commit(); // LƯU TẤT CẢ THAY ĐỔI
    
    echo json_encode([
        "success" => true, 
        "message" => "Đặt hàng thành công!",
        "order_id" => $order_id
    ]);

} catch (Exception $e) { // Dùng Exception thay vì mysqli_sql_exception
    // 4.6. Nếu có bất kỳ lỗi nào xảy ra (Kể cả lỗi hết hàng)
    $conn->rollback(); // HỦY BỎ TẤT CẢ
    
    echo json_encode([
        "success" => false, 
        // Trả về thông báo lỗi (ví dụ: "Sản phẩm A đã hết hàng")
        "message" => $e->getMessage() 
    ]);
}

$conn->close();
?>