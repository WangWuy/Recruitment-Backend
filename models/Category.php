<?php
class Category {
    public static function all(PDO $db): array {
        return $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    }
}

