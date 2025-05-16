<?php
require_once 'drive.php';

define('ADMIN_PASSWORD', 'admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không hợp lệ']);
    exit;
}

$fileId = $_GET['id'] ?? '';
$password = $_GET['password'] ?? '';

if ($fileId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu id file']);
    exit;
}

// Đọc files.json để tìm file
$files_json_path = __DIR__ . '/files.json';
$files_list = [];
if (file_exists($files_json_path)) {
    $content = file_get_contents($files_json_path);
    $files_list = json_decode($content, true);
    if (!is_array($files_list)) {
        $files_list = [];
    }
}

$file_info = null;
foreach ($files_list as $file) {
    if ($file['id'] === $fileId) {
        $file_info = $file;
        break;
    }
}

if (!$file_info) {
    http_response_code(404);
    echo json_encode(['error' => 'File không tồn tại']);
    exit;
}

// Kiểm mật khẩu: đúng nếu là admin hoặc khớp mật khẩu file
if ($password !== ADMIN_PASSWORD) {
    if ($file_info['password'] === null) {
        // File không đặt mật khẩu => cho phép tải
    } else {
        if (!password_verify($password, $file_info['password'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Mật khẩu không đúng']);
            exit;
        }
    }
}

// Khởi tạo GoogleDrive
$drive = new GoogleDrive();
$content = $drive->downloadFile($fileId);
if (isset($content['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $content['error']]);
    exit;
}

// Đẩy file về client
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file_info['name']) . '"');
header('Content-Length: ' . strlen($content));
echo $content;
exit;
