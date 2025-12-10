<?php

class Company {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function create($data) {
        $sql = "INSERT INTO companies (name, description, website, logo_url, address, phone, email, industry, size, founded_year, created_at) 
                VALUES (:name, :description, :website, :logo_url, :address, :phone, :email, :industry, :size, :founded_year, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function getById($id) {
        $sql = "SELECT * FROM companies WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAll() {
        $sql = "SELECT * FROM companies ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $sql = "UPDATE companies SET 
                    name = :name, 
                    description = :description, 
                    website = :website, 
                    logo_url = :logo_url, 
                    address = :address, 
                    phone = :phone, 
                    email = :email, 
                    industry = :industry, 
                    size = :size, 
                    founded_year = :founded_year, 
                    updated_at = NOW() 
                WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM companies WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
    
    public function getByUserId($userId) {
        $sql = "SELECT c.* FROM companies c 
                JOIN users u ON c.id = u.company_id 
                WHERE u.id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
