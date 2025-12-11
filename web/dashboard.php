<?php
session_start();
// if not logged in or not a user (admin/serveur), redirect to login
if (empty($_SESSION['auth']) || ($_SESSION['auth']['type'] ?? '') !== 'user') {
    header('Location: login.php');
    exit;
}
// Optionally, restrict to admin only:
if ($_SESSION['auth']['role'] !== 'admin') {
    header('Location: buypage.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - Gestion Resto</title>
    <link rel="stylesheet" href="style.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-dark bg-dark px-3">
        <a class="navbar-brand fw-bold" href="#">Tableau de Bord</a>
        <div>
            <span class="text-white me-3">Bonjour,
                <?php echo htmlspecialchars($_SESSION['auth']['nom'] ?? ''); ?></span>
            <a class="btn btn-outline-light" href="logout.php">Déconnexion</a>
        </div>
    </nav>

    <!-- Rest of your dashboard HTML (use your existing dashboard layout) -->
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 dashboard-sidebar d-flex flex-column pt-4">
                <a href="#">Dashboard</a>
                <a href="#">Gestion des utilisateurs</a>
                <a href="#">Paramètres</a>
                <a href="#">Logs système</a>
                <a href="#">Statistiques</a>
                <a href="logout.php">Déconnexion</a>
            </nav>

            <main class="col-md-10 content-area">
                <h3 class="mb-4 fw-bold">Vue générale</h3>
                <!-- stat cards and tables... you can reuse your previous HTML here -->
                <div id="dashboard-root">
                    <!-- Optionally show loading and let dashboard.js fetch api_dashboard.php -->
                </div>
            </main>
        </div>
    </div>

    <script src="dashboard.js"></script>
</body>

</html>