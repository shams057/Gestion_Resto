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
                    <a href="login" style="color: var(--text-primary); text-decoration: none; font-weight: 500;">Connexion</a>
                <?php endif; ?>
                <a href="shop" class="btn-shop">Retour boutique</a>
            </div>
        </div>
    </nav>

    <button id="theme-toggle" class="floating-theme-toggle" title="Toggle Theme"></button>

    <div class="container" style="padding: 20px; flex-direction: column; align-items: center;">
        <h2 style="text-align: center;">Confirmation de commande</h2>
        <p style="text-align: center; color: var(--text-secondary);">Veuillez vérifier votre panier avant de confirmer.</p>

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
        // Floating Theme Toggle Logic
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

        // Cart functionality
        const messageBox = document.getElementById('order-message');
        const listEl = document.getElementById('cart-summary-list');
        const totalEl = document.getElementById('cart-summary-total');
        const confirmBtn = document.getElementById('confirm-order-btn');

        let cart = [];

        function loadCartFromSession() {
            try {
                const stored = sessionStorage.getItem('cart');
                cart = stored ? JSON.parse(stored) : [];
            } catch (e) {
                console.error('Erreur lecture cart sessionStorage', e);
                cart = [];
            }
        }

        function saveCartToSession() {
            try {
                sessionStorage.setItem('cart', JSON.stringify(cart));
            } catch (e) {
                console.error('Erreur écriture cart sessionStorage', e);
            }
            fetch('api.php?action=save_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ cart })
            }).catch(() => {});
        }

        function renderCartSummary() {
            listEl.innerHTML = '';
            listEl.className = 'cart-list-styled'; // Use CSS class instead of inline styles

            let total = 0;

            if (cart.length === 0) {
                listEl.innerHTML = '<li style="text-align:center; padding: 20px;">Votre panier est vide.</li>';
                totalEl.textContent = '0.00 TND';
                return;
            }

            cart.forEach((item, index) => {
                const li = document.createElement('li');
                li.className = 'cart-item-row';

                const lineTotal = item.price * item.quantity;
                total += lineTotal;

                li.innerHTML = `
                    <span class="item-name">${item.name} <small>x${item.quantity}</small></span>
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

        async function confirmOrder() {
            if (cart.length === 0) {
                alert('Votre panier est vide.');
                return;
            }

            if (!sessionStorage.getItem('auth')) {
                const backUrl = encodeURIComponent('cart');
                window.location.href = `signup?redirect=${backUrl}`;
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
                    alert('Erreur serveur.');
                    return;
                }

                if (res.status === 401) {
                    alert('Veuillez vous connecter pour commander');
                    const backUrl = encodeURIComponent('cart');
                    window.location.href = `login?redirect=${backUrl}`;
                    return;
                }

                if (!res.ok || !data || data.success === false) {
                    alert((data && data.error) || 'Erreur lors de la création de la commande.');
                    return;
                }

                cart = [];
                sessionStorage.removeItem('cart');

                // Success Message
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
                // Hide cart card after success
                document.querySelector('.cart-summary-card').style.display = 'none';
                
                // Re-bind back button
                setTimeout(() => {
                    document.getElementById('back-to-shop-btn').addEventListener('click', () => {
                        window.location.href = 'shop';
                    });
                }, 100);

            } catch (e) {
                console.error('Order error', e);
                alert('Erreur réseau lors de la commande.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initThemeToggle();
            loadCartFromSession();
            renderCartSummary();
            confirmBtn.addEventListener('click', confirmOrder);
        });
    </script>
</body>
</html>