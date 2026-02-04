<?php
// ============================================================
// 1. PHP BACKEND & API LOGIC
// ============================================================
session_start();

// Database Configuration
$host = '127.0.0.1';
$db   = 'gestion_resto';
$user = 'root';
$pass = '';
$dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    die("DB Error: " . $e->getMessage());
}

// Handle API Requests (AJAX)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Security Check for API
    if (empty($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'cuisinier', 'serveur'])) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // --- GET ORDERS ---
    if ($_GET['action'] === 'get_orders') {
        // Fetch orders that are 'en_attente' or 'preparation'
        $stmt = $pdo->query("
            SELECT c.id, c.reference, c.statut, c.created_at, c.remarque as order_note,
                   cl.nom as client_name,
                   GROUP_CONCAT(CONCAT(lc.quantite, 'x ', p.nom) SEPARATOR '||') as items
            FROM commandes c
            LEFT JOIN clients cl ON c.id_client = cl.id
            JOIN ligne_commandes lc ON c.id = lc.id_commande
            JOIN plats p ON lc.id_plat = p.id
            WHERE c.statut IN ('en_attente', 'preparation')
            GROUP BY c.id
            ORDER BY 
                CASE WHEN c.statut = 'preparation' THEN 1 ELSE 2 END, -- Show In Prep first
                c.created_at ASC
        ");
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // --- UPDATE STATUS ---
    if ($_GET['action'] === 'update_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $status = $input['status'] ?? null;

        if ($id && $status) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }
    exit;
}

// ============================================================
// 2. PAGE ACCESS CONTROL
// ============================================================
if (empty($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'cuisinier'])) {
    header('Location: login.php'); // Redirect if not chef/admin
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuisine - Gresto</title>
    <link rel="stylesheet" href="/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* ============================================================
           3. INTERNAL CSS (SPECIFIC TO CHEF VIEW)
           ============================================================ */
        
        /* Kitchen Grid Layout */
        .kitchen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        /* Ticket Card Design */
        .ticket-card {
            background: var(--card-bg, #ffffff);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        /* Dark Mode overrides handled via CSS vars usually, 
           but adding explicit fallback here if style.css variables aren't global */
        .dark-mode .ticket-card {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }

        /* Ticket Header */
        .ticket-header {
            padding: 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dark-mode .ticket-header {
            background: #0f172a;
            border-color: #334155;
        }

        .ticket-time {
            font-weight: 700;
            font-size: 1.1em;
            color: #64748b;
        }
        .ticket-id {
            font-size: 0.85em;
            color: #94a3b8;
        }

        /* Ticket Body */
        .ticket-body {
            padding: 15px;
            flex-grow: 1;
        }
        
        .client-name {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            color: #3b82f6;
        }

        .item-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .item-list li {
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 1.05em;
            display: flex;
            justify-content: space-between;
        }
        .dark-mode .item-list li {
            border-color: #334155;
        }
        .item-qty {
            font-weight: 800;
            color: #ef4444; /* Red for visibility */
            margin-right: 10px;
        }

        /* Ticket Footer (Actions) */
        .ticket-footer {
            padding: 15px;
            display: grid;
            gap: 10px;
            background: var(--card-bg, #fff);
        }
        .dark-mode .ticket-footer {
            background: #1e293b;
        }

        /* Status Colors */
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-attente { background: #fee2e2; color: #991b1b; } /* Redish */
        .status-prep { background: #fef3c7; color: #92400e; } /* Yellowish */
        
        /* Buttons */
        .btn-kitchen {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-kitchen:hover { opacity: 0.9; }
        
        .btn-start { background-color: #3b82f6; color: white; }
        .btn-finish { background-color: #10b981; color: white; }

        /* Animation for new items */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ticket-card { animation: slideIn 0.3s ease-out; }

        /* Timer Pulse */
        .pulse { animation: pulse-animation 2s infinite; }
        @keyframes pulse-animation {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-modern">
        <div class="navbar-left">
            <button id="burger-menu-btn" class="burger-menu-btn" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
            <div class="navbar-brand">👨‍🍳 Cuisine</div>
        </div>
        <div class="navbar-right">
            <div id="connection-status" class="status-badge status-prep" style="margin-right:10px;">🟢 Live</div>
            <button id="theme-toggle-nav" class="theme-toggle-nav">🌙</button>
            <span class="navbar-user"><?php echo htmlspecialchars($_SESSION['auth']['nom'] ?? 'Chef'); ?></span>
            <a class="btn-logout" href="logout.php">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <nav class="sidebar-modern">
            <ul class="nav-links">
                <?php if($_SESSION['auth']['role'] === 'admin'): ?>
                <li><a href="dashboard" class="nav-link">Dashboard</a></li>
                <?php endif; ?>
                <li><a href="chef" class="nav-link active">Ecran Cuisine</a></li>
                <li><a href="shop" class="nav-link">Menu</a></li>
                <li><a href="logout.php" class="nav-link">Déconnexion</a></li>
            </ul>
        </nav>

        <main class="content-area">
            <header class="dashboard-header">
                <div>
                    <h1>Commandes en cours</h1>
                    <p class="subtitle">Vue temps réel des préparations</p>
                </div>
                <div class="header-controls">
                    <button onclick="fetchOrders()" class="btn-cam" style="background:#3b82f6; color:white; border:none;">
                        🔄 Actualiser
                    </button>
                </div>
            </header>

            <div id="loading" style="text-align:center; padding:50px; color:#94a3b8;">
                Chargement des tickets...
            </div>

            <div id="empty-state" style="display:none; text-align:center; padding:50px;">
                <h2 style="color:#10b981;">Tout est calme ! 👨‍🍳</h2>
                <p>Aucune commande en attente.</p>
            </div>

            <div id="kitchen-grid" class="kitchen-grid">
                </div>
        </main>
    </div>

    <script>
        // --- UI Logic (Sidebar/Theme) ---
        const burgerBtn = document.getElementById("burger-menu-btn");
        const sidebar = document.querySelector(".sidebar-modern");
        const themeToggle = document.getElementById("theme-toggle-nav");
        const html = document.documentElement;

        // Theme Init
        if(localStorage.getItem("theme") === "dark") {
            html.classList.add("dark-mode");
            themeToggle.textContent = "☀️";
        }
        themeToggle.addEventListener("click", () => {
            html.classList.toggle("dark-mode");
            const isDark = html.classList.contains("dark-mode");
            localStorage.setItem("theme", isDark ? "dark" : "light");
            themeToggle.textContent = isDark ? "☀️" : "🌙";
        });

        // Sidebar Mobile
        burgerBtn.addEventListener("click", () => {
            sidebar.classList.toggle("sidebar-open");
            burgerBtn.classList.toggle("active");
        });

        // --- CHEF LOGIC ---

        // Helper: Time ago
        function getTimeString(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        async function fetchOrders() {
            const grid = document.getElementById('kitchen-grid');
            const loading = document.getElementById('loading');
            const empty = document.getElementById('empty-state');
            const statusIndicator = document.getElementById('connection-status');

            try {
                const res = await fetch('chef.php?action=get_orders');
                if(!res.ok) throw new Error("Network");
                const orders = await res.json();

                // UI Updates
                loading.style.display = 'none';
                statusIndicator.innerText = "🟢 Live";
                
                if (orders.length === 0) {
                    empty.style.display = 'block';
                    grid.innerHTML = '';
                    return;
                }

                empty.style.display = 'none';

                // Render Tickets
                // Note: In a real React/Vue app we would diff the DOM. 
                // Here we rebuild to keep it simple, but we could optimize to not flicker.
                grid.innerHTML = orders.map(order => createTicketHTML(order)).join('');

            } catch (err) {
                console.error(err);
                statusIndicator.innerText = "🔴 Offline";
            }
        }

        function createTicketHTML(order) {
            // Parse items (Stored as "2x Pizza||1x Soda")
            const items = order.items.split('||').map(item => {
                // simple parsing logic
                const parts = item.split('x '); // "2", "Pizza"
                const qty = parts[0];
                const name = parts[1];
                return `<li><span class="item-qty">${qty}</span> ${name}</li>`;
            }).join('');

            const isPrep = order.statut === 'preparation';
            
            // Badge Logic
            const badgeHtml = isPrep 
                ? `<span class="status-badge status-prep">🔥 En Cuisine</span>` 
                : `<span class="status-badge status-attente">⏳ En Attente</span>`;
            
            // Button Logic
            const btnHtml = isPrep
                ? `<button onclick="updateStatus(${order.id}, 'livree')" class="btn-kitchen btn-finish">✅ Terminer la commande</button>`
                : `<button onclick="updateStatus(${order.id}, 'preparation')" class="btn-kitchen btn-start">🔥 Lancer la préparation</button>`;

            const borderStyle = isPrep ? 'border-left: 5px solid #f59e0b;' : 'border-left: 5px solid #ef4444;';

            return `
                <div class="ticket-card" style="${borderStyle}">
                    <div class="ticket-header">
                        <span class="ticket-time">${getTimeString(order.created_at)}</span>
                        <span class="ticket-id">#${order.reference.split('-').pop()}</span>
                    </div>
                    <div class="ticket-body">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span class="client-name">${order.client_name || 'Client Inconnu'}</span>
                            ${badgeHtml}
                        </div>
                        <ul class="item-list">
                            ${items}
                        </ul>
                        ${order.order_note ? `<div style="margin-top:10px; font-size:0.9em; color:#d97706; background:#fffbeb; padding:5px;">⚠️ Note: ${order.order_note}</div>` : ''}
                    </div>
                    <div class="ticket-footer">
                        ${btnHtml}
                    </div>
                </div>
            `;
        }

        async function updateStatus(id, newStatus) {
            if(!confirm("Changer le statut de la commande ?")) return;

            try {
                const res = await fetch('chef.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, status: newStatus })
                });
                const data = await res.json();
                if(data.success) {
                    fetchOrders(); // Refresh immediately
                } else {
                    alert("Erreur lors de la mise à jour");
                }
            } catch(e) {
                alert("Erreur réseau");
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            fetchOrders();
            // Auto refresh every 10 seconds
            setInterval(fetchOrders, 10000);
        });
    </script>
</body>
</html>