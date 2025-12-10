<?php
class AuthController
{
    private PDO $db;
    private JwtHelper $jwt;
    public function __construct(PDO $db, JwtHelper $jwt)
    {
        $this->db = $db;
        $this->jwt = $jwt;
    }

    public function register()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            foreach (['name', 'email', 'password'] as $f)
                if (empty($input[$f]))
                    return json_response(['message' => "Thiếu trường $f"], 422);
            if (User::findByEmail($this->db, $input['email']))
                return json_response(['message' => 'Email đã tồn tại'], 409);
            $id = User::create($this->db, $input);
            $token = $this->jwt->encode(['sub' => $id, 'role' => $input['role'] ?? 'candidate']);
            return json_response(['token' => $token, 'user' => User::findById($this->db, $id)]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Lỗi đăng ký', 'error' => $e->getMessage()], 500);
        }
    }

    public function login()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            foreach (['email', 'password'] as $f)
                if (empty($input[$f]))
                    return json_response(['message' => "Thiếu trường $f"], 422);
            $u = User::findByEmail($this->db, $input['email']);
            if (!$u || !password_verify($input['password'], $u['password_hash']))
                return json_response(['message' => 'Thông tin đăng nhập không đúng'], 401);
            $token = $this->jwt->encode(['sub' => $u['id'], 'role' => $u['role']]);
            return json_response(['token' => $token, 'user' => $u]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Lỗi đăng nhập', 'error' => $e->getMessage()], 500);
        }
    }

    public function googleLogin()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($input['google_id']) || empty($input['email'])) {
                return json_response(['message' => 'Thiếu google_id hoặc email'], 422);
            }

            // Check if user exists by Google ID
            $user = User::findByGoogleId($this->db, $input['google_id']);

            if ($user) {
                // Update latest info from Google
                User::updateGoogleInfo($this->db, $user['id'], [
                    'name' => $input['name'],
                    'photo_url' => $input['photo_url'] ?? $user['photo_url']
                ]);
                $user = User::findById($this->db, $user['id']); // Refresh
            } else {
                // Check if email exists (maybe registered conventionally)
                $existingUser = User::findByEmail($this->db, $input['email']);

                if ($existingUser) {
                    // Link Google account to existing account
                    $st = $this->db->prepare('UPDATE users SET google_id = ?, photo_url = ? WHERE id = ?');
                    $st->execute([$input['google_id'], $input['photo_url'] ?? null, $existingUser['id']]);
                    $user = User::findById($this->db, $existingUser['id']);
                } else {
                    // Create new user
                    $id = User::createFromGoogle($this->db, $input);
                    $user = User::findById($this->db, $id);
                }
            }

            $token = $this->jwt->encode(['sub' => $user['id'], 'role' => $user['role']]);
            return json_response(['token' => $token, 'user' => $user]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Lỗi đăng nhập Google', 'error' => $e->getMessage()], 500);
        }
    }

    public function me()
    {
        $token = get_bearer_token();
        if (!$token)
            return json_response(['message' => 'Thiếu token'], 401);
        $payload = $this->jwt->decode($token);
        if (!$payload)
            return json_response(['message' => 'Token không hợp lệ'], 401);
        $u = User::findById($this->db, (int) $payload['sub']);
        if (!$u)
            return json_response(['message' => 'Không tìm thấy user'], 404);
        return json_response(['user' => $u]);
    }
}

