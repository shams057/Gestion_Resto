<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$host    = '127.0.0.1';
$db      = 'gestion_resto';  // adjust if phpMyAdmin says otherwise
$user    = 'root';          // adjust
$pass    = '';              // adjust
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error'   => 'DB connection error',
        'details' => $e->getMessage(),
    ]);
    exit;
}

// =======================
// 3) HELPER
// =======================
function json_response($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? null;

// =======================
// 4) SIGNUP (FROM signup.php)
// =======================
//
// signup.php sends:
// fetch('api.php?action=signup', {
//   method: 'POST',
//   headers: { 'Content-Type': 'application/json' },
//   body: JSON.stringify({ nom, telephone, email, password })
// })
if ($action === 'signup') {
    $input = json_decode(file_get_contents('php://input'), true);

    $nom       = trim($input['nom'] ?? '');
    $telephone = trim($input['telephone'] ?? '');
    $email     = trim($input['email'] ?? '');
    $password  = $input['password'] ?? '';

    if ($nom === '' || $email === '' || $password === '') {
        json_response(['success' => false, 'error' => 'Nom, email et mot de passe requis.'], 400);
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'error' => 'Cet email est déjà utilisé.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('
        INSERT INTO clients (nom, telephone, email, password_hash, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$nom, $telephone, $email, $hash]);

    $clientId = (int)$pdo->lastInsertId();

    $_SESSION['auth'] = [
        'id'    => $clientId,
        'email' => $email,
        'nom'   => $nom,
        'role'  => 'client',
        'type'  => 'client',
    ];
    $_SESSION['client_id'] = $clientId;

    json_response([
        'success'  => true,
        'redirect' => 'buypage.html',
    ]);
}

// =======================
// 5) LOGIN (USED BY login.php)
// =======================
//
// login.php sends:
// fetch('api.php?action=login', { method: 'POST', headers: {...}, body: JSON.stringify({ email, password }) })
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($email === '' || $password === '') {
        json_response(['status' => 'error', 'message' => 'Email ou mot de passe manquant'], 400);
    }

    // Try client login first
    $stmt = $pdo->prepare('SELECT id, nom, email, password_hash FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $client = $stmt->fetch();

    if ($client && password_verify($password, $client['password_hash'])) {
        $_SESSION['auth'] = [
            'id'    => $client['id'],
            'email' => $client['email'],
            'nom'   => $client['nom'],
            'role'  => 'client',
            'type'  => 'client',
        ];
        $_SESSION['client_id'] = $client['id'];

        json_response([
            'status' => 'ok',
            'role'   => 'client',
        ]);
    }

    // Then try staff/users login (for dashboard)
    $stmt = $pdo->prepare('SELECT id, nom, email, role, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userRow = $stmt->fetch();

    if ($userRow && password_verify($password, $userRow['password_hash'])) {
        $_SESSION['auth'] = [
            'id'    => $userRow['id'],
            'email' => $userRow['email'],
            'nom'   => $userRow['nom'],
            'role'  => $userRow['role'],
            'type'  => 'user',
        ];

        json_response([
            'status' => 'ok',
            'role'   => 'admin', // your JS checks admin vs client
        ]);
    }

    json_response(['status' => 'error', 'message' => 'Incorrect login'], 401);
}

// =======================
// 6) CREATE ORDER (USED BY cart.php)
// =======================
//
// cart.php sends:
// fetch('api.php?action=createorder', { method: 'POST', body: JSON.stringify({ cart, total }) })
if ($action === 'create_order') {
    // Require a logged-in client
    if (empty($_SESSION['auth']) || ($_SESSION['auth']['role'] ?? '') !== 'client') {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['cart']) || !is_array($data['cart']) || empty($data['cart'])) {
        echo json_encode(['success' => false, 'error' => 'Panier vide ou invalide.']);
        exit;
    }

    $cart = $data['cart'];
    $total = 0.0;

    foreach ($cart as $item) {
        if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            continue;
        }
        $total += (float)$item['price'] * (int)$item['quantity'];
    }

    if ($total <= 0) {
        echo json_encode(['success' => false, 'error' => 'Montant total invalide.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $clientId = $_SESSION['auth']['id'];
        $serveurId = null;
        $ref = 'CMD-' . date('Ymd-His') . '-' . random_int(1000, 9999);

        $stmt = $pdo->prepare("
            INSERT INTO commandes (reference, id_client, id_serveur, total, statut, mode_paiement, remarque)
            VALUES (:ref, :cid, :sid, :total, 'en_attente', 'espece', NULL)
        ");
        $stmt->execute([
            'ref'   => $ref,
            'cid'   => $clientId,
            'sid'   => $serveurId,
            'total' => $total
        ]);

        $commandeId = (int) $pdo->lastInsertId();

        foreach ($cart as $item) {
            if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
                continue;
            }

            $name = $item['name'];
            $price = (float) $item['price'];
            $qty = (int) $item['quantity'];

            $p = $pdo->prepare("SELECT id FROM plats WHERE nom = :nom LIMIT 1");
            $p->execute(['nom' => $name]);
            $platId = $p->fetchColumn();

            if (!$platId) {
                continue;
            }

            $lp = $pdo->prepare("
                INSERT INTO ligne_commandes (id_commande, id_plat, quantite, prix_unitaire, remarque)
                VALUES (:cid, :pid, :qty, :prix, NULL)
            ");
            $lp->execute([
                'cid'  => $commandeId,
                'pid'  => $platId,
                'qty'  => $qty,
                'prix' => $price
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success'   => true,
            'commande_id' => $commandeId,
            'reference' => $ref
        ]);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Erreur lors de la création de la commande.'
        ]);
        exit;
    }
}

// =======================
// 7) GET CART (USED BY buypage.js)
// =======================
//
// buypage.js calls:
// fetch('api.php?action=getcart')
if ($action === 'getcart') {
    if (empty($_SESSION['client_id'])) {
        json_response(['items' => []]);
    }

    $clientId = (int)$_SESSION['client_id'];

    // Last en_attente order for this client
    $stmt = $pdo->prepare('
        SELECT id
        FROM commandes
        WHERE id_client = ? AND statut = "en_attente"
        ORDER BY date_commande DESC
        LIMIT 1
    ');
    $stmt->execute([$clientId]);
    $order = $stmt->fetch();

    if (!$order) {
        json_response(['items' => []]);
    }

    $orderId = (int)$order['id'];

    $stmt = $pdo->prepare('
        SELECT lc.id,
               lc.quantite,
               lc.prix_unitaire,
               p.nom AS name
        FROM ligne_commandes lc
        JOIN plats p ON p.id = lc.id_plat
        WHERE lc.id_commande = ?
    ');
    $stmt->execute([$orderId]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'id'       => (int)$r['id'],
            'name'     => $r['name'],
            'price'    => (float)$r['prix_unitaire'],
            'quantity' => (int)$r['quantite'],
        ];
    }

    json_response(['items' => $items]);
}

// =======================
// 8) DEFAULT: LIST PRODUCTS (USED BY buypage.js)
// =======================
//
// buypage.js does: fetch('api.php') with no action
if ($action === null) {
    $stmt = $pdo->query('
        SELECT
            p.id,
            p.nom,
            p.description,
            p.prix,
            c.nom AS category,
            p.image_url,
            p.allergies
        FROM plats p
        LEFT JOIN categories c ON c.id = p.id_categorie
        WHERE p.disponible = 1
    ');

    $rows = $stmt->fetchAll();

    json_response($rows);
}

// =======================
// 9) UNKNOWN ACTION
// =======================
json_response(['error' => 'Unknown action'], 400);
