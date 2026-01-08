<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panier - FoodMarket</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/styleBP.css">
</head>
<body>
<nav class="navbar">
    <div class="logo">FoodMarket</div>
    <div class="button">
        <a href="shop" style="color:white;margin-right:10px;text-decoration:none;">Retour à la boutique</a>
        <?php if (!empty($_SESSION['auth'])): ?>
            <span><?php echo htmlspecialchars($_SESSION['auth']['email']); ?></span>
            <a href="logout" style="color:white;margin-left:10px;text-decoration:none;">Déconnexion</a>
        <?php else: ?>
            <a href="login" style="color:white;margin-left:10px;text-decoration:none;">Connexion</a>
        <?php endif; ?>
    </div>
</nav>

<div class="container" style="padding:20px; flex-direction:column;">
    <h2>Confirmation de commande</h2>
    <p>Veuillez vérifier votre panier avant de confirmer.</p>

    <div class="card" style="max-width:600px;margin-top:20px;">
        <h3>Votre panier</h3>
        <ul id="cart-summary-list"></ul>
        <p><strong>Total: <span id="cart-summary-total">0.00 TND</span></strong></p>

        <button id="confirm-order-btn" style="margin-top:10px;">Confirmer la commande</button>
    </div>
</div>

<script>
const listEl = document.getElementById('cart-summary-list');
const totalEl = document.getElementById('cart-summary-total');
const confirmBtn = document.getElementById('confirm-order-btn');

let cart = [];
try {
    const stored = sessionStorage.getItem('cart');
    if (stored) {
        cart = JSON.parse(stored);
    }
} catch (e) {
    console.error('Erreur lecture cart sessionStorage', e);
}

function renderCartSummary() {
    listEl.innerHTML = '';
    let total = 0;

    cart.forEach(item => {
        const li = document.createElement('li');
        const lineTotal = item.price * item.quantity;
        li.textContent = `${item.name} x${item.quantity} - ${lineTotal.toFixed(2)} TND`;
        listEl.appendChild(li);
        total += lineTotal;
    });

    totalEl.textContent = total.toFixed(2) + ' TND';
}

renderCartSummary();

confirmBtn.addEventListener('click', async () => {
    if (cart.length === 0) {
        alert('Votre panier est vide.');
        return;
    }

    // Require login at purchase time
    if (!sessionStorage.getItem('auth')) {
        const backUrl = encodeURIComponent(window.location.pathname);
        window.location.href = 'login?redirect=' + backUrl; // /web/login.php
        return;
    }

    const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);

    try {
        const res = await fetch('/api.php?action=create_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cart, total })
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data || data.success === false) {
            alert((data && data.error) || 'Erreur lors de la création de la commande.');
            return;
        }

        alert('Commande confirmée ! Référence: ' + (data.reference || ''));
        cart = [];
        sessionStorage.removeItem('cart');
        window.location.href = 'shop';
    } catch (e) {
        console.error('Order error:', e);
        alert('Erreur réseau lors de la commande.');
    }
});
</script>
</body>
</html>
