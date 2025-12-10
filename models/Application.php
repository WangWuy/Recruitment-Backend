<?php

class Application {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function create($data) {
        $sql = "INSERT INTO applications (job_id, candidate_id, status, cover_letter, cv_url, expected_salary, available_from, applied_at) 
                VALUES (:job_id, :candidate_id, :status, :cover_letter, :cv_url, :expected_salary, :available_from, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }
    
    public function getByJobId($jobId) {
        $sql = "SELECT a.*, u.name as candidate_name, u.email as candidate_email, u.phone as candidate_phone
                FROM applications a 
                JOIN users u ON a.candidate_id = u.id 
                WHERE a.job_id = :job_id 
                ORDER BY a.applied_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getByCandidateId($candidateId) {
        $sql = "SELECT a.*, j.title as job_title, c.name as company_name
                FROM applications a 
                JOIN jobs j ON a.job_id = j.id 
                JOIN companies c ON j.company_id = c.id 
                WHERE a.candidate_id = :candidate_id 
                ORDER BY a.applied_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($applicationId, $status, $notes = null) {
        $sql = "UPDATE applications 
                SET status = :status, interview_notes = :notes, updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id' => $applicationId,
            'status' => $status,
            'notes' => $notes
        ]);
    }
    
    public function getStatsByEmployer($employerId) {
        $sql = "SELECT 
                    COUNT(*) as total_applications,
                    COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_applications,
                    COUNT(CASE WHEN a.status = 'reviewed' THEN 1 END) as reviewed_applications,
                    COUNT(CASE WHEN a.status = 'shortlisted' THEN 1 END) as shortlisted_applications,
                    COUNT(CASE WHEN a.status = 'interview' THEN 1 END) as interview_applications,
                    COUNT(CASE WHEN a.status = 'hired' THEN 1 END) as hired_applications,
                    COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_applications
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                JOIN companies c ON j.company_id = c.id
                WHERE c.id = :employer_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['employer_id' => $employerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getRecentApplications($employerId, $limit = 10) {
        $sql = "SELECT a.*, u.name as candidate_name, u.email as candidate_email, 
                       j.title as job_title, c.name as company_name
                FROM applications a
                JOIN users u ON a.candidate_id = u.id
                JOIN jobs j ON a.job_id = j.id
                JOIN companies c ON j.company_id = c.id
                WHERE c.id = :employer_id
                ORDER BY a.applied_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':employer_id', $employerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
