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
            <div class="logo">FoodMarket</div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button id="theme-toggle" class="theme-toggle">🌙</button>
            <div style="display: flex; align-items: center; gap: 8px; color: var(--text-primary);">
                <?php if (!empty($_SESSION['auth'])): ?>
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($_SESSION['auth']['nom']); ?></span>
                    <a href="logout"
                        style="color: white; padding: 8px 16px; background: var(--primary); border-radius: 6px; text-decoration: none; font-weight: 600; transition: all 0.2s ease;">Déconnexion</a>
                <?php else: ?>
                    <a href="login"
                        style="color: var(--text-primary); text-decoration: none; font-weight: 500;">Connexion</a>
                <?php endif; ?>
                <a href="shop"
                    style="color: var(--text-primary); text-decoration: none; padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 6px;">Retour
                    boutique</a>
            </div>
        </div>
    </nav>


    <div class="container" style="padding:20px; flex-direction:column;">
        <h2>Confirmation de commande</h2>
        <p>Veuillez vérifier votre panier avant de confirmer.</p>

        <div class="card" style="max-width:600px;margin-top:20px;">
            <h3>Votre panier</h3>
            <ul id="cart-summary-list"></ul>
            <p>
                <strong>Total:
                    <span id="cart-summary-total">0.00 TND</span>
                </strong>
            </p>
            <button id="confirm-order-btn" style="margin-top:10px;">
                Confirmer la commande
            </button>
        </div>
        <div id="order-message" style="display:none; margin-top:20px;"></div>
    </div>
    </div>

    <script>
        function initThemeToggle() {
            const themeToggle = document.getElementById("theme-toggle");
            const html = document.documentElement;
            const savedTheme = localStorage.getItem("theme") || "light";
            if (savedTheme === "dark") {
                html.classList.add("dark-mode");
                themeToggle.textContent = "☀️";
            }
            themeToggle.addEventListener("click", () => {
                html.classList.toggle("dark-mode");
                const isDark = html.classList.contains("dark-mode");
                localStorage.setItem("theme", isDark ? "dark" : "light");
                themeToggle.textContent = isDark ? "☀️" : "🌙";
            });
        }

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

            // NEW: sync DB cart as well
            fetch('api.php?action=save_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ cart })
            }).catch(() => { });
        }

        function renderCartSummary() {
            listEl.innerHTML = '';
            listEl.style.listStyle = 'none';
            listEl.style.paddingLeft = '0';

            let total = 0;

            cart.forEach((item, index) => {
                const li = document.createElement('li');
                li.style.display = 'flex';
                li.style.alignItems = 'center';
                li.style.justifyContent = 'space-between';
                li.style.margin = '4px 0';

                const lineTotal = item.price * item.quantity;
                total += lineTotal;

                li.innerHTML = `
            <span>${item.name} x${item.quantity} - ${lineTotal.toFixed(2)} TND</span>
            <span>
                <button type="button" class="qty-btn" data-index="${index}" data-op="dec">-</button>
                <button type="button" class="qty-btn" data-index="${index}" data-op="inc">+</button>
            </span>
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

            // Front check: if not logged in on front, go to signup
            if (!sessionStorage.getItem('auth')) {
                const backUrl = encodeURIComponent('cart');
                window.location.href = `signup?redirect=${backUrl}`;
                return;
            }

            const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            try {
                const res = await fetch('api?action=create_order', {
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
                    console.error('Invalid JSON from create_order. Raw:', text);
                    alert('Erreur serveur.');
                    return;
                }

                if (res.status === 401) {
                    alert('Login to be able to order');
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

                messageBox.innerHTML = `
  <div style="
    background:#e8f5e9;
    border:1px solid #2ecc71;
    padding:16px;
    border-radius:8px;
    max-width:600px;
  ">
    <h3 style="margin-top:0;">Commande confirmée !</h3>
    <p>Référence: <strong>${data.reference}</strong></p>
    <button id="back-to-shop-btn" style="margin-top:10px;">
      Retourner à la boutique
    </button>
  </div>
`;
                messageBox.style.display = 'block';

                // keep the summary visually empty
                renderCartSummary();

                // attach click handler
                const backBtn = document.getElementById('back-to-shop-btn');
                if (backBtn) {
                    backBtn.addEventListener('click', () => {
                        window.location.href = 'shop';
                    });
                }
            } catch (e) {
                console.error('Order error', e);
                alert('Erreur réseau lors de la commande.');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            initThemeToggle(); // Add this line
            loadCartFromSession();
            renderCartSummary();
            confirmBtn.addEventListener('click', confirmOrder);
        });

    </script>
</body>

</html>