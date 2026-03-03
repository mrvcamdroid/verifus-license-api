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

$db_host = 'sql113.infinityfree.com';
$db_name = 'if0_41285365_vlive_license';
$db_user = 'if0_41285365';
$db_pass = 'bMhlraME9WjBw';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license) {
    echo json_encode(['error' => 'Invalid key']);
    exit();
}

if (strtotime($license['expires_at']) < time()) {
    echo json_encode(['error' => 'Key expired']);
    exit();
}

if ($license['device_id'] && $license['device_id'] !== $android_id) {
    echo json_encode(['error' => 'Key already used on another device']);
    exit();
}

if (!$license['device_id']) {
    $update = $pdo->prepare("UPDATE licenses SET device_id = ?, device_model = ?, activated_at = NOW() WHERE id = ?");
    $update->execute([$android_id, $device_model, $license['id']]);
}

$remaining_ms = (strtotime($license['expires_at']) - time()) * 1000;
$secret = "MySup3rS3cr3tK3yF0rL1c3ns3App2024";
$signature = hash_hmac('sha256', "true:" . $remaining_ms, $secret);

echo json_encode([
    'valid' => true,
    'remaining_ms' => $remaining_ms,
    'sig' => $signature
]);
?>