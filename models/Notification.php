<?php
class NotificationModel {
    public static function listForUser(PDO $db, int $userId): array {
        $st = $db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
        $st->execute([$userId]);
        return $st->fetchAll();
    }
}

