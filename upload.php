<?php
require_once 'drive.php';

define('ADMIN_PASSWORD', 'admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Phương thức không hợp lệ']);
    exit;
}

// Lấy mật khẩu upload (có thể để trống)
$password = trim($_POST['password'] ?? '');
// Ở đây không bắt buộc nhập mật khẩu để upload, bạn có thể thêm kiểm tra nếu muốn

// Kiểm tra file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Không có file hoặc file lỗi']);
    exit;
}

$file_tmp = $_FILES['file']['tmp_name'];
$file_name = $_FILES['file']['name'];
$file_type = $_FILES['file']['type'];
$file_size = $_FILES['file']['size'];

// Khởi tạo GoogleDrive class
$drive = new GoogleDrive();
$res = $drive->uploadFile($file_tmp, $file_name, $file_type);

if (isset($res['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $res['error']]);
    exit;
}

// Lưu thông tin file vào files.json
$file_record = [
    'id' => $res['id'],
    'name' => $res['name'],
    'size' => $file_size,
    'upload_time' => date('Y-m-d H:i:s'),
    // Nếu mật khẩu trống thì lưu null, không mã hóa
    'password' => $password === '' ? null : password_hash($password, PASSWORD_DEFAULT)
];

$files_json_path = __DIR__ . '/files.json';
$files_list = [];
if (file_exists($files_json_path)) {
    $content = file_get_contents($files_json_path);
    $files_list = json_decode($content, true);
    if (!is_array($files_list)) {
        $files_list = [];
    }
}
$files_list[] = $file_record;
file_put_contents($files_json_path, json_encode($files_list, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'file' => $file_record]);
exit;
