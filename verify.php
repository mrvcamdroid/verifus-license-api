<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['key']) || !isset($data['android_id']) || !isset($data['device_model'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

$key = $data['key'];
$android_id = $data['android_id'];
$device_model = $data['device_model'];

// =============================================
// Render PostgreSQL Database Connection
// =============================================
$db_host = 'dpg-d6ja3t7tskes738hmt40-a';
$db_name = 'licenses_nd5s';
$db_user = 'licenses_nd5s_user';
$db_pass = 'zqhUfY3NJXYJWGzZSpdKpXvnKkhoHxtH';
$db_port = '5432';

try {
    $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name;", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// =============================================
// Create table if it doesn't exist
// =============================================
$createTableSQL = "
CREATE TABLE IF NOT EXISTS licenses (
    id SERIAL PRIMARY KEY,
    license_key VARCHAR(64) UNIQUE NOT NULL,
    device_id VARCHAR(64),
    device_model VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    duration_days INT NOT NULL
);
";

try {
    $pdo->exec($createTableSQL);
    error_log("Table 'licenses' is ready");
} catch (PDOException $e) {
    error_log("Table creation notice: " . $e->getMessage());
}

// =============================================
// Insert test key '1234' if it doesn't exist
// =============================================
$checkKey = $pdo->prepare("SELECT * FROM licenses WHERE license_key = '1234'");
$checkKey->execute();
$keyExists = $checkKey->fetch();

if (!$keyExists) {
    $insertKey = $pdo->prepare("INSERT INTO licenses (license_key, expires_at, duration_days) VALUES ('1234', NOW() + INTERVAL '30 days', 30)");
    $insertKey->execute();
    error_log("Test key '1234' has been inserted");
}

// =============================================
// Check if the requested key exists
// =============================================
$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license) {
    echo json_encode(['error' => 'Invalid key']);
    exit();
}

// Check if expired
if (strtotime($license['expires_at']) < time()) {
    echo json_encode(['error' => 'Key expired']);
    exit();
}

// Check if already used on another device
if ($license['device_id'] && $license['device_id'] !== $android_id) {
    echo json_encode(['error' => 'Key already used on another device']);
    exit();
}

// First time activation - save device info
if (!$license['device_id']) {
    $update = $pdo->prepare("UPDATE licenses SET device_id = ?, device_model = ?, activated_at = NOW() WHERE id = ?");
    $update->execute([$android_id, $device_model, $license['id']]);
}

// Calculate remaining time
$remaining_ms = (strtotime($license['expires_at']) - time()) * 1000;
$secret = "MySup3rS3cr3tK3yF0rL1c3ns3App2024";
$signature = hash_hmac('sha256', "true:" . $remaining_ms, $secret);

echo json_encode([
    'valid' => true,
    'remaining_ms' => $remaining_ms,
    'sig' => $signature
]);
?>