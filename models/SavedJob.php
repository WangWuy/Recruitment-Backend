<?php

class SavedJob {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function save($userId, $jobId) {
        $sql = "INSERT INTO saved_jobs (user_id, job_id, saved_at) VALUES (:user_id, :job_id, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'job_id' => $jobId
        ]);
    }
    
    public function unsave($userId, $jobId) {
        $sql = "DELETE FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'job_id' => $jobId
        ]);
    }
    
    public function isSaved($userId, $jobId) {
        $sql = "SELECT COUNT(*) as count FROM saved_jobs WHERE user_id = :user_id AND job_id = :job_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'job_id' => $jobId
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    public function getSavedJobs($userId) {
        $sql = "SELECT sj.*, j.title, j.description, j.location, j.salary_min, j.salary_max, 
                       j.employment_type, j.experience_level, j.created_at as job_created_at,
                       c.name as company_name, c.logo_url as company_logo,
                       cat.name as category_name, cat.color as category_color
                FROM saved_jobs sj
                JOIN jobs j ON sj.job_id = j.id
                JOIN companies c ON j.company_id = c.id
                JOIN categories cat ON j.category_id = cat.id
                WHERE sj.user_id = :user_id
                ORDER BY sj.saved_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getSavedJobsCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM saved_jobs WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}
