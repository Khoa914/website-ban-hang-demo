<?php
// add-product-handler.php (BẢN FINAL: CATEGORY_ID + GIÁ GỐC + SỐ LƯỢNG + ẢNH)

ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    header("Location: admin.html?error=Lỗi kết nối CSDL.");
    exit();
}
mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

function redirect_with_error($message) {
    header("Location: admin.html?error=" . urlencode($message));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Lấy dữ liệu
    $ten = $_POST['ten'] ?? '';
    // Xử lý giá (xóa dấu chấm)
    $gia = (int)str_replace(['.', ','], '', $_POST['gia'] ?? 0);
    $gia_goc = (int)str_replace(['.', ','], '', $_POST['gia_goc'] ?? 0); // Giữ tính năng giá gốc
    
    $moTa = $_POST['moTa'] ?? '';
    $so_luong = (int)($_POST['so_luong'] ?? 0); // Giữ tính năng số lượng
    
    // [THAY ĐỔI QUAN TRỌNG] Lấy category_id (số) thay vì loai (chữ)
    $category_id = (int)($_POST['category_id'] ?? 0);

    if (empty($ten) || empty($gia) || $category_id == 0) {
        redirect_with_error("Vui lòng điền Tên, Giá và Chọn danh mục con.");
    }

    // 2. Xử lý Upload Ảnh (Logic giữ nguyên 100%)
    $hinh_db_path = ""; 
    if (isset($_FILES['hinh']) && $_FILES['hinh']['error'] == 0) {
        $target_dir = "hinh-anh/"; 
        $original_name = basename($_FILES["hinh"]["name"]);
        $new_file_name = time() . "_" . $original_name;
        $target_file_path = $target_dir . $new_file_name;
        
        $check = getimagesize($_FILES["hinh"]["tmp_name"]);
        if($check === false) redirect_with_error("File không phải là ảnh.");
        
        if (move_uploaded_file($_FILES["hinh"]["tmp_name"], $target_file_path)) {
            $hinh_db_path = $target_file_path;
        } else {
            redirect_with_error("Lỗi upload ảnh.");
        }
    } else {
        redirect_with_error("Chưa chọn ảnh.");
    }

    // 3. INSERT DATABASE
    $conn->begin_transaction();
    try {
        // [CÂU SQL MỚI] Thay cột 'loai' bằng 'category_id'
        // Tổng 7 cột: ten, gia, gia_goc, moTa, hinh, category_id, so_luong
        $sql_product = "INSERT INTO products (ten, gia, gia_goc, moTa, hinh, category_id, so_luong) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt_product = $conn->prepare($sql_product);
        
        // Bind param: s-i-i-s-s-i-i (category_id là integer)
        $stmt_product->bind_param("siisssi", $ten, $gia, $gia_goc, $moTa, $hinh_db_path, $category_id, $so_luong);
        
        if (!$stmt_product->execute()) {
            throw new Exception("Lỗi SQL: " . $conn->error);
        }
        $stmt_product->close();

        $conn->commit();
        header("Location: admin.html?success=Thêm sản phẩm thành công!");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        redirect_with_error($e->getMessage());
    }
}
$conn->close();
?>