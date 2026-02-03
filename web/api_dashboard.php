<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// 1. Session & Auth Check
$timeout = 2 * 60 * 60; // 2 hours
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

// 2. Database Connection
$host = '127.0.0.1';
$db = 'gestion_resto';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error']);
    exit;
}

$action = $_GET['action'] ?? null;
$range = $_GET['range'] ?? 'month';

// Helper for date range
function getRangeCondition($range) {
    if ($range === 'day') return 'DATE(created_at) = CURDATE()';
    if ($range === 'year') return 'YEAR(created_at) = YEAR(CURDATE())';
    // default to month
    return 'YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())';
}

// --- ACTION: Get General Stats (Revenue, Orders, Alerts) ---
if ($action === 'getStats') {
    $where = getRangeCondition($range);
    
    // Revenue & Orders count
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
        FROM commandes WHERE $where
    ");
    $stmt->execute();
    $stats = $stmt->fetch();

    // Alerts (Reviews <= 3 stars)
    $alertsStmt = $pdo->prepare("
        SELECT COUNT(*) FROM reviews WHERE rating <= 3 AND $where
    ");
    $alertsStmt->execute();
    $alerts = $alertsStmt->fetchColumn();

    echo json_encode([
        'revenue' => (float)$stats['revenue'],
        'orders' => (int)$stats['orders'],
        'alerts' => (int)$alerts
    ]);
    exit;
}

// --- ACTION: Get Recent Orders (Top 10) ---
if ($action === 'getRecentOrders') {
    $sql = "
        SELECT c.id, c.created_at, c.total,
               cl.nom AS client_name,
               u.nom AS serveur_nom,
               GROUP_CONCAT(p.nom SEPARATOR ', ') AS items
        FROM commandes c
        LEFT JOIN clients cl ON c.id_client = cl.id
        LEFT JOIN users u ON c.id_serveur = u.id
        LEFT JOIN ligne_commandes lc ON c.id = lc.id_commande
        LEFT JOIN plats p ON lc.id_plat = p.id
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ";
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- ACTION: Get Popular Dishes (Top 5) ---
if ($action === 'getPopularDishes') {
    $sql = "
        SELECT p.nom, SUM(lc.quantite) AS sold
        FROM ligne_commandes lc
        JOIN plats p ON lc.id_plat = p.id
        GROUP BY p.id
        ORDER BY sold DESC
        LIMIT 5
    ";
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- ACTION: Get Last 7 Days Stats ---
if ($action === 'getLast7Days') {
    $sql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date, 
               COUNT(*) as count, 
               SUM(total) as revenue
        FROM commandes
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ";
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- ACTION: Get Low Star Alerts (Reviews <= 3) ---
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
    echo json_encode(['alerts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
?>