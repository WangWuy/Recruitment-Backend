R
<?php

class JobController
{
    private PDO $db;
    private JwtHelper $jwt;

    public function __construct(PDO $db, JwtHelper $jwt)
    {
        $this->db = $db;
        $this->jwt = $jwt;
    }

    private function requireAuth(): array
    {
        $token = get_bearer_token();
        $payload = $token ? $this->jwt->decode($token) : null;
        if (!$payload)
            json_response(['message' => 'Unauthorized'], 401);
        return $payload;
    }

    // Get all jobs with filters
    public function getAll()
    {
        try {
            $keyword = $_GET['keyword'] ?? '';
            $categoryId = $_GET['category_id'] ?? null;
            $location = $_GET['location'] ?? '';
            $salaryMin = $_GET['salary_min'] ?? null;
            $salaryMax = $_GET['salary_max'] ?? null;
            $employmentType = $_GET['employment_type'] ?? '';
            $experienceLevel = $_GET['experience_level'] ?? '';
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            $whereConditions = ["j.status = 'active'"];
            $params = [];

            if ($keyword) {
                $whereConditions[] = "(j.title LIKE :keyword OR j.description LIKE :keyword OR c.name LIKE :keyword)";
                $params['keyword'] = "%$keyword%";
            }

            if ($categoryId) {
                $whereConditions[] = "j.category_id = :category_id";
                $params['category_id'] = $categoryId;
            }

            if ($location) {
                $whereConditions[] = "j.location LIKE :location";
                $params['location'] = "%$location%";
            }

            if ($salaryMin) {
                $whereConditions[] = "j.salary_max >= :salary_min";
                $params['salary_min'] = $salaryMin;
            }

            if ($salaryMax) {
                $whereConditions[] = "j.salary_min <= :salary_max";
                $params['salary_max'] = $salaryMax;
            }

            if ($employmentType) {
                $whereConditions[] = "j.employment_type = :employment_type";
                $params['employment_type'] = $employmentType;
            }

            if ($experienceLevel) {
                $whereConditions[] = "j.experience_level = :experience_level";
                $params['experience_level'] = $experienceLevel;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM jobs j 
                        JOIN companies c ON j.company_id = c.id 
                        WHERE $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Get jobs
            $sql = "SELECT j.*, c.name as company_name, c.logo_url as company_logo,\n                       cat.name as category_name\n                FROM jobs j\n                JOIN companies c ON j.company_id = c.id\n                LEFT JOIN categories cat ON j.category_id = cat.id\n                WHERE $whereClause\n                ORDER BY j.featured DESC, j.created_at DESC\n                LIMIT :limit OFFSET :offset";
            $stmt = $this->db->prepare($sql);
            // Bind parameters (excluding limit/offset)
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_response([
                'data' => $jobs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching jobs', 'error' => $e->getMessage()], 500);
        }
    }

    // Get job by ID
    public function getById($id)
    {
        try {
            $sql = "SELECT j.*, c.name as company_name, c.logo_url as company_logo, 
                           c.description as company_description, c.website as company_website,
                           cat.name as category_name
                    FROM jobs j 
                    JOIN companies c ON j.company_id = c.id 
                    LEFT JOIN categories cat ON j.category_id = cat.id 
                    WHERE j.id = :id AND j.status = 'active'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                return json_response(['message' => 'Job not found'], 404);
            }

            // Increment view count
            $updateStmt = $this->db->prepare("UPDATE jobs SET views_count = views_count + 1 WHERE id = :id");
            $updateStmt->execute(['id' => $id]);

            return json_response(['data' => $job]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching job', 'error' => $e->getMessage()], 500);
        }
    }

    // Apply for job
    public function apply()
    {
        try {
            $payload = $this->requireAuth();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $jobId = $input['job_id'] ?? null;
            $coverLetter = $input['cover_letter'] ?? '';
            $expectedSalary = $input['expected_salary'] ?? null;
            $availableFrom = $input['available_from'] ?? null;
            $cvUrl = $input['cv_url'] ?? null;

            if (!$jobId) {
                return json_response(['message' => 'Job ID is required'], 400);
            }

            // Check if job exists and is active
            $jobStmt = $this->db->prepare("SELECT id FROM jobs WHERE id = ? AND status = 'active'");
            $jobStmt->execute([$jobId]);
            if (!$jobStmt->fetch()) {
                return json_response(['message' => 'Job not found or not active'], 404);
            }

            // Check if already applied
            $existingStmt = $this->db->prepare("SELECT id FROM applications WHERE job_id = ? AND candidate_id = ?");
            $existingStmt->execute([$jobId, $payload['sub']]);
            if ($existingStmt->fetch()) {
                return json_response(['message' => 'Already applied for this job'], 400);
            }

            // Create application
            $sql = "INSERT INTO applications (job_id, candidate_id, cover_letter, expected_salary, available_from, cv_url) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$jobId, $payload['sub'], $coverLetter, $expectedSalary, $availableFrom, $cvUrl]);

            // Update job applications count
            $updateStmt = $this->db->prepare("UPDATE jobs SET applications_count = applications_count + 1 WHERE id = ?");
            $updateStmt->execute([$jobId]);

            return json_response(['message' => 'Application submitted successfully']);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error applying for job', 'error' => $e->getMessage()], 500);
        }
    }

    // Save/unsave job
    public function toggleSave()
    {
        try {
            $payload = $this->requireAuth();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $jobId = $input['job_id'] ?? null;
            if (!$jobId) {
                return json_response(['message' => 'Job ID is required'], 400);
            }

            // Check if already saved
            $existingStmt = $this->db->prepare("SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ?");
            $existingStmt->execute([$jobId, $payload['sub']]);
            $existing = $existingStmt->fetch();

            if ($existing) {
                // Remove from saved
                $deleteStmt = $this->db->prepare("DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ?");
                $deleteStmt->execute([$jobId, $payload['sub']]);
                return json_response(['message' => 'Job removed from saved', 'saved' => false]);
            } else {
                // Add to saved
                $insertStmt = $this->db->prepare("INSERT INTO saved_jobs (job_id, user_id) VALUES (?, ?)");
                $insertStmt->execute([$jobId, $payload['sub']]);
                return json_response(['message' => 'Job saved successfully', 'saved' => true]);
            }
        } catch (Throwable $e) {
            return json_response(['message' => 'Error saving job', 'error' => $e->getMessage()], 500);
        }
    }

    // Get saved jobs
    public function getSaved()
    {
        try {
            $payload = $this->requireAuth();

            $sql = "SELECT j.*, c.name as company_name, c.logo_url as company_logo, 
                           cat.name as category_name,
                           sj.saved_at
                    FROM saved_jobs sj
                    JOIN jobs j ON sj.job_id = j.id
                    JOIN companies c ON j.company_id = c.id
                    LEFT JOIN categories cat ON j.category_id = cat.id
                    WHERE sj.user_id = ? AND j.status = 'active'
                    ORDER BY sj.saved_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub']]);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_response(['data' => $jobs]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching saved jobs', 'error' => $e->getMessage()], 500);
        }
    }

    // Get application history
    public function getApplications()
    {
        try {
            $payload = $this->requireAuth();

            $sql = "SELECT a.*, j.title as job_title, j.location as job_location,
                           c.name as company_name, c.logo_url as company_logo
                    FROM applications a
                    JOIN jobs j ON a.job_id = j.id
                    JOIN companies c ON j.company_id = c.id
                    WHERE a.candidate_id = ?
                    ORDER BY a.applied_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub']]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_response(['data' => $applications]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching applications', 'error' => $e->getMessage()], 500);
        }
    }

    // Create new job (for employers)
    public function create()
    {
        try {
            $payload = $this->requireAuth();

            // Only employers can create jobs
            if ($payload['role'] !== 'employer') {
                return json_response(['message' => 'Unauthorized - Only employers can create jobs'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Get company_id from user's company or create one
            $userId = $payload['sub'];
            $companyStmt = $this->db->prepare("SELECT id FROM companies WHERE user_id = ?");
            $companyStmt->execute([$userId]);
            $company = $companyStmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                // Create a default company for this user
                $companyName = $payload['email'] ?? 'Company ' . $userId;
                $createCompanyStmt = $this->db->prepare("INSERT INTO companies (name, user_id, created_at) VALUES (?, ?, NOW())");
                $createCompanyStmt->execute([$companyName, $userId]);
                $companyId = $this->db->lastInsertId();
            } else {
                $companyId = $company['id'];
            }
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $requirements = $input['requirements'] ?? '';
            $location = $input['location'] ?? '';
            $salaryMin = $input['salary_min'] ?? null;
            $salaryMax = $input['salary_max'] ?? null;
            $categoryId = $input['category_id'] ?? null;
            $employmentType = $input['employment_type'] ?? 'full_time';
            $experienceLevel = $input['experience_level'] ?? null;
            $isRemote = $input['is_remote'] ?? false;
            $isUrgent = $input['is_urgent'] ?? false;
            $isFeatured = $input['is_featured'] ?? false;
            $status = 'active'; // Temporarily remove pending approval

            if (!$title || !$description || !$location) {
                return json_response(['message' => 'Missing required fields'], 400);
            }

            $sql = "INSERT INTO jobs (company_id, category_id, title, description, requirements, 
                    location, salary_min, salary_max, employment_type, experience_level, 
                    remote_option, urgent, featured, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $remoteOption = $isRemote ? 'full' : 'no';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $companyId,
                $categoryId,
                $title,
                $description,
                $requirements,
                $location,
                $salaryMin,
                $salaryMax,
                $employmentType,
                $experienceLevel,
                $remoteOption,
                $isUrgent ? 1 : 0,
                $isFeatured ? 1 : 0,
                $status
            ]);

            $jobId = $this->db->lastInsertId();

            return json_response([
                'message' => 'Job created successfully',
                'id' => $jobId
            ]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error creating job', 'error' => $e->getMessage()], 500);
        }
    }

    // Update job status
    public function updateStatus($id)
    {
        try {
            $payload = $this->requireAuth();

            // Only employers can update job status
            if ($payload['role'] !== 'employer') {
                return json_response(['message' => 'Unauthorized - Only employers can update job status'], 403);
            }

            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $status = $input['status'] ?? '';

            if (!in_array($status, ['active', 'paused', 'closed', 'draft'])) {
                return json_response(['message' => 'Invalid status'], 400);
            }

            // Check if job belongs to employer's company
            $checkSql = "SELECT j.id FROM jobs j 
                        JOIN companies c ON j.company_id = c.id 
                        WHERE j.id = ? AND c.user_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id, $payload['sub']]);

            if (!$checkStmt->fetch()) {
                return json_response(['message' => 'Job not found or unauthorized'], 404);
            }

            // Update job status
            $sql = "UPDATE jobs SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);

            return json_response(['message' => 'Job status updated successfully']);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error updating job status', 'error' => $e->getMessage()], 500);
        }
    }
}
