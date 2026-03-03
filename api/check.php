<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = $_GET['key'] ?? '';

if (!$key) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

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

$stmt = $pdo->prepare("SELECT * FROM licenses WHERE license_key = ?");
$stmt->execute([$key]);
$license = $stmt->fetch();

if (!$license || strtotime($license['expires_at']) < time()) {
    echo json_encode(['valid' => false]);
    exit();
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