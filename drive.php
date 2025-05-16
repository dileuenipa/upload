<?php
class GoogleDrive {
    private $token_uri = 'https://oauth2.googleapis.com/token';
    private $scope = 'https://www.googleapis.com/auth/drive.file';
    private $service_account_file = __DIR__ . '/service-account.json';

    private $access_token;
    private $token_expiry;

    public function __construct() {
        $this->loadServiceAccount();
    }

    private $service_account;

    private function loadServiceAccount() {
        if (!file_exists($this->service_account_file)) {
            die("Không tìm thấy file service-account.json");
        }
        $json = file_get_contents($this->service_account_file);
        $this->service_account = json_decode($json, true);
    }

    // Tạo JWT để lấy access token
    private function createJWT() {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $claims = [
            'iss' => $this->service_account['client_email'],
            'scope' => $this->scope,
            'aud' => $this->token_uri,
            'exp' => $now + 3600,
            'iat' => $now
        ];
        $base64url_header = $this->base64url_encode(json_encode($header));
        $base64url_claims = $this->base64url_encode(json_encode($claims));
        $unsigned_token = $base64url_header . '.' . $base64url_claims;

        $pkey = openssl_pkey_get_private($this->service_account['private_key']);
        if (!$pkey) {
            die("Không thể lấy private key từ service-account.json");
        }
        openssl_sign($unsigned_token, $signature, $pkey, 'sha256');
        $base64url_signature = $this->base64url_encode($signature);

        return $unsigned_token . '.' . $base64url_signature;
    }

    // Encode base64url
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // Lấy access token từ Google
    private function getAccessToken() {
        if ($this->access_token && $this->token_expiry > time() + 60) {
            return $this->access_token;
        }
        $jwt = $this->createJWT();
        $post = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]);
        $ch = curl_init($this->token_uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            die("Lỗi CURL khi lấy access token: " . curl_error($ch));
        }
        curl_close($ch);
        $data = json_decode($response, true);
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
            $this->token_expiry = time() + $data['expires_in'];
            return $this->access_token;
        } else {
            die("Lấy access token thất bại: " . $response);
        }
    }

    // Upload file lên Google Drive (tự đổi tên nếu trùng)
    public function uploadFile($file_path, $file_name, $mime_type) {
        $access_token = $this->getAccessToken();

        // Kiểm tra xem file cùng tên đã tồn tại chưa
        $existing_name = $file_name;
        $i = 1;
        while ($this->fileExists($existing_name, $access_token)) {
            // Thêm (1), (2), ... trước phần mở rộng
            $pos = strrpos($file_name, '.');
            if ($pos !== false) {
                $name_part = substr($file_name, 0, $pos);
                $ext_part = substr($file_name, $pos);
                $existing_name = $name_part . " ($i)" . $ext_part;
            } else {
                $existing_name = $file_name . " ($i)";
            }
            $i++;
        }

        // Upload file bằng multipart request
        $metadata = [
			'name' => $existing_name,
			'parents' => ['1ir5U3psuKa7iwXQXZWDbrMumW7XC_NZU']
		];

        $metadata_json = json_encode($metadata);

        $file_data = file_get_contents($file_path);

        $boundary = '-------314159265358979323846';
        $delimiter = "\r\n--" . $boundary . "\r\n";
        $close_delim = "\r\n--" . $boundary . "--";

        $body = $delimiter .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            $metadata_json .
            $delimiter .
            "Content-Type: $mime_type\r\n\r\n" .
            $file_data .
            $close_delim;

        $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return ['error' => 'Lỗi CURL: ' . curl_error($ch)];
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res_data = json_decode($response, true);
        if ($http_code == 200 || $http_code == 201) {
            return ['id' => $res_data['id'], 'name' => $existing_name];
        } else {
            return ['error' => 'Upload thất bại: ' . $response];
        }
    }

    // Kiểm tra file có tồn tại theo tên chưa
    private function fileExists($name, $access_token) {
        $q = urlencode("name = '$name' and trashed = false");
        $url = "https://www.googleapis.com/drive/v3/files?q=$q&fields=files(id,name)";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        return !empty($data['files']);
    }
}
