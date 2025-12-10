<?php
class UploadController {
    private PDO $db; private JwtHelper $jwt;
    public function __construct(PDO $db, JwtHelper $jwt) { $this->db=$db; $this->jwt=$jwt; }

    public function upload() {
        try {
            $token = get_bearer_token();
            if (!$token || !$this->jwt->decode($token)) return json_response(['message'=>'Unauthorized'], 401);
            if (!isset($_FILES['file'])) return json_response(['message'=>'Thiếu file'], 422);
            $dir = __DIR__ . '/../uploads';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $name = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.\-]/','', $_FILES['file']['name']);
            $target = $dir . '/' . $name;
            if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) return json_response(['message'=>'Không thể lưu file'], 500);
            $url = (env('APP_URL','') ? rtrim(env('APP_URL'),'/') : '') . '/uploads/' . $name;
            return json_response(['url'=>$url, 'name'=>$name]);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi upload','error'=>$e->getMessage()],500); }
    }
}

