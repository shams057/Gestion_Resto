<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Session timeout check (same as api.php)
$timeout = 2 * 60 * 60;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['timed_out'] = true;
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!empty($_SESSION['timed_out']) || empty($_SESSION['auth']) || ($_SESSION['auth']['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Database connection (same as api.php)
$host = '127.0.0.1';
$db = 'gestion_resto';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error']);
    exit;
}

$action = $_GET['action'] ?? null;
$range = $_GET['range'] ?? 'month';

$where = '1=1';
$params = [];
if ($range === 'day') {
    $where = 'DATE(created_at) = CURDATE()';
} elseif ($range === 'month') {
    $where = 'YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())';
} elseif ($range === 'year') {
    $where = 'YEAR(created_at) = YEAR(CURDATE())';
}

if ($action === 'getStats') {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
        FROM commandes WHERE $where
    ");
    $stmt->execute($params);
    $row = $stmt->fetch();

    $alertsStmt = $pdo->prepare("
        SELECT COUNT(*) FROM reviews WHERE rating <= 3 AND $where
    ");
    $alertsStmt->execute($params);
    $alerts = $alertsStmt->fetchColumn();

    echo json_encode([
        'revenue' => (float)$row['revenue'],
        'orders' => (int)$row['orders'],
        'alerts' => (int)$alerts
    ]);
    exit;
}

if ($action === 'getLowStarAlerts') {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at,
               c.nom AS client_name, p.nom AS plat_name
        FROM reviews r
        JOIN clients c ON c.id = r.id_client
        JOIN plats p ON p.id = r.id_plat
        WHERE r.rating <= 3
        ORDER BY r.created_at DESC LIMIT 50
    ");
    $stmt->execute();
    echo json_encode(['alerts' => $stmt->fetchAll()]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
?>
