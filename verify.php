<?php
// =============================================
// verify.php - License Activation Endpoint
// =============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Allow preflight requests (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =============================================
// Configuration
// =============================================
$secret = "MySup3rS3cr3tK3yF0rL1c3ns3App2024"; // 32 chars

// Database connection (use environment variables on Render)
$db_host = getenv('DB_HOST') ?: 'sql113.infinityfree.com';
$db_name = getenv('DB_NAME') ?: 'if0_41285365_vlive_license';
$db_user = getenv('DB_USER') ?: 'if0_41285365';
$db_pass = getenv('DB_PASS') ?: 'bMhlraME9WjBw';

// =============================================
// Read JSON input
// =============================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['key']) || !isset($data['android_id']) || !isset($data['device_model'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$key = trim($data['key']);
$android_id = trim($data['android_id']);
$device_model = trim($data['device_model']);

// =============================================
// Database connection
// =============================================
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// =============================================
// Check license key
// =============================================
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ? AND expires_at > NOW()");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid or expired key']);
    exit();
}

// =============================================
// Check device binding
// =============================================
if (!empty($license['device_id']) && $license['device_id'] !== $android_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Key already used on another device']);
    exit();
}

// =============================================
// Register device (first activation)
// =============================================
if (empty($license['device_id'])) {
    $update = $pdo->prepare("UPDATE licenses SET device_id = ?, device_model = ?, activated_at = NOW() WHERE id = ?");
    $update->execute([$android_id, $device_model, $license['id']]);
}

// =============================================
// Calculate remaining time
// =============================================
$expires = strtotime($license['expires_at']);
$now = time();
$remaining_ms = max(0, ($expires - $now) * 1000);

// =============================================
// Generate signature (HMAC-SHA256)
// =============================================
$valid = true;
$signature_data = "true:" . $remaining_ms;
$signature = hash_hmac('sha256', $signature_data, $secret);

// =============================================
// Return success response
// =============================================
echo json_encode([
    'valid' => true,
    'remaining_ms' => $remaining_ms,
    'sig' => $signature
]);
?>