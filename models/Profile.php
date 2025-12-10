<?php
class Profile {
    public static function upsert(PDO $db, int $userId, array $data): void {
        $st = $db->prepare('SELECT id FROM profiles WHERE user_id=?');
        $st->execute([$userId]);
        if ($st->fetch()) {
            $up = $db->prepare('UPDATE profiles SET avatar_url=?, headline=?, education=?, experience=?, skills=?, cv_url=?, phone=?, address=?, linkedin=?, github=? WHERE user_id=?');
            $up->execute([
                $data['avatar_url'] ?? null,
                $data['headline'] ?? null,
                $data['education'] ?? null,
                $data['experience'] ?? null,
                $data['skills'] ?? null,
                $data['cv_url'] ?? null,
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['linkedin'] ?? null,
                $data['github'] ?? null,
                $userId
            ]);
        } else {
            $ins = $db->prepare('INSERT INTO profiles(user_id, avatar_url, headline, education, experience, skills, cv_url, phone, address, linkedin, github) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([
                $userId,
                $data['avatar_url'] ?? null,
                $data['headline'] ?? null,
                $data['education'] ?? null,
                $data['experience'] ?? null,
                $data['skills'] ?? null,
                $data['cv_url'] ?? null,
                $data['phone'] ?? null,
                $data['address'] ?? null,
                $data['linkedin'] ?? null,
                $data['github'] ?? null,
            ]);
        }
    }

    public static function get(PDO $db, int $userId): ?array {
        $st = $db->prepare('SELECT * FROM profiles WHERE user_id=? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: null;
    }
}

