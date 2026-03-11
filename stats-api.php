<?php
// stats-api.php: API Thống kê Doanh thu & Sản phẩm

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
mysqli_set_charset($conn, "utf8mb4");

// 1. Thống kê Doanh thu 6 tháng gần nhất
// Chỉ tính đơn hàng "Hoàn tất"
$sql_revenue = "SELECT 
                    DATE_FORMAT(thoi_gian_dat, '%m/%Y') as thang, 
                    SUM(tong_tien) as doanh_thu 
                FROM don_hang 
                WHERE trang_thai = 'Hoàn tất' 
                GROUP BY thang 
                ORDER BY thoi_gian_dat DESC 
                LIMIT 6";

$result_rev = $conn->query($sql_revenue);
$revenue_data = [];
if ($result_rev) {
    while ($row = $result_rev->fetch_assoc()) {
        $revenue_data[] = $row;
    }
}
// Đảo ngược mảng để tháng cũ hiện trước, tháng mới hiện sau (cho biểu đồ)
$revenue_data = array_reverse($revenue_data);

// 2. Thống kê Top 5 Sản phẩm bán chạy
$sql_top = "SELECT 
                p.ten, 
                SUM(ct.so_luong) as da_ban 
            FROM chi_tiet_don_hang ct
            JOIN products p ON ct.product_id = p.id
            JOIN don_hang d ON ct.order_id = d.id
            WHERE d.trang_thai = 'Hoàn tất'
            GROUP BY p.id, p.ten 
            ORDER BY da_ban DESC 
            LIMIT 5";

$result_top = $conn->query($sql_top);
$top_products = [];
if ($result_top) {
    while ($row = $result_top->fetch_assoc()) {
        $top_products[] = $row;
    }
}

echo json_encode([
    "success" => true, 
    "revenue" => $revenue_data, 
    "top_products" => $top_products
]);

$conn->close();
?>