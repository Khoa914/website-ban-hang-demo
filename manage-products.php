<?php
// manage-products.php (BẢN FINAL: UPDATE CATEGORY_ID + GIÁ GỐC + ẢNH + SỐ LƯỢNG)

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: POST"); 

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

$action = isset($_POST['action']) ? $_POST['action'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- XÓA (Giữ nguyên logic cũ) ---
    if ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!$id) { echo json_encode(["success" => false, "message" => "Thiếu ID."]); exit; }
        
        $query = $conn->query("SELECT hinh FROM products WHERE id = $id");
        if ($row = $query->fetch_assoc()) {
            if (file_exists($row['hinh'])) unlink($row['hinh']);
        }

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) echo json_encode(["success" => true, "message" => "Đã xóa."]);
        else echo json_encode(["success" => false, "message" => "Lỗi xóa: " . $conn->error]);
        $stmt->close();
    }
    
    // --- CẬP NHẬT (Nâng cấp category_id) ---
    else if ($action === 'update') {
        $id = $_POST['id'] ?? null;
        $ten = $_POST['ten'];
        
        // Giữ tính năng giá gốc và xử lý dấu chấm
        $gia = (int)str_replace(['.', ','], '', $_POST['gia']);
        $gia_goc = (int)str_replace(['.', ','], '', $_POST['gia_goc'] ?? 0); 
        
        $moTa = $_POST['moTa'];
        $so_luong = (int)($_POST['so_luong'] ?? 0);
        
        // [THAY ĐỔI QUAN TRỌNG] Lấy category_id
        $category_id = (int)($_POST['category_id'] ?? 0);
        
        $hinh_db_path = $_POST['hinh_cu']; 

        if (!$id) {
            echo json_encode(["success" => false, "message" => "Thiếu ID."]);
            exit();
        }

        // Logic Upload ảnh (Giữ nguyên)
        if (isset($_FILES['hinh']) && $_FILES['hinh']['error'] == 0) {
            $target_dir = "hinh-anh/"; 
            $new_name = time() . "_" . basename($_FILES["hinh"]["name"]);
            $target_file = $target_dir . $new_name;
            if (move_uploaded_file($_FILES["hinh"]["tmp_name"], $target_file)) {
                $hinh_db_path = $target_file; 
                if (file_exists($_POST['hinh_cu']) && $_POST['hinh_cu'] != $target_file) {
                    unlink($_POST['hinh_cu']);
                }
            }
        }

        // [CÂU SQL MỚI] Thay 'loai' bằng 'category_id'
        $sql = "UPDATE products SET ten=?, gia=?, gia_goc=?, moTa=?, hinh=?, category_id=?, so_luong=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        
        // Bind param: category_id là integer (i)
        $stmt->bind_param("siisssii", $ten, $gia, $gia_goc, $moTa, $hinh_db_path, $category_id, $so_luong, $id);

        if ($stmt->execute()) {
             echo json_encode(["success" => true, "message" => "Cập nhật thành công!"]);
        } else {
            echo json_encode(["success" => false, "message" => "Lỗi cập nhật: " . $conn->error]);
        }
        $stmt->close();
    }
}
$conn->close();
?>