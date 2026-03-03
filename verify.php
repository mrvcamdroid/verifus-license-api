<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['key']) || !isset($data['android_id']) || !isset($data['device_model'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$key = trim($data['key']);
$android_id = trim($data['android_id']);
$device_model = trim($data['device_model']);

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

// Check license key
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid key']);
    exit();
}

// Check expiration
$expires = strtotime($license['expires_at']);
if (time() > $expires) {
    http_response_code(410);
    echo json_encode(['error' => 'Key expired']);
    exit();
}

// Check if already used on another device
if ($license['device_id'] && $license['device_id'] !== $android_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Key already used on another device']);
    exit();
}

// First time activation - save device info
if (!$license['device_id']) {
    $update = $pdo->prepare("UPDATE licenses SET device_id = ?, device_model = ?, activated_at = NOW() WHERE id = ?");
    $update->execute([$android_id, $device_model, $license['id']]);
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