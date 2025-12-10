<?php
class AdminController {
    private PDO $db; private JwtHelper $jwt;
    public function __construct(PDO $db, JwtHelper $jwt) { $this->db=$db; $this->jwt=$jwt; }

    private function requireAdmin(): array {
        $token = get_bearer_token();
        $payload = $token ? $this->jwt->decode($token) : null;
        if (!$payload || ($payload['role'] ?? '') !== 'admin') json_response(['message'=>'Forbidden'], 403);
        return $payload;
    }

    public function stats() {
        try {
            $this->requireAdmin();
            $counts = [];
            foreach (['users','companies','jobs','applications'] as $table) {
                $counts[$table] = (int)$this->db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            }
            return json_response(['data'=>$counts]);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi thống kê','error'=>$e->getMessage()],500); }
    }

    // ========== USER MANAGEMENT ==========
    public function createUser() {
        try {
            $this->requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $role = $data['role'] ?? 'candidate';
            $phone = $data['phone'] ?? null;
            $status = $data['status'] ?? 'active';

            if ($name === '' || $email === '' || $password === '') {
                return json_response(['message' => 'Thiếu name/email/password'], 422);
            }

            // Check duplicate email
            $check = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);
            if ($check->fetch()) {
                return json_response(['message' => 'Email đã tồn tại'], 409);
            }

            // Insert user
            $stmt = $this->db->prepare('INSERT INTO users(name, email, phone, password_hash, role, status, created_at) VALUES(?,?,?,?,?,?,NOW())');
            $stmt->execute([
                $name,
                $email,
                $phone,
                password_hash($password, PASSWORD_BCRYPT),
                $role,
                $status,
            ]);
            $id = (int)$this->db->lastInsertId();

            // Return created user basic info
            $userStmt = $this->db->prepare('SELECT id, name, email, role, status, phone, created_at FROM users WHERE id = ?');
            $userStmt->execute([$id]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            return json_response(['message' => 'Tạo người dùng thành công', 'data' => $user], 201);
        } catch (Throwable $e) {
            return json_response(['message' => 'Lỗi tạo người dùng', 'error' => $e->getMessage()], 500);
        }
    }
    public function getAllUsers() {
        try {
            $this->requireAdmin();
            $role = $_GET['role'] ?? '';
            
            if ($role) {
                $stmt = $this->db->prepare("
                    SELECT u.id, u.name, u.email, u.role, u.status, u.phone, u.created_at,
                           u.name as profile_name,
                           p.phone as profile_phone,
                           p.address as profile_address
                    FROM users u
                    LEFT JOIN profiles p ON u.id = p.user_id
                    WHERE u.role = ?
                    ORDER BY u.created_at DESC
                ");
                $stmt->execute([$role]);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $users = $this->db->query("
                    SELECT u.id, u.name, u.email, u.role, u.status, u.phone, u.created_at,
                           u.name as profile_name,
                           p.phone as profile_phone,
                           p.address as profile_address
                    FROM users u
                    LEFT JOIN profiles p ON u.id = p.user_id
                    ORDER BY u.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return json_response(['data'=>$users]);
        } catch (Throwable $e) { 
            return json_response(['message'=>'Lỗi lấy danh sách','error'=>$e->getMessage()],500); 
        }
    }

    public function getUser($id) {
        try {
            $this->requireAdmin();
            $stmt = $this->db->prepare("
                SELECT u.*, 
                       p.full_name as profile_name,
                       p.phone as profile_phone,
                       p.address as profile_address
                FROM users u
                LEFT JOIN profiles p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return json_response(['message'=>'Không tìm thấy người dùng'],404);
            return json_response(['data'=>$user]);
        } catch (Throwable $e) { 
            return json_response(['message'=>'Lỗi lấy thông tin','error'=>$e->getMessage()],500); 
        }
    }

    public function updateUser($id) {
        try {
            $this->requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Build dynamic update with prepared statements
            $fields = [];
            $params = [];
            if (isset($data['email'])) { $fields[] = 'email = ?'; $params[] = $data['email']; }
            if (isset($data['role'])) { $fields[] = 'role = ?'; $params[] = $data['role']; }
            if (isset($data['status'])) { $fields[] = 'status = ?'; $params[] = $data['status']; }
            if (isset($data['phone'])) { $fields[] = 'phone = ?'; $params[] = $data['phone']; }
            if (isset($data['name'])) { $fields[] = 'name = ?'; $params[] = $data['name']; }

            if (empty($fields)) return json_response(['message'=>'Không có dữ liệu cập nhật'],400);

            $params[] = $id;
            $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
            $stmt->execute($params);
            
            // Update profile phone/address if provided
            if (isset($data['phone']) || isset($data['address'])) {
                $pfields = [];
                $pparams = [];
                if (isset($data['phone'])) { $pfields[] = 'phone = ?'; $pparams[] = $data['phone']; }
                if (isset($data['address'])) { $pfields[] = 'address = ?'; $pparams[] = $data['address']; }

                if (!empty($pfields)) {
                    $pparams[] = $id;
                    $pstmt = $this->db->prepare('UPDATE profiles SET ' . implode(', ', $pfields) . ' WHERE user_id = ?');
                    $pstmt->execute($pparams);
                }
            }
            
            return json_response(['message'=>'Cập nhật thành công']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi cập nhật','error'=>$e->getMessage()],500); }
    }

    public function deleteUser($id) {
        try {
            $this->requireAdmin();
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            return json_response(['message'=>'Xóa thành công']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi xóa','error'=>$e->getMessage()],500); }
    }

    // ========== JOB MANAGEMENT ==========
    public function getAllJobs() {
        try {
            $this->requireAdmin();
            $status = $_GET['status'] ?? '';
            
            if ($status) {
                $stmt = $this->db->prepare("
                    SELECT j.*, 
                           c.name as company_name,
                           cat.name as category_name,
                           u.email as employer_email
                    FROM jobs j
                    LEFT JOIN companies c ON j.company_id = c.id
                    LEFT JOIN categories cat ON j.category_id = cat.id
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE j.status = ?
                    ORDER BY j.created_at DESC
                ");
                $stmt->execute([$status]);
                $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $jobs = $this->db->query("
                    SELECT j.*, 
                           c.name as company_name,
                           cat.name as category_name,
                           u.email as employer_email
                    FROM jobs j
                    LEFT JOIN companies c ON j.company_id = c.id
                    LEFT JOIN categories cat ON j.category_id = cat.id
                    LEFT JOIN users u ON c.user_id = u.id
                    ORDER BY j.created_at DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
            }
            
            return json_response(['data'=>$jobs]);
        } catch (Throwable $e) { 
            return json_response(['message'=>'Lỗi lấy danh sách','error'=>$e->getMessage()],500); 
        }
    }

    public function getJob($id) {
        try {
            $this->requireAdmin();
            $stmt = $this->db->prepare("
                SELECT j.*, 
                       c.name as company_name,
                       cat.name as category_name,
                       u.email as employer_email
                FROM jobs j
                LEFT JOIN companies c ON j.company_id = c.id
                LEFT JOIN categories cat ON j.category_id = cat.id
                LEFT JOIN users u ON c.user_id = u.id
                WHERE j.id = ?
            ");
            $stmt->execute([$id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) return json_response(['message'=>'Không tìm thấy tin tuyển dụng'],404);
            return json_response(['data'=>$job]);
        } catch (Throwable $e) { 
            return json_response(['message'=>'Lỗi lấy thông tin','error'=>$e->getMessage()],500); 
        }
    }

    public function updateJob($id) {
        try {
            $this->requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            
            $update = [];
            if (isset($data['status'])) $update[] = "status = '{$data['status']}'";
            if (isset($data['is_featured'])) $update[] = "is_featured = " . ($data['is_featured'] ? 1 : 0);
            
            if (empty($update)) return json_response(['message'=>'Không có dữ liệu cập nhật'],400);
            
            $sql = "UPDATE jobs SET " . implode(', ', $update) . " WHERE id = $id";
            $this->db->exec($sql);
            
            return json_response(['message'=>'Cập nhật thành công']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi cập nhật','error'=>$e->getMessage()],500); }
    }

    public function deleteJob($id) {
        try {
            $this->requireAdmin();
            $this->db->exec("DELETE FROM jobs WHERE id = $id");
            return json_response(['message'=>'Xóa thành công']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi xóa','error'=>$e->getMessage()],500); }
    }

    public function moderateJob($id) {
        try {
            $this->requireAdmin();
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $data['action'] ?? 'approve';
            
            if ($action === 'approve') {
                $this->db->exec("UPDATE jobs SET status = 'active' WHERE id = $id");
            } elseif ($action === 'reject') {
                $this->db->exec("UPDATE jobs SET status = 'rejected' WHERE id = $id");
            }
            
            return json_response(['message'=>'Thao tác thành công']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi thao tác','error'=>$e->getMessage()],500); }
    }
}

