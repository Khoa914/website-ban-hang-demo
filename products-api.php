<?php
// products-api.php (BẢN FINAL: JOIN CATEGORIES + SALE + BÁN CHẠY)

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) { echo json_encode(["error" => "Lỗi DB"]); exit(); }
mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

$products = [];
$loai = isset($_GET['loai']) ? $_GET['loai'] : null;
$id = isset($_GET['id']) ? $_GET['id'] : null;
$isAdmin = isset($_GET['admin']) ? true : false; 
$search = isset($_GET['search']) ? trim($_GET['search']) : null; 
$isBestselling = isset($_GET['bestselling']) ? true : false;
$isOnSale = isset($_GET['onsale']) ? true : false;

// CÂU SQL CƠ BẢN (CÓ JOIN BẢNG CATEGORIES)
// Chúng ta lấy tên danh mục (ten_danh_muc) và đổi tên thành 'loai_text'
$base_select = "SELECT p.*, c.ten_danh_muc as loai_text, c.id as cat_id ";
$base_join   = "FROM products p LEFT JOIN categories c ON p.category_id = c.id ";

if ($isBestselling) {
    // Logic Bán chạy (Giữ nguyên, chỉ thêm JOIN)
    $sql = "SELECT p.*, c.ten_danh_muc as loai_text, SUM(ct.so_luong) AS total_sold
            FROM chi_tiet_don_hang ct
            JOIN products p ON ct.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            GROUP BY p.id
            ORDER BY total_sold DESC LIMIT 6";
    $stmt = $conn->prepare($sql);

} else if ($isOnSale) {
    // Logic Sale (Giữ nguyên)
    $sql = $base_select . $base_join . "WHERE p.gia_goc > p.gia ORDER BY ((p.gia_goc - p.gia) / p.gia_goc) DESC";
    $stmt = $conn->prepare($sql);

} else {
    // Logic Tìm kiếm / Lọc (Sửa đổi để lọc theo tên danh mục)
    $sql = $base_select . $base_join . "WHERE 1=1";
    $params = []; $types = ""; 

    if ($search) {
        $sql .= " AND p.ten LIKE ?";
        $types .= "s"; $params[] = "%$search%";
    } 
    else if ($id) {
        $sql .= " AND p.id = ?";
        $types .= "i"; $params[] = $id;
    } 
    else if ($loai) {
        // [QUAN TRỌNG] Lọc theo tên danh mục (vì URL vẫn là ?loai=laptop)
        // Chúng ta tìm những sản phẩm có danh mục (hoặc cha của danh mục) khớp với tên
        // Nhưng để đơn giản, ta so sánh tên danh mục (hoặc slug nếu bạn có cột slug)
        // Ở đây tạm thời so sánh tên danh mục
        $sql .= " AND (c.ten_danh_muc LIKE ? OR c.ten_danh_muc LIKE ?)"; 
        $types .= "ss"; 
        $params[] = "%$loai%"; // Tìm gần đúng
        $params[] = "%$loai%";
    }
    else if ($isAdmin) {
        // Admin lấy tất cả
    }
    else { 
        $sql .= " LIMIT 3"; 
    }
    
    $stmt = $conn->prepare($sql);
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params); 
    }
}

if ($stmt && $stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id'] = (int) $row['id'];
        $row['gia'] = (int) $row['gia'];
        $row['gia_goc'] = (int) $row['gia_goc'];
        $row['so_luong'] = (int) $row['so_luong'];
        
        // [QUAN TRỌNG] Frontend đang dùng field 'loai'. 
        // Ta gán 'loai_text' (tên danh mục lấy từ JOIN) vào 'loai' để frontend không bị lỗi.
        $row['loai'] = $row['loai_text'] ?? 'Chưa phân loại';
        
        $products[] = $row;
    }
    $stmt->close();
}
$conn->close();
echo json_encode($products, JSON_UNESCAPED_UNICODE);
?>