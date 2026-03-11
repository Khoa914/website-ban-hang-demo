<?php
// categories-api.php (BẢN FINAL: FIX LỖI JSON + XÓA)

// Tắt hiển thị lỗi để tránh làm hỏng JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi DB"]);
    exit();
}
mysqli_set_charset($conn, "utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Lấy danh sách ---
if ($method === 'GET') {
    $sql = "SELECT * FROM categories ORDER BY parent_id ASC, id ASC";
    $result = $conn->query($sql);
    
    $categories = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
    echo json_encode(["success" => true, "data" => $categories]);
}

// --- POST: Thêm hoặc Xóa ---
else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 1. Thêm
    if (isset($input['action']) && $input['action'] == 'add') {
        $ten = $input['ten_danh_muc'];
        $parent_id = (int)$input['parent_id'];

        $stmt = $conn->prepare("INSERT INTO categories (ten_danh_muc, parent_id) VALUES (?, ?)");
        $stmt->bind_param("si", $ten, $parent_id);
        
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Thêm thành công!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Lỗi: " . $conn->error]);
        }
        $stmt->close();
    }
    
    // 2. Xóa
    else if (isset($input['action']) && $input['action'] == 'delete') {
        $id = (int)$input['id'];
        
        // Kiểm tra ràng buộc trước khi xóa
        $check_child = $conn->query("SELECT id FROM categories WHERE parent_id = $id");
        $check_product = $conn->query("SELECT id FROM products WHERE category_id = $id");

        if ($check_child->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Không thể xóa: Danh mục này đang chứa danh mục con!"]);
        } 
        else if ($check_product->num_rows > 0) {
            echo json_encode(["success" => false, "message" => "Không thể xóa: Đang có sản phẩm thuộc danh mục này!"]);
        }
        else {
            if ($conn->query("DELETE FROM categories WHERE id = $id")) {
                echo json_encode(["success" => true, "message" => "Đã xóa danh mục."]);
            } else {
                echo json_encode(["success" => false, "message" => "Lỗi SQL: " . $conn->error]);
            }
        }
    }
}
$conn->close();