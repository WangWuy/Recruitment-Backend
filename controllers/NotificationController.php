<?php
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';

class NotificationController {
    private PDO $db;
    private JwtHelper $jwt;

    public function __construct(PDO $db, JwtHelper $jwt) {
        $this->db = $db;
        $this->jwt = $jwt;
    }

    private function requireAuth(): array {
        $token = get_bearer_token();
        $payload = $token ? $this->jwt->decode($token) : null;
        if (!$payload) json_response(['message' => 'Unauthorized'], 401);
        return $payload;
    }

    // Get user notifications
    public function getMine() {
        try {
            $payload = $this->requireAuth();
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = ($page - 1) * $limit;

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$payload['sub']]);
            $total = $countStmt->fetch()['total'];

            // Get notifications
            $sql = "SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub'], $limit, $offset]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true) ?? [];
                }
            }

            return json_response([
                'data' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching notifications', 'error' => $e->getMessage()], 500);
        }
    }

    // Mark notification as read
    public function markAsRead($id) {
        try {
            $payload = $this->requireAuth();

            $sql = "UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $payload['sub']]);

            if ($stmt->rowCount() === 0) {
                return json_response(['message' => 'Notification not found'], 404);
            }

            return json_response(['message' => 'Notification marked as read']);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error marking notification as read', 'error' => $e->getMessage()], 500);
        }
    }

    // Mark all notifications as read
    public function markAllAsRead() {
        try {
            $payload = $this->requireAuth();

            $sql = "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub']]);

            return json_response(['message' => 'All notifications marked as read']);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error marking all notifications as read', 'error' => $e->getMessage()], 500);
        }
    }

    // Get unread count
    public function getUnreadCount() {
        try {
            $payload = $this->requireAuth();

            $sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND read_at IS NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub']]);
            $result = $stmt->fetch();

            return json_response(['unread_count' => $result['unread_count']]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching unread count', 'error' => $e->getMessage()], 500);
        }
    }

    // Create notification (for admin/employer use)
    public function create() {
        try {
            $payload = $this->requireAuth();
            
            // Only admin can create notifications
            if ($payload['role'] !== 'admin') {
                return json_response(['message' => 'Unauthorized'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $userId = $input['user_id'] ?? null;
            $type = $input['type'] ?? '';
            $title = $input['title'] ?? '';
            $message = $input['message'] ?? '';
            $data = $input['data'] ?? null;

            if (!$userId || !$type || !$title || !$message) {
                return json_response(['message' => 'Missing required fields'], 400);
            }

            $sql = "INSERT INTO notifications (user_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null
            ]);

            return json_response(['message' => 'Notification created successfully']);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error creating notification', 'error' => $e->getMessage()], 500);
        }
    }
}