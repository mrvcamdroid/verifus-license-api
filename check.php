<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = $_GET['key'] ?? '';

if (!$key) {
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Fixed: Changed hostname from infinityfree.com to byetcluster.com
$db_host = 'sql113.byetcluster.com';
$db_name = 'if0_41285365_vlive_license';
$db_user = 'if0_41285365';
$db_pass = 'bMhlraME9WjBw';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
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