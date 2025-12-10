<?php
class CategoryController {
    private PDO $db; public function __construct(PDO $db) { $this->db=$db; }
    public function index() { return json_response(['data'=>Category::all($this->db)]); }
}

