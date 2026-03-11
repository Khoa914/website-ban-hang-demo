<?php
// get-categories.php: Lấy danh sách các loại sản phẩm đang có (DISTINCT)

header('Content-Type: application/json');
$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
mysqli_set_charset($conn, "utf8mb4");

// Lấy các loại duy nhất
$sql = "SELECT DISTINCT loai FROM products WHERE loai IS NOT NULL AND loai != '' ORDER BY loai ASC";
$result = $conn->query($sql);

$categories = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['loai'];
    }
}

echo json_encode(["success" => true, "data" => $categories]);
$conn->close();
?>