<?php
// Bắt đầu session
session_start();

// Xóa tất cả các biến session
$_SESSION = array();

// Hủy phiên làm việc (session)
session_destroy();

// Chuyển hướng người dùng về trang chủ
header("Location: index.html");
exit;
?>