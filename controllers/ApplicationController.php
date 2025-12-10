<?php
class ApplicationController {
    private PDO $db; private JwtHelper $jwt;
    public function __construct(PDO $db, JwtHelper $jwt) { $this->db=$db; $this->jwt=$jwt; }

    private function requireAuth(): array {
        $token = get_bearer_token();
        $payload = $token ? $this->jwt->decode($token) : null;
        if (!$payload) json_response(['message'=>'Unauthorized'], 401);
        return $payload;
    }

    public function apply() {
        try {
            $payload = $this->requireAuth();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            foreach (['job_id'] as $f) if (empty($input[$f])) return json_response(['message'=>"Thiếu trường $f"], 422);

            $coverLetter = $input['cover_letter'] ?? '';
            $cvUrl = $input['cv_url'] ?? null;
            $expectedSalary = $input['expected_salary'] ?? null;
            $availableFrom = $input['available_from'] ?? null;

            $st = $this->db->prepare('INSERT INTO applications(job_id, candidate_id, status, cover_letter, cv_url, expected_salary, available_from, applied_at) VALUES(?,? ,"pending", ?, ?, ?, ?, NOW())');
            $st->execute([(int)$input['job_id'], (int)$payload['sub'], $coverLetter, $cvUrl, $expectedSalary, $availableFrom]);
            return json_response(['message'=>'Đã ứng tuyển']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi ứng tuyển','error'=>$e->getMessage()],500); }
    }

    public function listForEmployer() {
        try {
            $payload = $this->requireAuth();
            if (!in_array($payload['role'] ?? '', ['employer','admin'])) return json_response(['message'=>'Forbidden'], 403);
            $st = $this->db->query('SELECT a.*, u.name as candidate_name, u.email as candidate_email, j.title FROM applications a JOIN users u ON u.id=a.candidate_id JOIN jobs j ON j.id=a.job_id ORDER BY a.applied_at DESC LIMIT 200');
            return json_response(['data'=>$st->fetchAll()]);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi lấy danh sách ứng tuyển','error'=>$e->getMessage()],500); }
    }

    public function updateStatus(int $id) {
        try {
            $payload = $this->requireAuth();
            if (!in_array($payload['role'] ?? '', ['employer','admin'])) return json_response(['message'=>'Forbidden'], 403);
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($input['status'])) return json_response(['message'=>'Thiếu status'], 422);
            $st = $this->db->prepare('UPDATE applications SET status=? WHERE id=?');
            $st->execute([$input['status'], $id]);
            return json_response(['message'=>'Đã cập nhật trạng thái']);
        } catch (Throwable $e) { return json_response(['message'=>'Lỗi cập nhật trạng thái','error'=>$e->getMessage()],500); }
    }
}

