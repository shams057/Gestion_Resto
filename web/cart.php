<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Panier - FoodMarket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styleBP.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="img/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="img/favicon.png/favicon.svg" />
    <link rel="shortcut icon" href="img/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Gresto" />
    <link rel="manifest" href="img/site.webmanifest" />
</head>

<body>
    <nav class="navbar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="logo">Gresto</div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px; color: var(--text-primary);">
                <?php if (!empty($_SESSION['auth'])): ?>
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['auth']['nom']); ?></span>
                    <a href="logout" class="btn-logout">Déconnexion</a>
                <?php else: ?>
                    <a href="login"
                        style="color: var(--text-primary); text-decoration: none; font-weight: 500;">Connexion</a>
                <?php endif; ?>
                <a href="shop" class="btn-shop">Retour menu</a>
            </div>
        </div>
    </nav>

    <button id="theme-toggle" class="floating-theme-toggle" title="Toggle Theme"></button>

    <div class="container" style="padding: 20px; flex-direction: column; align-items: center;">
        <h2 style="text-align: center;">Confirmation de commande</h2>
        <p style="text-align: center; color: var(--text-secondary);">Veuillez vérifier votre panier avant de confirmer.
        </p>

        <div class="card cart-summary-card">
            <h3>Votre panier</h3>
            <ul id="cart-summary-list"></ul>

            <div class="cart-summary-footer">
                <p class="total-line">
                    <strong>Total: <span id="cart-summary-total">0.00 TND</span></strong>
                </p>
                <button id="confirm-order-btn" class="btn-confirm">
                    Confirmer la commande
                </button>
            </div>
        </div>
        <div id="order-message" style="display:none; margin-top:20px; width: 100%; max-width: 600px;"></div>
    </div>

    <script>
        // 1. Theme Toggle Logic
        function initThemeToggle() {
            const themeToggle = document.getElementById("theme-toggle");
            if (!themeToggle) return;
            
            const body = document.body;
            const savedTheme = localStorage.getItem("theme");
            
            if (savedTheme === "dark") {
                body.classList.add("dark-mode");
            }
            
            themeToggle.addEventListener("click", () => {
                const isDark = body.classList.toggle("dark-mode");
                localStorage.setItem("theme", isDark ? "dark" : "light");
            });
        }

        // 2. Global Variables
        const messageBox = document.getElementById('order-message');
        const listEl = document.getElementById('cart-summary-list');
        const totalEl = document.getElementById('cart-summary-total');
        const confirmBtn = document.getElementById('confirm-order-btn');
        let cart = [];

        // 3. Initialize Cart (Load from Session + Server)
        async function initCart() {
            // A. Load local data first
            try {
                const stored = sessionStorage.getItem('cart');
                if (stored) cart = JSON.parse(stored);
            } catch (e) { console.error(e); }

            // B. Check if user is logged in (PHP injection)
            const isLoggedIn = <?php echo !empty($_SESSION['auth']) ? 'true' : 'false'; ?>;

            if (isLoggedIn) {
                try {
                    // Fetch saved cart from DB
                    const res = await fetch('api.php?action=get_cart');
                    const data = await res.json();
                    
                    if (data.cart && data.cart.length > 0) {
                        // Merge logic: If local is empty, use server.
                        // If user has local items, we keep them (or you could merge them).
                        if (cart.length === 0) {
                            cart = data.cart;
                            sessionStorage.setItem('cart', JSON.stringify(cart));
                        }
                    }
                } catch (err) {
                    console.error("Error fetching server cart:", err);
                }
            }
            
            renderCartSummary();
        }

        // 4. Save Cart
        function saveCartToSession() {
            // Save Local
            sessionStorage.setItem('cart', JSON.stringify(cart));
            
            // Save Server (if logged in)
            const isLoggedIn = <?php echo !empty($_SESSION['auth']) ? 'true' : 'false'; ?>;
            if(isLoggedIn) {
                fetch('api.php?action=save_cart', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cart })
                }).catch(e => console.error("Save error", e));
            }
        }

        // 5. Render Cart HTML
        function renderCartSummary() {
            listEl.innerHTML = '';
            listEl.className = 'cart-list-styled'; 

            let total = 0;

            if (cart.length === 0) {
                listEl.innerHTML = '<li style="text-align:center; padding: 20px;">Votre panier est vide.</li>';
                totalEl.textContent = '0.00 TND';
                return;
            }

            cart.forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'cart-item-row';
                
                const price = parseFloat(item.price);
                const qty = parseInt(item.quantity);
                const lineTotal = price * qty;
                total += lineTotal;

                li.innerHTML = `
                    <span class="item-name">${item.name} <small>x${qty}</small></span>
                    <div class="item-actions">
                        <span class="item-price">${lineTotal.toFixed(2)} TND</span>
                        <div class="qty-controls">
                            <button type="button" class="qty-btn" data-index="${index}" data-op="dec">-</button>
                            <button type="button" class="qty-btn" data-index="${index}" data-op="inc">+</button>
                        </div>
                    </div>
                `;
                listEl.appendChild(li);
            });

            totalEl.textContent = total.toFixed(2) + ' TND';

            listEl.querySelectorAll('.qty-btn').forEach(btn => {
                btn.addEventListener('click', onQtyButtonClick);
            });
        }

        // 6. Handle Quantity Buttons
        function onQtyButtonClick(e) {
            const index = parseInt(e.currentTarget.getAttribute('data-index'), 10);
            const op = e.currentTarget.getAttribute('data-op');

            if (Number.isNaN(index) || !cart[index]) return;

            if (op === 'inc') {
                cart[index].quantity += 1;
            } else if (op === 'dec') {
                cart[index].quantity -= 1;
                if (cart[index].quantity <= 0) {
                    cart.splice(index, 1);
                }
            }
            
            saveCartToSession(); 
            renderCartSummary();
        }

        // 7. CONFIRM ORDER (This was missing!)
        async function confirmOrder() {
            if (cart.length === 0) {
                alert('Votre panier est vide.');
                return;
            }

            // Check Login via PHP Session first
            const isLoggedIn = <?php echo !empty($_SESSION['auth']) ? 'true' : 'false'; ?>;
            
            if (!isLoggedIn) {
                // If not logged in, send them to login page
                const backUrl = encodeURIComponent('cart');
                window.location.href = `login?redirect=${backUrl}`;
                return;
            }

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            try {
                const res = await fetch('api.php?action=create_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ cart, total })
                });

                const text = await res.text();
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON', text);
                    alert('Erreur serveur: Réponse invalide.');
                    return;
                }

                if (res.status === 401) {
                    alert('Session expirée. Veuillez vous reconnecter.');
                    window.location.href = 'login';
                    return;
                }

                if (!res.ok || !data || data.success === false) {
                    alert((data && data.error) || 'Erreur lors de la création de la commande.');
                    return;
                }

                // Success Logic
                cart = [];
                sessionStorage.removeItem('cart');
                // Also clear server cart
                fetch('api.php?action=save_cart', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ cart: [] })
                });

                messageBox.innerHTML = `
                    <div class="success-box">
                        <h3>Commande confirmée !</h3>
                        <p>Référence: <strong>${data.reference}</strong></p>
                        <button id="back-to-shop-btn" class="btn-confirm">
                            Retourner à la boutique
                        </button>
                    </div>
                `;
                messageBox.style.display = 'block';
                document.querySelector('.cart-summary-card').style.display = 'none';
                
                setTimeout(() => {
                    const backBtn = document.getElementById('back-to-shop-btn');
                    if(backBtn) {
                        backBtn.addEventListener('click', () => {
                            window.location.href = 'shop';
                        });
                    }
                }, 100);

            } catch (e) {
                console.error('Order error', e);
                alert('Erreur réseau lors de la commande.');
            }
        }

        // 8. Main Event Listener
        document.addEventListener('DOMContentLoaded', () => {
            initThemeToggle();
            initCart();
            if(confirmBtn) {
                confirmBtn.addEventListener('click', confirmOrder);
            }
        });
    </script>
</body>

</html>