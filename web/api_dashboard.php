<?php
header('Content-Type: application/json; charset=utf-8');

session_start();

// Only admins can access
if (empty($_SESSION['auth']) || ($_SESSION['auth']['role'] ?? '') !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$DB_HOST = '127.0.0.1';
$DB_NAME = 'gestion_resto';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHAR = 'utf8mb4';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHAR}",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]);
    exit;
}

try {
    // Simple stats
    $usersCount  = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $ordersCount = (int)$pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
    $alertsCount = (int)$pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();

    // Orders per day (last 7 days) using commandes table directly
    $ordersStmt = $pdo->query("
        SELECT DATE(date_commande) AS date,
               COUNT(*) AS nb_commandes,
               COALESCE(SUM(total), 0) AS total_revenu
        FROM commandes
        GROUP BY DATE(date_commande)
        ORDER BY DATE(date_commande) DESC
        LIMIT 7
    ");
    $ordersPerDay = $ordersStmt->fetchAll();

    // Popular dishes: top 5 plats by number of lignes
    $popularStmt = $pdo->query("
        SELECT p.id,
               p.nom,
               COALESCE(SUM(l.quantite), 0) AS quantite_vendue
        FROM plats p
        LEFT JOIN ligne_commandes l ON p.id = l.id_plat
        GROUP BY p.id, p.nom
        ORDER BY quantite_vendue DESC
        LIMIT 10
    ");
    $popular = $popularStmt->fetchAll();

    // Recent orders: last 10 commandes with client and serveur names
    $recentOrdersStmt = $pdo->query("
        SELECT co.id,
               co.date_commande,
               co.total,
               co.id_client,
               co.id_serveur
        FROM commandes co
        ORDER BY co.date_commande DESC
        LIMIT 10
    ");
    $recentOrdersRaw = $recentOrdersStmt->fetchAll();

    $recent = [];

    foreach ($recentOrdersRaw as $ro) {
        // Client name
        $client = 'Client inconnu';
        if (!empty($ro['id_client'])) {
            $c = $pdo->prepare("SELECT nom FROM clients WHERE id = :id LIMIT 1");
            $c->execute(['id' => $ro['id_client']]);
            $clientName = $c->fetchColumn();
            if ($clientName) {
                $client = $clientName;
            }
        }

        // Serveur name
        $waiter = '';
        if (!empty($ro['id_serveur'])) {
            $s = $pdo->prepare("SELECT nom FROM users WHERE id = :id LIMIT 1");
            $s->execute(['id' => $ro['id_serveur']]);
            $wName = $s->fetchColumn();
            if ($wName) {
                $waiter = $wName;
            }
        }

        // Lines for this order
        $lc = $pdo->prepare("
            SELECT l.quantite,
                   l.prix_unitaire,
                   p.nom AS produit
            FROM ligne_commandes l
            LEFT JOIN plats p ON p.id = l.id_plat
            WHERE l.id_commande = :cid
        ");
        $lc->execute(['cid' => $ro['id']]);
        $lines = $lc->fetchAll();

        $items = [];
        foreach ($lines as $ln) {
            $items[] = ($ln['produit'] ?? 'Plat').' x'.$ln['quantite'];
        }

        $recent[] = [
            'id'            => $ro['id'],
            'date_commande' => $ro['date_commande'],
            'client_nom'    => $client,
            'serveur_nom'   => $waiter,
            'items'         => $items,
            'total'         => $ro['total'],
        ];
    }

    echo json_encode([
        'stats' => [
            'users'         => $usersCount,
            'orders'        => $ordersCount,
            'alerts'        => $alertsCount,
            'server_status' => 'En ligne',
        ],
        'orders'        => $ordersPerDay,
        'popular'       => $popular,
        'recent_orders' => $recent,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
