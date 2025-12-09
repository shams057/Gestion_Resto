<?php
header('Content-Type: application/json');

$host = "localhost";
$db   = "gestion_resto";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=$db;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Updated query including allergies if column exists
    $stmt = $pdo->query("
        SELECT p.id, p.nom, p.description, p.prix, p.allergies, p.image_url,
               c.nom AS category
        FROM plats p
        LEFT JOIN categories c ON p.id_categorie = c.id
        WHERE p.disponible = 1
    ");

    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($plats as &$plat) {

        // Convert allergies string to array OR fallback to []
        if (isset($plat['allergies']) && !empty($plat['allergies'])) {
            // supports comma separated or JSON stored values
            if (str_starts_with($plat['allergies'], '[')) {
                $plat['allergies'] = json_decode($plat['allergies'], true);
            } else {
                $plat['allergies'] = array_map('trim', explode(',', $plat['allergies']));
            }
        } else {
            $plat['allergies'] = [];
        }

        // Default placeholder image if missing
        if (empty($plat['image_url'])) {
            $plat['image_url'] = 'https://via.placeholder.com/300x200';
        }
    }

    echo json_encode($plats, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}