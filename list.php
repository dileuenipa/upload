<?php
// Định nghĩa mật khẩu admin
define('ADMIN_PASSWORD', 'admin');

// Đọc danh sách file
$files_json_path = __DIR__ . '/files.json';
$files_list = [];
if (file_exists($files_json_path)) {
    $content = file_get_contents($files_json_path);
    $files_list = json_decode($content, true);
    if (!is_array($files_list)) {
        $files_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>Danh sách file đã upload</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { padding: 8px 12px; border: 1px solid #ccc; }
  th { background: #eee; }
  input[type=password] { width: 130px; }
  button { margin-top: 10px; margin-right: 10px; }
</style>
</head>
<body>
<h2>Danh sách file đã upload</h2>

<form id="fileForm">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox" id="selectAll" /></th>
        <th>Tên file</th>
        <th>Dung lượng (MB)</th>
        <th>Thời gian upload</th>
        <th>Mật khẩu file</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($files_list as $idx => $file): ?>
      <tr>
        <td><input type="checkbox" name="files[]" value="<?= htmlspecialchars($file['id']) ?>" data-index="<?= $idx ?>" /></td>
        <td><?= htmlspecialchars($file['name']) ?></td>
        <td><?= number_format($file['size'] / (1024*1024), 2) ?></td>
        <td><?= htmlspecialchars($file['upload_time']) ?></td>
        <td><input type="password" name="file_password_<?= $idx ?>" placeholder="Mật khẩu" /></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($files_list)): ?>
      <tr><td colspan="5" style="text-align:center">Chưa có file nào</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p>
    Mật khẩu chung cho các file chọn: <input type="password" id="common_password" placeholder="Mật khẩu chung" />
  </p>

  <button type="button" id="downloadBtn">Tải xuống</button>
  <button type="button" id="deleteBtn">Xóa file đã chọn</button>
</form>

<script>
// Chọn / bỏ chọn tất cả
document.getElementById('selectAll').addEventListener('change', function() {
  let checked = this.checked;
  document.querySelectorAll('input[name="files[]"]').forEach(cb => cb.checked = checked);
});

// Lấy mật khẩu cho từng file (ưu tiên mật khẩu file riêng, nếu trống dùng mật khẩu chung)
function getPasswords() {
  let passwords = {};
  const checkboxes = document.querySelectorAll('input[name="files[]"]:checked');
  checkboxes.forEach(cb => {
    let idx = cb.getAttribute('data-index');
    let pw = document.querySelector('input[name="file_password_' + idx + '"]').value.trim();
    passwords[cb.value] = pw;
  });
  return passwords;
}

// Lấy mật khẩu chung
function getCommonPassword() {
  return document.getElementById('common_password').value.trim();
}

// Tải xuống nhiều file: mở tab mới cho từng file
document.getElementById('downloadBtn').addEventListener('click', function() {
  let selected = Array.from(document.querySelectorAll('input[name="files[]"]:checked'));
  if (selected.length === 0) {
    alert('Vui lòng chọn ít nhất 1 file để tải xuống.');
    return;
  }
  let passwords = getPasswords();
  let commonPassword = getCommonPassword();

  selected.forEach(cb => {
    let fileId = cb.value;
    let pw = passwords[fileId];
    if (pw === '') pw = commonPassword;
    if (pw === '') {
      alert('Vui lòng nhập mật khẩu cho file hoặc mật khẩu chung.');
      return;
    }
    // Mở tab tải xuống (gọi download.php)
    let url = `download.php?id=${encodeURIComponent(fileId)}&password=${encodeURIComponent(pw)}`;
    window.open(url, '_blank');
  });
});

// Xóa file đã chọn
document.getElementById('deleteBtn').addEventListener('click', function() {
  let selected = Array.from(document.querySelectorAll('input[name="files[]"]:checked'));
  if (selected.length === 0) {
    alert('Vui lòng chọn ít nhất 1 file để xóa.');
    return;
  }
  if (!confirm('Bạn có chắc muốn xóa các file đã chọn?')) return;

  let passwords = getPasswords();
  let commonPassword = getCommonPassword();

  // Gửi AJAX xóa
  let xhr = new XMLHttpRequest();
  xhr.open('POST', 'delete.php', true);
  xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');

  let data = [];
  selected.forEach(cb => {
    let fileId = cb.value;
    let pw = passwords[fileId];
    if (pw === '') pw = commonPassword;
    data.push({id: fileId, password: pw});
  });

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        let res = JSON.parse(xhr.responseText);
        if (res.success) {
          alert('Xóa thành công. Reload lại trang.');
          location.reload();
        } else {
          alert('Lỗi: ' + res.error);
        }
      } else {
        alert('Lỗi khi gọi server: ' + xhr.status);
      }
    }
  };

  xhr.send(JSON.stringify(data));
});
</script>
</body>
</html>
