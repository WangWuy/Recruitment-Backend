<?php

class NewsController {
    private PDO $db;
    private JwtHelper $jwt;

    public function __construct(PDO $db, JwtHelper $jwt) {
        $this->db = $db;
        $this->jwt = $jwt;
    }

    // Get all news with filters
    public function getAll() {
        try {
            $category = $_GET['category'] ?? '';
            $keyword = $_GET['keyword'] ?? '';
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $whereConditions = ["status = 'published'"];
            $params = [];

            if ($category && $category !== 'Tất cả') {
                $whereConditions[] = "category = :category";
                $params['category'] = $category;
            }

            if ($keyword) {
                $whereConditions[] = "(title LIKE :keyword OR content LIKE :keyword OR summary LIKE :keyword)";
                $params['keyword'] = "%$keyword%";
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM news WHERE $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Get news
            $sql = "SELECT * FROM news 
                    WHERE $whereClause 
                    ORDER BY published_at DESC, created_at DESC 
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($news as &$item) {
                if ($item['tags']) {
                    $item['tags'] = json_decode($item['tags'], true) ?? [];
                }
            }

            return json_response([
                'data' => $news,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching news', 'error' => $e->getMessage()], 500);
        }
    }

    // Get news by ID
    public function getById($id) {
        try {
            $sql = "SELECT * FROM news WHERE id = :id AND status = 'published'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $news = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$news) {
                return json_response(['message' => 'News not found'], 404);
            }

            // Increment view count
            $updateStmt = $this->db->prepare("UPDATE news SET views_count = views_count + 1 WHERE id = :id");
            $updateStmt->execute(['id' => $id]);

            // Parse JSON fields
            if ($news['tags']) {
                $news['tags'] = json_decode($news['tags'], true) ?? [];
            }

            return json_response(['data' => $news]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching news', 'error' => $e->getMessage()], 500);
        }
    }

    // Get categories
    public function getCategories() {
        try {
            $sql = "SELECT DISTINCT category FROM news WHERE status = 'published' ORDER BY category";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return json_response(['data' => $categories]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching categories', 'error' => $e->getMessage()], 500);
        }
    }
}
