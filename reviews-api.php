<?php
// reviews-api.php: Quản lý đánh giá sản phẩm

ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

$db_host = "sql306.infinityfree.com";
$db_user = "if0_40189376";
$db_pass = "Khoa8971";
$db_name = "if0_40189376_tzshop";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Lỗi kết nối CSDL"]);
    exit();
}
mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

session_start();
$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Lấy danh sách đánh giá ---
if ($method === 'GET') {
    $product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
    
    // Lấy đánh giá + Tên người dùng (JOIN bảng users)
    $sql = "SELECT r.rating, r.comment, r.created_at, u.ho_ten 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.product_id = ? 
            ORDER BY r.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $row['ho_ten'] = htmlspecialchars($row['ho_ten']); // Bảo mật
        $row['comment'] = htmlspecialchars($row['comment']); 
        $reviews[] = $row;
    }
    
    // Tính điểm trung bình
    $avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ?";
    $stmt_avg = $conn->prepare($avg_sql);
    $stmt_avg->bind_param("i", $product_id);
    $stmt_avg->execute();
    $avg_data = $stmt_avg->get_result()->fetch_assoc();
    
    echo json_encode([
        "success" => true, 
        "data" => $reviews,
        "average" => round($avg_data['avg_rating'] ?? 0, 1),
        "total" => $avg_data['total']
    ]);
}

// --- POST: Gửi đánh giá mới ---
else if ($method === 'POST') {
    // Kiểm tra đăng nhập
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Bạn cần đăng nhập để đánh giá."]);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = $data['product_id'] ?? 0;
    $rating = (int)($data['rating'] ?? 5);
    $comment = trim($data['comment'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($rating < 1 || $rating > 5 || empty($comment)) {
        echo json_encode(["success" => false, "message" => "Vui lòng chọn sao và nhập nội dung."]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $product_id, $user_id, $rating, $comment);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Cảm ơn bạn đã đánh giá!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Lỗi khi lưu đánh giá."]);
    }
}
$conn->close();
?>