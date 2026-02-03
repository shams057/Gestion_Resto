<?php
// web/auth_callback.php

// 1. Enable Error Reporting (Debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Start Session Safely
if (session_status() === PHP_SESSION_NONE) session_start();

// 3. Database Connection
$host = '127.0.0.1';
$db   = 'gestion_resto';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 4. Google Logic
$provider = $_GET['provider'] ?? '';
$code     = $_GET['code'] ?? '';
$state    = $_GET['state'] ?? '';

// Security Check
if (!$code || !isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    die("Security Error: Invalid State. Please try logging in again.");
}
unset($_SESSION['oauth_state']);

// Credentials (PASTE YOURS HERE AGAIN)
$client_id     = '227014846953-53cg98n8v1u0v86se7kkgp16a52nd2ob.apps.googleusercontent.com'; 
$client_secret = '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';
$redirect_uri  = "http://gresto.com/web/callback?provider=google";

// Exchange Code for Token
$ch = curl_init("https://oauth2.googleapis.com/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'code'          => $code,
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri'  => $redirect_uri,
    'grant_type'    => 'authorization_code'
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!isset($response['access_token'])) {
    die("Login Failed: " . ($response['error_description'] ?? 'No access token received from Google.'));
}

// Get User Info
$ch = curl_init("https://www.googleapis.com/oauth2/v2/userinfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
$googleUser = json_decode(curl_exec($ch), true);
curl_close($ch);

$email = $googleUser['email'] ?? null;
$name  = $googleUser['name'] ?? 'Google User';

if (!$email) die("Error: Google did not provide an email.");

// 5. Database Sync (FIXED SECTION)

// Check if user exists (Removed 'role' from SELECT)
$stmt = $pdo->prepare("SELECT id, nom, email FROM clients WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // User exists
    $userId = $user['id'];
    $role   = 'client'; // Hardcoded because table doesn't have it
} else {
    // New User -> Create them
    // Fixed: Added 'telephone' (empty) and removed 'role' from INSERT
    $dummyHash = password_hash(bin2hex(random_bytes(10)), PASSWORD_BCRYPT);
    $telephone = ""; 
    
    $stmt = $pdo->prepare("INSERT INTO clients (nom, email, telephone, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$name, $email, $telephone, $dummyHash]);
    $userId = $pdo->lastInsertId();
    $role   = 'client';
}

// 6. Set Session
$_SESSION['auth'] = [
    'id'    => $userId,
    'nom'   => $name,
    'email' => $email,
    'role'  => $role,
    'type'  => 'client'
];

// Redirect
header("Location: /shop");
exit;