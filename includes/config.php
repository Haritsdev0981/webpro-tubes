<?php
// ============================================
// TELOVED - Database Configuration
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'teloved');
define('BASE_URL', 'http://localhost/teloved');

// App config
define('APP_NAME', 'Teloved');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Helper: JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    echo json_encode($data);
    exit;
}

// Helper: API Key Auth
function requireApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if (empty($apiKey)) {
        jsonResponse(['error' => 'API Key required'], 401);
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch();
    if (!$user) {
        jsonResponse(['error' => 'Invalid or inactive API Key'], 401);
    }
    return $user;
}

// Helper: Check feature permission
function hasFeaturePermission($userId, $featureName) {
    $db = getDB();
    $stmt = $db->prepare("SELECT is_allowed FROM feature_permissions WHERE user_id = ? AND feature_name = ?");
    $stmt->execute([$userId, $featureName]);
    $row = $stmt->fetch();
    if (!$row) return true; // default allow if not set
    return (bool) $row['is_allowed'];
}

// Helper: Flash messages
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Helper: Auth check for full PHP pages
function requireAuth($role = null) {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if ($role && $_SESSION['user']['role'] !== $role) {
        header('Location: ' . BASE_URL . '/login.php?error=unauthorized');
        exit;
    }
    return $_SESSION['user'];
}

// Format Rupiah
function formatRupiah($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
