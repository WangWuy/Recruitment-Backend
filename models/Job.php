<?php
class Job {
    public static function list(PDO $db, array $filters = []): array {
        $sql = 'SELECT j.*, c.name as company_name FROM jobs j LEFT JOIN companies c ON c.id=j.company_id WHERE j.status = "published"';
        $params = [];
        if (!empty($filters['keyword'])) { $sql .= ' AND (j.title LIKE ? OR j.location LIKE ?)'; $params[] = "%".$filters['keyword']."%"; $params[] = "%".$filters['keyword']."%"; }
        if (!empty($filters['category_id'])) { $sql .= ' AND j.category_id = ?'; $params[] = (int)$filters['category_id']; }
        if (!empty($filters['min_salary'])) { $sql .= ' AND j.salary_min >= ?'; $params[] = (int)$filters['min_salary']; }
        if (!empty($filters['location'])) { $sql .= ' AND j.location LIKE ?'; $params[] = "%".$filters['location']."%"; }
        $sql .= ' ORDER BY j.created_at DESC LIMIT 100';
        $st = $db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function find(PDO $db, int $id): ?array {
        $st = $db->prepare('SELECT j.*, c.name as company_name FROM jobs j LEFT JOIN companies c ON c.id=j.company_id WHERE j.id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function create(PDO $db, array $data, int $employerId): int {
        $st = $db->prepare('INSERT INTO jobs(company_id, title, description, requirements, location, salary_min, salary_max, category_id, status, created_by, created_at) VALUES(?,?,?,?,?,?,?,?,"published",?,NOW())');
        $st->execute([
            $data['company_id'], $data['title'], $data['description'] ?? '', $data['requirements'] ?? '',
            $data['location'] ?? '', $data['salary_min'] ?? 0, $data['salary_max'] ?? 0, $data['category_id'] ?? null,
            $employerId
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(PDO $db, int $id, array $data): bool {
        $st = $db->prepare('UPDATE jobs SET title=?, description=?, requirements=?, location=?, salary_min=?, salary_max=?, category_id=? WHERE id=?');
        return $st->execute([
            $data['title'], $data['description'] ?? '', $data['requirements'] ?? '', $data['location'] ?? '',
            $data['salary_min'] ?? 0, $data['salary_max'] ?? 0, $data['category_id'] ?? null, $id
        ]);
    }

    public static function delete(PDO $db, int $id): bool {
        $st = $db->prepare('DELETE FROM jobs WHERE id=?');
        return $st->execute([$id]);
    }
}

