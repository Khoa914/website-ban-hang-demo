<?php
// dang-ky.php (ĐÃ NÂNG CẤP THÊM SĐT + ĐỊA CHỈ - SỬA LỖI DÒNG 13)

// ========== KẾT NỐI CƠ SỞ DỮ LIỆU ==========
$db_host = "sql306.infinityfree.com"; 
$db_user = "if0_40189376";           
$db_pass = "Khoa8971";        
$db_name = "if0_40189376_tzshop";     

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    // [ĐÃ SỬA LỖI] Dùng 1 dấu chấm
    die("Kết nối thất bại: " . $conn->connect_error);
}

mysqli_set_charset($conn, "utf8mb4");
$conn->query("SET time_zone = '+07:00'");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Lấy dữ liệu từ form (THÊM 2 DÒNG MỚI)
    $ho_ten = $_POST['ho_ten'];
    $email = $_POST['email'];
    $so_dien_thoai = $_POST['so_dien_thoai']; // Dòng mới
    $dia_chi = $_POST['dia_chi'];           // Dòng mới
    $mat_khau = $_POST['mat_khau'];
    $xac_nhan_mat_khau = $_POST['xac_nhan_mat_khau'];

    // 2. Kiểm tra dữ liệu (Validation)
    if ($mat_khau != $xac_nhan_mat_khau) {
        header("Location: dang-ky.html?error=Mật khẩu không khớp!");
        exit();
    }
    
    $sql_check = "SELECT id FROM users WHERE email = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        header("Location: dang-ky.html?error=Email này đã được sử dụng!");
        exit();
    }
    $stmt_check->close();

    // 3. Băm mật khẩu
    $mat_khau_hashed = password_hash($mat_khau, PASSWORD_DEFAULT);

    // 4. Thêm người dùng mới vào CSDL
    $sql_insert = "INSERT INTO users (ho_ten, email, so_dien_thoai, dia_chi, mat_khau) VALUES (?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    $stmt_insert->bind_param("sssss", $ho_ten, $email, $so_dien_thoai, $dia_chi, $mat_khau_hashed);

    if ($stmt_insert->execute()) {
        header("Location: dang-nhap.html?success=Đăng ký thành công! Vui lòng đăng nhập.");
        exit();
    } else {
        header("Location: dang-ky.html?error=Có lỗi xảy ra, vui lòng thử lại.");
        exit();
    }
    $stmt_insert->close();
}

$conn->close();
?>