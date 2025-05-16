<?php
require_once 'drive.php';

define('ADMIN_PASSWORD', 'admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không hợp lệ']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dữ liệu không hợp lệ']);
    exit;
}

// Đọc file files.json
$files_json_path = __DIR__ . '/files.json';
$files_list = [];
if (file_exists($files_json_path)) {
    $content = file_get_contents($files_json_path);
    $files_list = json_decode($content, true);
    if (!is_array($files_list)) {
        $files_list = [];
    }
}

$drive = new GoogleDrive();
$errors = [];
$ids_to_delete = [];

foreach ($data as $item) {
    $id = $item['id'] ?? '';
    $password = $item['password'] ?? '';

    if ($id === '') {
        $errors[] = "Thiếu id file";
        continue;
    }

    // Tìm file
    $file_index = null;
    $file_info = null;
    foreach ($files_list as $k => $f) {
        if ($f['id'] === $id) {
            $file_index = $k;
            $file_info = $f;
            break;
        }
    }

    if ($file_info === null) {
        $errors[] = "File id $id không tồn tại";
        continue;
    }

    // Kiểm mật khẩu
    if ($password !== ADMIN_PASSWORD) {
        if ($file_info['password'] !== null) {
            if (!password_verify($password, $file_info['password'])) {
                $errors[] = "Mật khẩu không đúng cho file " . htmlspecialchars($file_info['name']);
                continue;
            }
        }
    }

    // Xóa file trên Drive
    $res = $drive->deleteFile($id);
    if (isset($res['error'])) {
        $errors[] = "Lỗi xóa file " . htmlspecialchars($file_info['name']) . ": " . $res['error'];
        continue;
    }

    // Đánh dấu xóa khỏi mảng
    $ids_to_delete[] = $file_index;
}

// Xóa các file trong mảng $files_list
foreach ($ids_to_delete as $idx) {
    unset($files_list[$idx]);
}
$files_list = array_values($files_list);

// Lưu lại files.json
file_put_contents($files_json_path, json_encode($files_list, JSON_PRETTY_PRINT));

if (count($errors) > 0) {
    echo json_encode(['error' => implode('; ', $errors)]);
} else {
    echo json_encode(['success' => true]);
}
exit;
