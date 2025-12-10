<?php
ob_start(); // Start output buffering to catch any unwanted output BEFORE any output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
// Headers will be set by json_response function

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    http_response_code(204);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    exit;
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/controllers/' . $class . '.php',
        __DIR__ . '/models/' . $class . '.php',
        __DIR__ . '/config/' . $class . '.php',
        __DIR__ . '/helpers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

function env($key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $envPath = __DIR__ . '/.env';
        if (file_exists($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $trim = trim($line);
                if ($trim === '' || str_starts_with($trim, '#'))
                    continue;
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $k = trim($parts[0]);
                    $v = trim($parts[1]);
                    $cache[$k] = trim($v, "\"'");
                }
            }
        }
    }
    return $cache[$key] ?? $default;
}

function json_response($data, $status = 200)
{
    if (ob_get_length() > 0) {
        ob_clean(); // Clean any unwanted output if there is any
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    if (ob_get_length() > 0) {
        ob_end_flush();
    }
    exit;
}

function get_bearer_token()
{
    // Try multiple methods to get the Authorization header

    // Method 1: getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return trim($m[1]);
        }
    }

    // Method 2: $_SERVER['HTTP_AUTHORIZATION']
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return trim($m[1]);
        }
    }

    // Method 3: apache_request_headers()
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return trim($m[1]);
        }
    }

    // Method 4: Check REDIRECT_HTTP_AUTHORIZATION (for some server configs)
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $m)) {
            return trim($m[1]);
        }
    }

    return null;
}

// ===== LOGGING FUNCTION =====
function log_request($message, $data = [])
{
    $logFile = __DIR__ . '/logs/requests.log';
    $logDir = dirname($logFile);

    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";

    if (!empty($data)) {
        $logEntry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }

    $logEntry .= str_repeat('-', 80) . "\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
if ($base && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}

$uri = '/' . ltrim($uri, '/');
// Remove trailing slash to normalize routing
$uri = rtrim($uri, '/') ?: '/';

// ===== LOG INCOMING REQUEST =====
$requestBody = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

log_request('INCOMING REQUEST', [
    'method' => $method,
    'uri' => $uri,
    'query_params' => $_GET,
    'headers' => $headers,
    'raw_body' => $requestBody,
    'parsed_body' => json_decode($requestBody, true),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
]);

try {
    $db = (new Database())->getConnection();
    $jwt = new JwtHelper('your-secret-key');

    if ($uri === '/test' && $method === 'GET') {
        json_response([
            'status' => 'success',
            'message' => 'Backend is working!',
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } elseif ($uri === '/test-post' && $method === 'POST') {
        json_response([
            'status' => 'success',
            'message' => 'POST test working!',
            'method' => $method,
            'uri' => $uri,
            'raw_method' => $_SERVER['REQUEST_METHOD'] ?? 'null',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    } elseif ($uri === '/api/auth/register' && $method === 'POST') {
        (new AuthController($db, $jwt))->register();
    } elseif ($uri === '/api/auth/login' && $method === 'POST') {
        (new AuthController($db, $jwt))->login();
    } elseif ($uri === '/api/auth/google' && $method === 'POST') {
        (new AuthController($db, $jwt))->googleLogin();
    } elseif ($uri === '/api/auth/me' && $method === 'GET') {
        (new AuthController($db, $jwt))->me();

    } elseif (($uri === '/api/jobs' || $uri === '/api/jobs/') && $method === 'POST') {
        (new JobController($db, $jwt))->create();
    } elseif ($uri === '/api/jobs' && $method === 'GET') {
        (new JobController($db, $jwt))->getAll();
    } elseif (preg_match('#^/api/jobs/(\d+)$#', $uri, $m) && $method === 'GET') {
        (new JobController($db, $jwt))->show((int) $m[1]);
    } elseif (preg_match('#^/api/jobs/(\d+)$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new JobController($db, $jwt))->update((int) $m[1]);
    } elseif (preg_match('#^/api/jobs/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        (new JobController($db, $jwt))->delete((int) $m[1]);

    } elseif ($uri === '/api/applications' && $method === 'POST') {
        (new ApplicationController($db, $jwt))->apply();
    } elseif ($uri === '/api/applications' && $method === 'GET') {
        (new ApplicationController($db, $jwt))->listForEmployer();
    } elseif (preg_match('#^/api/applications/(\d+)$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new ApplicationController($db, $jwt))->updateStatus((int) $m[1]);

    } elseif ($uri === '/api/upload' && $method === 'POST') {
        (new UploadController($db, $jwt))->upload();

    } elseif ($uri === '/api/profile' && $method === 'GET') {
        (new ProfileController($db, $jwt))->getMine();
    } elseif ($uri === '/api/profile' && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        (new ProfileController($db, $jwt))->upsertMine();

    } elseif ($uri === '/api/categories' && $method === 'GET') {
        (new CategoryController($db))->index();

    } elseif ($uri === '/api/notifications' && $method === 'GET') {
        (new NotificationController($db, $jwt))->mine();

    } elseif ($uri === '/api/news' && $method === 'GET') {
        (new NewsController($db, $jwt))->getAll();
    } elseif (preg_match('#^/api/news/(\d+)$#', $uri, $m) && $method === 'GET') {
        (new NewsController($db, $jwt))->getById((int) $m[1]);
    } elseif ($uri === '/api/news/categories' && $method === 'GET') {
        (new NewsController($db, $jwt))->getCategories();

    } elseif (preg_match('#^/api/jobs/(\d+)/status$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new JobController($db, $jwt))->updateStatus((int) $m[1]);
    } elseif ($uri === '/api/jobs/saved' && $method === 'GET') {
        (new JobController($db, $jwt))->getSaved();
    } elseif ($uri === '/api/jobs/save' && $method === 'POST') {
        (new JobController($db, $jwt))->toggleSave();
    } elseif ($uri === '/api/jobs/apply' && $method === 'POST') {
        (new JobController($db, $jwt))->apply();
    } elseif ($uri === '/api/jobs/applications' && $method === 'GET') {
        (new JobController($db, $jwt))->getApplications();

    } elseif ($uri === '/api/employer/jobs' && $method === 'GET') {
        (new EmployerController($db, $jwt))->getMyJobs();
    } elseif ($uri === '/api/employer/applications' && $method === 'GET') {
        (new EmployerController($db, $jwt))->getApplications();
    } elseif (preg_match('#^/api/employer/applications/(\d+)$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new EmployerController($db, $jwt))->updateApplicationStatus((int) $m[1]);
    } elseif ($uri === '/api/employer/dashboard-stats' && $method === 'GET') {
        (new EmployerController($db, $jwt))->getDashboardStats();
    } elseif ($uri === '/api/employer/recent-applications' && $method === 'GET') {
        (new EmployerController($db, $jwt))->getRecentApplications();
    } elseif ($uri === '/api/employer/profile' && $method === 'GET') {
        (new EmployerController($db, $jwt))->getProfile();
    } elseif ($uri === '/api/employer/profile' && in_array($method, ['PUT', 'PATCH'])) {
        (new EmployerController($db, $jwt))->updateProfile();
    } elseif ($uri === '/api/employer/upload-logo' && $method === 'POST') {
        (new EmployerController($db, $jwt))->uploadLogo();

        // Gemini AI Routes
    } elseif ($uri === '/api/gemini/chat' && $method === 'POST') {
        (new GeminiController())->sendMessage();
    } elseif ($uri === '/api/gemini/job-recommendations' && $method === 'POST') {
        (new GeminiController())->getJobRecommendations();
    } elseif ($uri === '/api/gemini/cv-suggestions' && $method === 'POST') {
        (new GeminiController())->getCVSuggestions();
    } elseif ($uri === '/api/gemini/interview-prep' && $method === 'POST') {
        (new GeminiController())->getInterviewPrep();

    } elseif ($uri === '/api/admin/stats' && $method === 'GET') {
        (new AdminController($db, $jwt))->stats();

        // Admin - User Management
    } elseif ($uri === '/api/admin/users' && $method === 'POST') {
        (new AdminController($db, $jwt))->createUser();
    } elseif ($uri === '/api/admin/users' && $method === 'GET') {
        (new AdminController($db, $jwt))->getAllUsers();
    } elseif (preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) && $method === 'GET') {
        (new AdminController($db, $jwt))->getUser((int) $m[1]);
    } elseif (preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new AdminController($db, $jwt))->updateUser((int) $m[1]);
    } elseif (preg_match('#^/api/admin/users/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        (new AdminController($db, $jwt))->deleteUser((int) $m[1]);

        // Admin - Job Management
    } elseif ($uri === '/api/admin/jobs' && $method === 'GET') {
        (new AdminController($db, $jwt))->getAllJobs();
    } elseif (preg_match('#^/api/admin/jobs/(\d+)$#', $uri, $m) && $method === 'GET') {
        (new AdminController($db, $jwt))->getJob((int) $m[1]);
    } elseif (preg_match('#^/api/admin/jobs/(\d+)$#', $uri, $m) && in_array($method, ['PUT', 'PATCH'])) {
        (new AdminController($db, $jwt))->updateJob((int) $m[1]);
    } elseif (preg_match('#^/api/admin/jobs/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        (new AdminController($db, $jwt))->deleteJob((int) $m[1]);
    } elseif (preg_match('#^/api/admin/jobs/(\d+)/moderate$#', $uri, $m) && in_array($method, ['POST', 'PUT'])) {
        (new AdminController($db, $jwt))->moderateJob((int) $m[1]);

    } else {
        json_response(['message' => 'Endpoint not found', 'path' => $uri], 404);
    }
} catch (Throwable $e) {
    // Log the error
    log_request('ERROR', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    if (ob_get_length() > 0) {
        ob_clean(); // Clean any output before sending error
    }
    json_response([
        'message' => 'Server error',
        'error' => $e->getMessage(),
    ], 500);
}


