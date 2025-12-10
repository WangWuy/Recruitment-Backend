<?php
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Application.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';
require_once __DIR__ . '/../helpers/EmailHelper.php';

// Include json_response function
if (!function_exists('json_response')) {
    function json_response($data, $status = 200)
    {
        ob_clean(); // Clean any unwanted output
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit;
    }
}

// Include get_bearer_token function
if (!function_exists('get_bearer_token')) {
    function get_bearer_token()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}

class EmployerController
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

    private function requireEmployer(): array
    {
        $payload = $this->requireAuth();
        if ($payload['role'] !== 'employer') {
            json_response(['message' => 'Unauthorized - Only employers can access this'], 403);
        }
        return $payload;
    }

    // Get employer's jobs
    public function getMyJobs()
    {
        try {
            $payload = $this->requireEmployer();
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM jobs j 
                        JOIN companies c ON j.company_id = c.id 
                        WHERE c.user_id = ?";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$payload['sub']]);
            $total = $countStmt->fetch()['total'];

            // Get jobs
            $sql = "SELECT j.*, c.name as company_name, c.logo_url as company_logo,
                           cat.name as category_name, cat.color as category_color
                    FROM jobs j 
                    JOIN companies c ON j.company_id = c.id 
                    JOIN categories cat ON j.category_id = cat.id 
                    WHERE c.user_id = ?
                    ORDER BY j.created_at DESC 
                    LIMIT ? OFFSET ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub'], $limit, $offset]);
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

    // Get applications for employer's jobs
    public function getApplications()
    {
        try {
            $payload = $this->requireEmployer();
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 10);
            $offset = ($page - 1) * $limit;
            $status = $_GET['status'] ?? null;

            $whereConditions = ["c.user_id = ?"];
            $params = [$payload['sub']];

            if ($status) {
                $whereConditions[] = "a.status = ?";
                $params[] = $status;
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM applications a
                        JOIN jobs j ON a.job_id = j.id
                        JOIN companies c ON j.company_id = c.id
                        WHERE $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];

            // Get applications
            $sql = "SELECT a.*, j.title as job_title, j.location as job_location,
                           c.name as company_name, c.logo_url as company_logo,
                           u.name as candidate_name, u.email as candidate_email
                    FROM applications a
                    JOIN jobs j ON a.job_id = j.id
                    JOIN companies c ON j.company_id = c.id
                    JOIN users u ON a.candidate_id = u.id
                    WHERE $whereClause
                    ORDER BY a.applied_at DESC 
                    LIMIT ? OFFSET ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([...$params, $limit, $offset]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_response([
                'data' => $applications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching applications', 'error' => $e->getMessage()], 500);
        }
    }

    // Update application status
    public function updateApplicationStatus($applicationId)
    {
        try {
            $payload = $this->requireEmployer();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            $newStatus = $input['status'] ?? null;
            $interviewDate = $input['interview_date'] ?? null;
            $interviewLocation = $input['interview_location'] ?? null;
            $interviewNotes = $input['interview_notes'] ?? null;
            $rejectionReason = $input['rejection_reason'] ?? null;

            if (!$newStatus) {
                return json_response(['message' => 'Status is required'], 400);
            }

            // Check if employer owns this application and get candidate info
            $checkSql = "SELECT a.id, a.candidate_id, 
                               u.name as candidate_name, u.email as candidate_email,
                               j.title as job_title, c.name as company_name
                        FROM applications a
                        JOIN jobs j ON a.job_id = j.id
                        JOIN companies c ON j.company_id = c.id
                        JOIN users u ON a.candidate_id = u.id
                        WHERE a.id = ? AND c.user_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$applicationId, $payload['sub']]);

            $application = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return json_response(['message' => 'Application not found or unauthorized'], 404);
            }

            // Update application
            $updateSql = "UPDATE applications SET 
                         status = ?, 
                         interview_date = ?, 
                         interview_location = ?, 
                         interview_notes = ?, 
                         rejection_reason = ?,
                         updated_at = NOW()
                         WHERE id = ?";

            $stmt = $this->db->prepare($updateSql);
            $stmt->execute([
                $newStatus,
                $interviewDate,
                $interviewLocation,
                $interviewNotes,
                $rejectionReason,
                $applicationId
            ]);

            // Send email notification to candidate
            try {
                $emailHelper = new EmailHelper();
                $additionalInfo = [
                    'interview_date' => $interviewDate,
                    'interview_location' => $interviewLocation,
                    'interview_notes' => $interviewNotes,
                    'rejection_reason' => $rejectionReason
                ];

                $emailSent = $emailHelper->sendApplicationStatusUpdate(
                    $application['candidate_email'],
                    $application['candidate_name'],
                    $application['job_title'],
                    $application['company_name'],
                    $newStatus,
                    $additionalInfo
                );

                if (!$emailSent) {
                    error_log("Failed to send email notification for application ID: {$applicationId}");
                }
            } catch (Exception $e) {
                // Log error but don't fail the request
                error_log("Email notification error: " . $e->getMessage());
            }

            return json_response([
                'message' => 'Application status updated successfully',
                'email_sent' => $emailSent ?? false
            ]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error updating application status', 'error' => $e->getMessage()], 500);
        }
    }

    // Get employer dashboard stats
    public function getDashboardStats()
    {
        try {
            $payload = $this->requireEmployer();

            // Get company ID
            $companySql = "SELECT id FROM companies WHERE user_id = ?";
            $companyStmt = $this->db->prepare($companySql);
            $companyStmt->execute([$payload['sub']]);
            $company = $companyStmt->fetch();

            if (!$company) {
                return json_response(['message' => 'Company not found'], 404);
            }

            $companyId = $company['id'];

            // Get job stats
            $jobStatsSql = "SELECT 
                COUNT(*) as total_jobs,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_jobs,
                COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_jobs,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_jobs,
                SUM(views_count) as total_views,
                SUM(applications_count) as total_applications
                FROM jobs WHERE company_id = ?";

            $jobStatsStmt = $this->db->prepare($jobStatsSql);
            $jobStatsStmt->execute([$companyId]);
            $jobStats = $jobStatsStmt->fetch();

            // Get application stats
            $appStatsSql = "SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN a.status = 'pending' THEN 1 END) as pending_applications,
                COUNT(CASE WHEN a.status = 'reviewed' THEN 1 END) as reviewed_applications,
                COUNT(CASE WHEN a.status = 'shortlisted' THEN 1 END) as shortlisted_applications,
                COUNT(CASE WHEN a.status = 'interview' THEN 1 END) as interview_applications,
                COUNT(CASE WHEN a.status = 'rejected' THEN 1 END) as rejected_applications,
                COUNT(CASE WHEN a.status = 'hired' THEN 1 END) as hired_applications
                FROM applications a
                JOIN jobs j ON a.job_id = j.id
                WHERE j.company_id = ?";

            $appStatsStmt = $this->db->prepare($appStatsSql);
            $appStatsStmt->execute([$companyId]);
            $appStats = $appStatsStmt->fetch();

            return json_response([
                'data' => [
                    'jobs' => $jobStats,
                    'applications' => $appStats
                ]
            ]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching dashboard stats', 'error' => $e->getMessage()], 500);
        }
    }

    // Get recent applications
    public function getRecentApplications()
    {
        try {
            $payload = $this->requireEmployer();
            $limit = (int) ($_GET['limit'] ?? 5);

            $sql = "SELECT a.*, j.title as job_title, j.location as job_location,
                           c.name as company_name, c.logo_url as company_logo,
                           u.name as candidate_name, u.email as candidate_email
                    FROM applications a
                    JOIN jobs j ON a.job_id = j.id
                    JOIN companies c ON j.company_id = c.id
                    JOIN users u ON a.candidate_id = u.id
                    WHERE c.user_id = ?
                    ORDER BY a.applied_at DESC
                    LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub'], $limit]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return json_response(['data' => $applications]);

        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching recent applications', 'error' => $e->getMessage()], 500);
        }
    }

    // Get employer/company profile
    public function getProfile()
    {
        try {
            $payload = $this->requireEmployer();

            // Get company info linked to this user
            $sql = "SELECT c.*, u.name as contact_name, u.email as contact_email
                    FROM companies c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$payload['sub']]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                // Return empty profile if not found
                return json_response([
                    'message' => 'Profile not found, returning empty profile',
                    'data' => [
                        'id' => null,
                        'user_id' => $payload['sub'],
                        'name' => '',
                        'description' => '',
                        'logo_url' => '',
                        'website' => '',
                        'address' => '',
                        'phone' => '',
                        'email' => '',
                        'industry' => '',
                        'size' => '',
                        'founded_year' => null
                    ]
                ]);
            }

            return json_response(['data' => $company]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error fetching profile', 'error' => $e->getMessage()], 500);
        }
    }

    // Update employer/company profile
    public function updateProfile()
    {
        try {
            $payload = $this->requireEmployer();
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Get current company ID
            $checkSql = "SELECT id FROM companies WHERE user_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$payload['sub']]);
            $company = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                // Create new company profile if not exists
                $insertSql = "INSERT INTO companies (user_id, name, description, website, address, phone, email, industry, size, founded_year, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([
                    $payload['sub'],
                    $input['name'] ?? '',
                    $input['description'] ?? null,
                    $input['website'] ?? null,
                    $input['address'] ?? null,
                    $input['phone'] ?? null,
                    $input['email'] ?? null,
                    $input['industry'] ?? null,
                    $input['size'] ?? null,
                    $input['founded_year'] ?? null
                ]);
                return json_response(['message' => 'Profile created successfully']);
            }

            $companyId = $company['id'];

            // Build update query dynamically
            $allowedFields = ['name', 'description', 'website', 'logo_url', 'address', 'phone', 'email', 'industry', 'size', 'founded_year'];
            $updateFields = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            if (empty($updateFields)) {
                return json_response(['message' => 'No fields to update'], 400);
            }

            $params[] = $companyId;
            $sql = "UPDATE companies SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return json_response(['message' => 'Profile updated successfully']);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error updating profile', 'error' => $e->getMessage()], 500);
        }
    }

    // Upload company logo/avatar
    public function uploadLogo()
    {
        try {
            $payload = $this->requireEmployer();

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return json_response(['message' => 'No file uploaded or upload error'], 400);
            }

            $file = $_FILES['file'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedTypes)) {
                return json_response(['message' => 'Invalid file type. Only images are allowed.'], 400);
            }

            // Validate file size
            if ($file['size'] > $maxSize) {
                return json_response(['message' => 'File too large. Maximum size is 5MB.'], 400);
            }

            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'company_logo_' . $payload['sub'] . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/logos/';

            // Create directory if not exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $destination = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                return json_response(['message' => 'Failed to move uploaded file'], 500);
            }

            // Generate URL
            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
            $logoUrl = $baseUrl . $scriptPath . '/uploads/logos/' . $filename;

            // Update company logo_url in database
            $checkSql = "SELECT id FROM companies WHERE user_id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$payload['sub']]);
            $company = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($company) {
                $updateSql = "UPDATE companies SET logo_url = ?, updated_at = NOW() WHERE id = ?";
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([$logoUrl, $company['id']]);
            } else {
                // Create company profile if not exists
                $insertSql = "INSERT INTO companies (user_id, logo_url, created_at) VALUES (?, ?, NOW())";
                $insertStmt = $this->db->prepare($insertSql);
                $insertStmt->execute([$payload['sub'], $logoUrl]);
            }

            return json_response([
                'message' => 'Logo uploaded successfully',
                'logo_url' => $logoUrl,
                'filename' => $filename
            ]);
        } catch (Throwable $e) {
            return json_response(['message' => 'Error uploading logo', 'error' => $e->getMessage()], 500);
        }
    }
}
