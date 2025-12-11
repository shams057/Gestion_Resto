<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=gestion_resto;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? null;

if ($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['email']) || !isset($data['password'])) {
        echo json_encode(['status' => 'error', 'error' => 'Missing fields']);
        exit;
    }

    $email = trim($data['email']);
    $password = $data['password'];

    // 1) Clients: check hash in clients.password_hash
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $client = $stmt->fetch();

    if ($client && !empty($client['password_hash']) && password_verify($password, $client['password_hash'])) {
        $_SESSION['auth'] = [
            'id' => $client['id'],
            'role' => 'client',
            'name' => $client['nom'],
            'email' => $client['email']
        ];

        echo json_encode(['status' => 'ok', 'role' => 'client']);
        exit;
    }

    // 2) Admin/users: check hash in users.password_hash
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && !empty($admin['password_hash']) && password_verify($password, $admin['password_hash'])) {
        $_SESSION['auth'] = [
            'id' => $admin['id'],
            'role' => $admin['role'],
            'name' => $admin['nom'],
            'email' => $admin['email']
        ];

        echo json_encode(['status' => 'ok', 'role' => 'admin']);
        exit;
    }

    echo json_encode(['status' => 'error', 'error' => 'Invalid credentials']);
    exit;
}

if ($action === 'signup') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (
        !$data ||
        !isset($data['nom']) ||
        !isset($data['email']) ||
        !isset($data['password'])
    ) {
        echo json_encode([
            'success' => false,
            'error' => 'Nom, email et mot de passe requis.'
        ]);
        exit;
    }

    $nom = trim($data['nom']);
    $telephone = isset($data['telephone']) ? trim($data['telephone']) : '';
    $email = trim($data['email']);
    $password = $data['password'];

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error' => 'Cet email est déjà utilisé.'
        ]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO clients (nom, telephone, email, note, password_hash) VALUES (?, ?, ?, NULL, ?)'
    );
    $stmt->execute([$nom, $telephone, $email, $hash]);

    echo json_encode([
        'success' => true,
        'redirect' => 'buypage.html'
    ]);
    exit;
}

/**
 * New: create an order from buypage
 * Expects JSON: { cart: [{name, price}], total: number }
 */
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

    // Compute total from cart to trust server-side
    foreach ($cart as $item) {
        if (!isset($item['name']) || !isset($item['price'])) {
            continue;
        }
        $total += (float) $item['price'];
    }

    if ($total <= 0) {
        echo json_encode(['success' => false, 'error' => 'Montant total invalide.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $clientId = $_SESSION['auth']['id'];
        $serveurId = null; // no waiter for web order
        $ref = 'CMD-' . date('Ymd-His') . '-' . random_int(1000, 9999);

        // Insert into commandes
        $stmt = $pdo->prepare("
            INSERT INTO commandes (reference, id_client, id_serveur, total, statut, mode_paiement, remarque)
            VALUES (:ref, :cid, :sid, :total, 'en_attente', 'espece', NULL)
        ");
        $stmt->execute([
            'ref' => $ref,
            'cid' => $clientId,
            'sid' => $serveurId,
            'total' => $total
        ]);

        $commandeId = (int) $pdo->lastInsertId();

        // Insert each cart line into ligne_commandes
        foreach ($cart as $item) {
            if (!isset($item['name']) || !isset($item['price'])) {
                continue;
            }

            $name = $item['name'];
            $price = (float) $item['price'];
            $qty = isset($item['quantity']) ? (int) $item['quantity'] : 1;

            // Find plat id by name
            $p = $pdo->prepare("SELECT id FROM plats WHERE nom = :nom LIMIT 1");
            $p->execute(['nom' => $name]);
            $platId = $p->fetchColumn();

            if (!$platId) {
                // skip unknown items
                continue;
            }

            $lp = $pdo->prepare("
                INSERT INTO ligne_commandes (id_commande, id_plat, quantite, prix_unitaire, remarque)
                VALUES (:cid, :pid, :qty, :prix, NULL)
            ");
            $lp->execute([
                'cid' => $commandeId,
                'pid' => $platId,
                'qty' => $qty,
                'prix' => $price
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'commande_id' => $commandeId, 'reference' => $ref]);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la commande.']);
        exit;
    }
}

/**
 * DEFAULT: return plats for buypage with category name
 */
try {
    $stmt = $pdo->query(
        'SELECT p.id,
                p.nom,
                p.description,
                p.prix,
                p.id_categorie,
                c.nom AS categorie_nom,
                p.image_url,
                p.allergies
         FROM plats p
         LEFT JOIN categories c ON c.id = p.id_categorie
         WHERE p.disponible = 1'
    );
    $plats = $stmt->fetchAll();

    foreach ($plats as &$p) {
        if (!empty($p['allergies'])) {
            $p['allergies'] = array_map('trim', explode(',', $p['allergies']));
        } else {
            $p['allergies'] = [];
        }
        $p['category'] = $p['categorie_nom'] ?: 'Autre';
    }

    echo json_encode($plats);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load plats', 'details' => $e->getMessage()]);
    exit;
}
