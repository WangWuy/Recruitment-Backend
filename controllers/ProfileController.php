<?php
class ProfileController {
    private PDO $db; private JwtHelper $jwt;
    public function __construct(PDO $db, JwtHelper $jwt) { $this->db=$db; $this->jwt=$jwt; }

    private function requireAuth(): array {
        $token = get_bearer_token();
        $payload = $token ? $this->jwt->decode($token) : null;
        if (!$payload) json_response(['message'=>'Unauthorized'], 401);
        return $payload;
    }

    public function getMine() {
        $payload = $this->requireAuth();
        $p = Profile::get($this->db, (int)$payload['sub']);
        return json_response(['data'=>$p]);
    }

    public function upsertMine() {
        try {
            $payload = $this->requireAuth();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            
            // Cập nhật profile
            Profile::upsert($this->db, (int)$payload['sub'], $input);
            
            // Cập nhật tên user nếu có
            if (isset($input['name']) && !empty($input['name'])) {
                User::updateName($this->db, (int)$payload['sub'], $input['name']);
            }
            
            return json_response(['message'=>'Đã lưu hồ sơ']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi lưu hồ sơ','error'=>$e->getMessage()],500); }
    }
}

