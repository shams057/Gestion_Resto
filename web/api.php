<?php
// 1. PHPMailer Namespace Imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 2. Load PHPMailer files
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

// --- CONFIGURATION ---
// Change to '1 HOUR' when going live
$emailDelay = '1 SECOND'; 

// Session timeout (2h)
$timeout = 2 * 60 * 60;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['timed_out'] = true;
}
$_SESSION['LAST_ACTIVITY'] = time();

$host = '127.0.0.1';
$db = 'gestion_resto';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection error']);
    exit;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (!empty($_SESSION['timed_out'])) {
    json_response(['success' => false, 'error' => 'Session expired', 'code' => 'SESSION_EXPIRED'], 401);
}

$action = $_GET['action'] ?? null;

// SIGNUP
if ($action === 'signup') {
    $input = json_decode(file_get_contents('php://input'), true);
    $nom = trim($input['nom'] ?? '');
    $telephone = trim($input['telephone'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$nom || !$email || !$password) {
        json_response(['success' => false, 'error' => 'Nom, email et mot de passe requis.'], 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'error' => 'Cet email est déjà utilisé.'], 400);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO clients (nom, telephone, email, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$nom, $telephone, $email, $hash]);
    json_response(['success' => true, 'redirect' => 'login']);
}

// LOGIN
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        json_response(['status' => 'error', 'message' => 'Email ou mot de passe manquant'], 400);
    }

    $stmt = $pdo->prepare("SELECT id, nom, email, password_hash FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    if ($client && password_verify($password, $client['password_hash'])) {
        $_SESSION['auth'] = ['id' => $client['id'], 'email' => $client['email'], 'nom' => $client['nom'], 'role' => 'client', 'type' => 'client'];
        $_SESSION['client_id'] = $client['id'];
        json_response(['status' => 'ok', 'role' => 'client']);
    }

    $stmt = $pdo->prepare("SELECT id, nom, email, role, password_hash FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $userRow = $stmt->fetch();
    if ($userRow && password_verify($password, $userRow['password_hash'])) {
        $_SESSION['auth'] = ['id' => $userRow['id'], 'email' => $userRow['email'], 'nom' => $userRow['nom'], 'role' => $userRow['role'], 'type' => 'user'];
        json_response(['status' => 'ok', 'role' => 'admin']);
    }

    json_response(['status' => 'error', 'message' => 'Identifiants incorrects'], 401);
}

// SAVE CART
if ($action === 'save_cart') {
    if (empty($_SESSION['auth']) || ($_SESSION['auth']['role'] ?? '') !== 'client') {
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $cart = $data['cart'] ?? [];
    if (!is_array($cart)) {
        json_response(['success' => false, 'error' => 'Panier invalide'], 400);
    }
    $clientId = (int)$_SESSION['auth']['id'];

    try {
        $pdo->beginTransaction();
        $del = $pdo->prepare("DELETE FROM carts WHERE id_client = ?");
        $del->execute([$clientId]);
        $ins = $pdo->prepare("INSERT INTO carts (id_client, id_plat, quantite) VALUES (?, ?, ?)");
        foreach ($cart as $item) {
            if (!isset($item['name']) || !isset($item['quantity'])) continue;
            $p = $pdo->prepare("SELECT id FROM plats WHERE nom = ? LIMIT 1");
            $p->execute([$item['name']]);
            $platId = $p->fetchColumn();
            if (!$platId) continue;
            $qty = (int)$item['quantity'];
            if ($qty > 0) {
                $ins->execute([$clientId, $platId, $qty]);
            }
        }
        $pdo->commit();
        json_response(['success' => true]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Erreur lors de la sauvegarde du panier'], 500);
    }
}

// GET CART
if ($action === 'get_cart') {
    if (empty($_SESSION['auth']) || ($_SESSION['auth']['role'] ?? '') !== 'client') {
        json_response(['cart' => []], 200);
    }
    $clientId = (int)$_SESSION['auth']['id'];
    $stmt = $pdo->prepare("SELECT c.quantite, p.nom, p.prix FROM carts c JOIN plats p ON p.id = c.id_plat WHERE c.id_client = ?");
    $stmt->execute([$clientId]);
    $rows = $stmt->fetchAll();
    $cart = array_map(function($row) {
        return [
            'name' => $row['nom'],
            'price' => (float)$row['prix'],
            'quantity' => (int)$row['quantite']
        ];
    }, $rows);
    json_response(['cart' => $cart]);
}

// CREATE ORDER
// CREATE ORDER
if ($action === 'create_order') {
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
        $clientName = $_SESSION['auth']['nom'] ?? 'Client';
        $clientEmail = $_SESSION['auth']['email'] ?? null; // <--- GET DYNAMIC EMAIL
        $serveurId = null;
        $ref = 'CMD-' . date('Ymd-His') . '-' . random_int(1000, 9999);

        // Insert Command
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

        $commandeId = (int)$pdo->lastInsertId();

        // Insert Order Lines and Build Email List
        $itemsHtmlList = ""; 
        foreach ($cart as $item) {
            if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) continue;

            $name = $item['name'];
            $price = (float)$item['price'];
            $qty = (int)$item['quantity'];

            $itemsHtmlList .= "<li><strong>$name</strong> x $qty (" . number_format($price * $qty, 2) . " TND)</li>";

            $p = $pdo->prepare("SELECT id FROM plats WHERE nom = :nom LIMIT 1");
            $p->execute(['nom' => $name]);
            $platId = $p->fetchColumn();

            if (!$platId) continue;

            $lp = $pdo->prepare("
                INSERT INTO ligne_commandes (id_commande, id_plat, quantite, prix_unitaire, remarque)
                VALUES (:cid, :pid, :qty, :prix, NULL)
            ");
            $lp->execute(['cid' => $commandeId, 'pid' => $platId, 'qty' => $qty, 'prix' => $price]);
        }

        // Clear Cart
        $clear = $pdo->prepare('DELETE FROM carts WHERE id_client = :cid');
        $clear->execute([':cid' => $clientId]);

        // Schedule Review Reminder
        try {
            if ($clientEmail) {
                $reminderStmt = $pdo->prepare("
                    INSERT INTO review_reminders (id_commande, email, scheduled_at)
                    VALUES (:cid, :email, DATE_ADD(NOW(), INTERVAL $emailDelay))
                ");
                $reminderStmt->execute([':cid' => $commandeId, ':email' => $clientEmail]);
            }
        } catch (Throwable $e) {}

        $pdo->commit();

        // ============================================================
        // SEND EMAIL TO CUSTOMER (DYNAMIC)
        // ============================================================
        $emailDebug = "Skipped (No email)";

        if ($clientEmail) { // Only try if we have an address
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'shamsnasfi7@gmail.com'; 
                $mail->Password   = 'ptxe grgp kklk yszs'; // <--- PASTE PASSWORD HERE
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Sender
                $mail->setFrom('shamsnasfi7@gmail.com', 'Gresto - Confirmation');
                
                // Recipient: The Dynamic User
                $mail->addAddress($clientEmail, $clientName); 

                // Optional: Receive a copy yourself (Admin)
                // $mail->addBCC('mohamedshamseddine.nasfi@sesame.com.tn'); 

                $mail->isHTML(true);
                $mail->Subject = "Confirmation de votre commande : $ref";
                
                $mail->Body = "
                    <div style='font-family: Arial, sans-serif; color: #333;'>
                        <h2 style='color: #d35400;'>Merci pour votre commande, $clientName !</h2>
                        <p>Nous avons bien reçu votre commande.</p>
                        <p><strong>Référence :</strong> $ref</p>
                        <p><strong>Total à payer :</strong> <span style='font-size: 1.2em; font-weight: bold;'>$total TND</span></p>
                        <hr>
                        <h3>Récapitulatif :</h3>
                        <ul>$itemsHtmlList</ul>
                        <br>
                        <p>À bientôt,<br>L'équipe Gresto</p>
                    </div>
                ";

                $mail->send();
                $emailDebug = "Success: Sent to $clientEmail";

            } catch (Exception $e) {
                $emailDebug = "Error: " . $mail->ErrorInfo;
                error_log("Customer Email Error: " . $mail->ErrorInfo);
            }
        }

        echo json_encode([
            'success' => true,
            'commande_id' => $commandeId,
            'reference' => $ref,
            'email_status' => $emailDebug
        ]);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la commande.']);
        exit;
    }
}

// SEND REVIEW REMINDERS
if ($action === 'send_review_reminders') {
    $token = $_GET['token'] ?? '';
    if ($token !== 'simple123') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT rr.id, rr.id_commande, rr.email, c.reference, c.total
        FROM review_reminders rr
        JOIN commandes c ON c.id = rr.id_commande
        WHERE rr.sent = 0 AND rr.scheduled_at <= NOW()
        LIMIT 50
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll();

    if (empty($reminders)) {
        echo json_encode(['success' => true, 'processed' => 0, 'message' => 'No emails to send']);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'shamsnasfi7@gmail.com'; 
        $mail->Password   = 'ptxe grgp kklk yszs'; // <--- PASTE PASSWORD HERE
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        $mail->setFrom('shamsnasfi7@gmail.com', 'Gresto Team');
        
        $sentCount = 0;

        foreach ($reminders as $rem) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($rem['email']);
                $mail->isHTML(true);
                $mail->Subject = "Notez votre commande " . $rem['reference'];
                
                $reviewLink = "http://localhost:8000/review.html?order=" . $rem['id_commande'];
                
                $mail->Body = "
                    <h2>Bonjour,</h2>
                    <p>Merci pour votre commande <strong>{$rem['reference']}</strong> (Total: {$rem['total']} TND).</p>
                    <p>Nous aimerions avoir votre avis :</p>
                    <p><a href='{$reviewLink}'>Cliquez ici pour noter votre repas</a></p>
                    <br>
                    <p>Cordialement,<br>Gresto Team</p>
                ";
                
                $mail->send();

                $upd = $pdo->prepare("UPDATE review_reminders SET sent = 1 WHERE id = :id");
                $upd->execute([':id' => $rem['id']]);
                $sentCount++;

            } catch (Exception $e) {
                error_log("Failed to send to {$rem['email']}: " . $mail->ErrorInfo);
            }
        }

        echo json_encode(['success' => true, 'processed' => $sentCount]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Mailer Error: {$mail->ErrorInfo}"]);
        exit;
    }
}

// DEFAULT PRODUCTS LIST
if ($action === null) {
    $stmt = $pdo->query("
        SELECT p.id, p.nom, p.description, p.prix, p.id_categorie,
               c.nom AS category, 
               COALESCE(p.image_url, 'no-image.png') AS image_url,
               COALESCE(p.allergies, '') AS allergies
        FROM plats p 
        LEFT JOIN categories c ON c.id = p.id_categorie 
        WHERE p.disponible = 1
    ");
    $rows = $stmt->fetchAll();
    
    $products = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'name' => $row['nom'],
            'desc' => $row['description'] ?? '',
            'price' => (float)$row['prix'],
            'id_categorie' => (int)$row['id_categorie'],
            'category' => $row['category'] ?? '',
            'img' => $row['image_url'],
            'allergy' => $row['allergies'] ? explode(',', trim($row['allergies'])) : []
        ];
    }, $rows);
    
    json_response($products);
}
?>