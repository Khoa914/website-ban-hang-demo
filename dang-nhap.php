<?php
// dang-nhap.php: ĐÃ NÂNG CẤP (Kiểm tra is_active)

session_start(); 

// ========== KẾT NỐI CƠ SỞ DỮ LIỆU ==========
$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    header("Location: dang-nhap.html?error=" . urlencode("Lỗi kết nối CSDL."));
    exit();
}

mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = $_POST['email'];
    $mat_khau = $_POST['mat_khau'];

    // [BƯỚC 2.1] Sửa SELECT, thêm 'is_active'
    $sql = "SELECT id, ho_ten, mat_khau, role, is_active FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    
    if(!$stmt) {
        header("Location: dang-nhap.html?error=" . urlencode("Lỗi chuẩn bị SQL."));
        exit();
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // [BƯỚC 2.2] Bind thêm $is_active
        $stmt->bind_result($id, $ho_ten, $mat_khau_hashed, $role, $is_active);
        $stmt->fetch();

        if (password_verify($mat_khau, $mat_khau_hashed)) {
            // Mật khẩu chính xác!
            
            // [BƯỚC 2.3 - QUAN TRỌNG] Kiểm tra tài khoản có bị khóa không
            if ($is_active == 0) {
                // Tài khoản đã bị khóa
                header("Location: dang-nhap.html?error=" . urlencode("Tài khoản này đã bị quản trị viên khóa!"));
                exit();
            }

            // Nếu không bị khóa, tiếp tục đăng nhập
            session_regenerate_id(true);
            $_SESSION['da_dang_nhap'] = true;
            $_SESSION['user_id'] = $id;
            $_SESSION['user_name'] = $ho_ten;
            $_SESSION['role'] = $role; 

            if ($role === 'admin') {
                header("Location: admin.html");
            } else {
                header("Location: index.html");
            }
            exit(); 
            
        } else {
            // Sai mật khẩu
            header("Location: dang-nhap.html?error=" . urlencode("Sai email hoặc mật khẩu!"));
            exit();
        }
    } else {
        // Không tìm thấy email
        header("Location: dang-nhap.html?error=" . urlencode("Sai email hoặc mật khẩu!"));
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>