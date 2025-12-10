<?php
class User
{
    public static function findByEmail(PDO $db, string $email): ?array
    {
        $st = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findById(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function create(PDO $db, array $data): int
    {
        $st = $db->prepare('INSERT INTO users(name, email, phone, password_hash, role, status, created_at) VALUES(?,?,?,?,?,?,NOW())');
        $st->execute([
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            password_hash($data['password'], PASSWORD_BCRYPT),
            $data['role'] ?? 'candidate',
            'active'
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateName(PDO $db, int $userId, string $name): void
    {
        $st = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
        $st->execute([$name, $userId]);
    }

    // Google OAuth methods
    public static function findByGoogleId(PDO $db, string $googleId): ?array
    {
        $st = $db->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
        $st->execute([$googleId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function createFromGoogle(PDO $db, array $data): int
    {
        $st = $db->prepare('INSERT INTO users(name, email, google_id, photo_url, role, status, created_at) VALUES(?,?,?,?,?,?,NOW())');
        $st->execute([
            $data['name'],
            $data['email'],
            $data['google_id'],
            $data['photo_url'] ?? null,
            $data['role'] ?? 'candidate',
            'active'
        ]);
        return (int) $db->lastInsertId();
    }

    public static function updateGoogleInfo(PDO $db, int $userId, array $data): void
    {
        $st = $db->prepare('UPDATE users SET name = ?, photo_url = ? WHERE id = ?');
        $st->execute([
            $data['name'],
            $data['photo_url'] ?? null,
            $userId
        ]);
    }
}
