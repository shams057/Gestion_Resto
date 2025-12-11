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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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

    $email    = trim($data['email']);
    $password = $data['password'];

    // 1) Clients: check hash in clients.password_hash
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $client = $stmt->fetch();

    if ($client && !empty($client['password_hash']) && password_verify($password, $client['password_hash'])) {
        $_SESSION['auth'] = [
            'id'    => $client['id'],
            'role'  => 'client',
            'name'  => $client['nom'],
            'email' => $client['email'],
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
            'id'    => $admin['id'],
            'role'  => $admin['role'],   // 'admin', 'serveur', ...
            'name'  => $admin['nom'],
            'email' => $admin['email'],
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
            'error'   => 'Nom, email et mot de passe requis.'
        ]);
        exit;
    }

    $nom       = trim($data['nom']);
    $telephone = isset($data['telephone']) ? trim($data['telephone']) : '';
    $email     = trim($data['email']);
    $password  = $data['password'];

    $stmt = $pdo->prepare('SELECT id FROM clients WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'error'   => 'Cet email est déjà utilisé.'
        ]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO clients (nom, telephone, email, note, password_hash) VALUES (?, ?, ?, NULL, ?)'
    );
    $stmt->execute([$nom, $telephone, $email, $hash]);

    echo json_encode([
        'success'  => true,
        'redirect' => 'buypage.html'
    ]);
    exit;
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
        // human‑readable category for buypage.js
        $p['category'] = $p['categorie_nom'] ?: 'Autre';
    }

    echo json_encode($plats);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load plats', 'details' => $e->getMessage()]);
    exit;
}