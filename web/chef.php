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

// --- API REQUEST HANDLING (AJAX) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Security Check: Only allow Admin, Chef, or Server
    if (empty($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'cuisinier', 'serveur'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // 1. GET ORDERS
    if ($_GET['action'] === 'get_orders') {
        // Fetch orders 'en_attente' (waiting) or 'preparation' (cooking)
        // Group items into a single string like "2x Burger||1x Fries"
        $sql = "
            SELECT c.id, c.reference, c.statut, c.created_at, c.remarque as order_note,
                   COALESCE(cl.nom, 'Client Comptoir') as client_name,
                   GROUP_CONCAT(CONCAT(lc.quantite, 'x ', p.nom) SEPARATOR '||') as items
            FROM commandes c
            LEFT JOIN clients cl ON c.id_client = cl.id
            JOIN ligne_commandes lc ON c.id = lc.id_commande
            JOIN plats p ON lc.id_plat = p.id
            WHERE c.statut IN ('en_attente', 'preparation')
            GROUP BY c.id
            ORDER BY 
                CASE WHEN c.statut = 'preparation' THEN 1 ELSE 2 END ASC, 
                c.created_at ASC
        ";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    // 2. UPDATE STATUS
    if ($_GET['action'] === 'update_status') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $status = $input['status'] ?? null;

        if ($id && $status) {
            $stmt = $pdo->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid input']);
        }
        exit;
    }
    exit;
}

// ============================================================
// 2. PAGE ACCESS CONTROL
// ============================================================
// Only allow Admin or Chef to view the kitchen screen
if (empty($_SESSION['auth']) || !in_array($_SESSION['auth']['role'], ['admin', 'cuisinier'])) {
    header('Location: login.php'); 
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
        :root {
            --bg-page: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --border-color: #e2e8f0;
        }
        
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-page); margin: 0; }
        
        .dark-mode {
            --bg-page: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --border-color: #334155;
        }

        /* Layout */
        .dashboard-container { display: flex; min-height: 100vh; }
        .content-area { flex: 1; padding: 20px; }
        
        /* Kitchen Grid */
        .kitchen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding-top: 20px;
        }

        /* Ticket Card */
        .ticket-card {
            background: var(--card-bg);
            color: var(--text-main);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s ease-out;
        }

        .ticket-header {
            padding: 15px;
            background: rgba(0,0,0,0.03);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            color: #64748b;
        }

        .ticket-body { padding: 15px; flex-grow: 1; }
        
        .client-name { font-size: 1.1em; font-weight: 700; color: #3b82f6; display: block; margin-bottom: 8px;}

        .item-list { list-style: none; padding: 0; margin: 0; }
        .item-list li {
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-color);
            display: flex;
            align-items: center;
        }
        .item-qty {
            font-weight: 800;
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 10px;
        }

        .ticket-footer { padding: 15px; background: rgba(0,0,0,0.02); }

        /* Buttons */
        .btn-kitchen {
            width: 100%; padding: 12px; border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; color: white; transition: 0.2s;
        }
        .btn-start { background-color: #3b82f6; }
        .btn-start:hover { background-color: #2563eb; }
        .btn-finish { background-color: #10b981; }
        .btn-finish:hover { background-color: #059669; }

        /* Animations */
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Navbar specific fix */
        .navbar-modern { display: flex; justify-content: space-between; padding: 1rem; background: #fff; border-bottom: 1px solid #e2e8f0;}
        .dark-mode .navbar-modern { background: #1e293b; border-color: #334155; color: white; }
    </style>
</head>

<body>
    <nav class="navbar navbar-modern">
        <div class="navbar-left">
            <span style="font-size: 1.5rem; margin-right: 10px;">👨‍🍳</span>
            <span style="font-weight: bold; font-size: 1.2rem;">KITCHEN DISPLAY</span>
        </div>
        <div class="navbar-right">
            <div id="connection-status" style="display:inline-block; padding: 5px 10px; border-radius:15px; background:#dcfce7; color:#166534; font-size:0.85rem; margin-right:15px;">
                🟢 Live
            </div>
            <button id="theme-toggle" style="background:none; border:none; cursor:pointer; font-size:1.2rem;">🌙</button>
            <strong style="margin: 0 15px;"><?php echo htmlspecialchars($_SESSION['auth']['nom'] ?? 'Chef'); ?></strong>
            <a href="logout.php" style="color: #ef4444; text-decoration: none; font-weight: 600;">Logout</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <nav class="sidebar-modern" style="width: 250px; padding: 20px; background: var(--card-bg); border-right: 1px solid var(--border-color);">
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;"><a href="dashboard.php" style="text-decoration: none; color: inherit;">📊 Dashboard</a></li>
                <li style="margin-bottom: 10px;"><a href="chef.php" style="text-decoration: none; color: #3b82f6; font-weight:bold;">🔥 Ecran Cuisine</a></li>
                <li style="margin-bottom: 10px;"><a href="shop.php" style="text-decoration: none; color: inherit;">🍔 Menu Client</a></li>
            </ul>
        </nav>

        <main class="content-area">
            <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <div>
                    <h1 style="margin:0; color: var(--text-main);">Commandes en cours</h1>
                    <p style="margin:5px 0 0 0; color: #64748b;">Vue temps réel des préparations</p>
                </div>
                <button onclick="fetchOrders()" style="padding:10px 20px; background:#3b82f6; color:white; border:none; border-radius:6px; cursor:pointer;">
                    🔄 Actualiser
                </button>
            </header>

            <div id="loading" style="text-align:center; padding:50px; color:#94a3b8;">Chargement...</div>
            <div id="empty-state" style="display:none; text-align:center; padding:50px;">
                <h2 style="color:#10b981;">Tout est calme ! 👨‍🍳</h2>
                <p style="color: var(--text-main);">Aucune commande en attente.</p>
            </div>

            <div id="kitchen-grid" class="kitchen-grid"></div>
        </main>
    </div>

    <script>
        // --- Theme Logic ---
        const themeBtn = document.getElementById('theme-toggle');
        const html = document.documentElement;
        
        if(localStorage.getItem('theme') === 'dark') {
            html.classList.add('dark-mode');
            themeBtn.textContent = '☀️';
        }
        
        themeBtn.addEventListener('click', () => {
            html.classList.toggle('dark-mode');
            const isDark = html.classList.contains('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            themeBtn.textContent = isDark ? '☀️' : '🌙';
        });

        // --- Kitchen Logic ---

        function getTimeString(dateStr) {
            return new Date(dateStr).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        async function fetchOrders() {
            const grid = document.getElementById('kitchen-grid');
            const loading = document.getElementById('loading');
            const empty = document.getElementById('empty-state');
            const statusBadge = document.getElementById('connection-status');

            try {
                const res = await fetch('chef.php?action=get_orders');
                if(!res.ok) throw new Error("API Error");
                const orders = await res.json();

                loading.style.display = 'none';
                statusBadge.innerHTML = "🟢 Live";
                statusBadge.style.background = "#dcfce7";
                statusBadge.style.color = "#166534";
                
                if (orders.length === 0) {
                    empty.style.display = 'block';
                    grid.innerHTML = '';
                    return;
                }

                empty.style.display = 'none';
                grid.innerHTML = orders.map(createTicketHTML).join('');

            } catch (err) {
                console.error(err);
                statusBadge.innerHTML = "🔴 Offline";
                statusBadge.style.background = "#fee2e2";
                statusBadge.style.color = "#991b1b";
            }
        }

        function createTicketHTML(order) {
            // Parse items string: "2x Pizza||1x Soda"
            const items = order.items.split('||').map(item => {
                const [qty, ...nameParts] = item.split('x ');
                const name = nameParts.join('x '); // Rejoin in case name had 'x '
                return `<li><span class="item-qty">${qty}</span> ${name}</li>`;
            }).join('');

            const isPrep = order.statut === 'preparation';
            
            // Visual logic based on status
            const borderStyle = isPrep 
                ? 'border-left: 5px solid #f59e0b;' // Orange for Prep
                : 'border-left: 5px solid #ef4444;'; // Red for Waiting
            
            const btnHtml = isPrep
                ? `<button onclick="updateStatus(${order.id}, 'livree')" class="btn-kitchen btn-finish">✅ Terminer la commande</button>`
                : `<button onclick="updateStatus(${order.id}, 'preparation')" class="btn-kitchen btn-start">🔥 Lancer la préparation</button>`;

            const statusLabel = isPrep 
                ? `<span style="background:#fef3c7; color:#92400e; padding:4px 8px; border-radius:4px; font-size:0.8em; text-transform:uppercase;">En Cuisine</span>`
                : `<span style="background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:4px; font-size:0.8em; text-transform:uppercase;">En Attente</span>`;

            return `
                <div class="ticket-card" style="${borderStyle}">
                    <div class="ticket-header">
                        <span>🕒 ${getTimeString(order.created_at)}</span>
                        <span>#${order.reference.split('-').pop()}</span>
                    </div>
                    <div class="ticket-body">
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                            <span class="client-name">${order.client_name}</span>
                            ${statusLabel}
                        </div>
                        <ul class="item-list">${items}</ul>
                        ${order.order_note ? `<div style="margin-top:10px; background:#fffbeb; color:#d97706; padding:5px; font-size:0.9em; border-radius:4px;">⚠️ ${order.order_note}</div>` : ''}
                    </div>
                    <div class="ticket-footer">
                        ${btnHtml}
                    </div>
                </div>
            `;
        }

        async function updateStatus(id, newStatus) {
            try {
                const res = await fetch('chef.php?action=update_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, status: newStatus })
                });
                const data = await res.json();
                if(data.success) {
                    fetchOrders();
                } else {
                    alert("Erreur: " + (data.error || "Inconnue"));
                }
            } catch(e) {
                alert("Erreur réseau");
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', () => {
            fetchOrders();
            setInterval(fetchOrders, 10000); // Poll every 10s
        });
    </script>
</body>
</html>