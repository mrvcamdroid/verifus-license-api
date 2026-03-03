<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get license key from URL parameter
$key = isset($_GET['key']) ? trim($_GET['key']) : '';

if (empty($key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Database connection
$db_host = getenv('DB_HOST') ?: 'sql113.infinityfree.com';
$db_name = getenv('DB_NAME') ?: 'if0_41285365_vlive_license';
$db_user = getenv('DB_USER') ?: 'if0_41285365';
$db_pass = getenv('DB_PASS') ?: 'bMhlraME9WjBw';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check license
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license) {
    echo json_encode(['valid' => false]);
    exit();
}

// Check expiration
$expires = strtotime($license['expires_at']);
if (time() > $expires) {
    echo json_encode(['valid' => false]);
    exit();
}

// Calculate remaining time
$remaining_ms = ($expires - time()) * 1000;

// Generate signature
$secret = "MySup3rS3cr3tK3yF0rL1c3ns3App2024";
$signature_data = "true:" . $remaining_ms;
$signature = hash_hmac('sha256', $signature_data, $secret);

// Success response
echo json_encode([
    'valid' => true,
    'remaining_ms' => $remaining_ms,
    'sig' => $signature
]);
?>