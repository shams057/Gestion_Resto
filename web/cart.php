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
</head>
<body>
<nav class="navbar">
    <div class="logo">FoodMarket</div>
    <div class="button">
        <a href="shop" style="color:white;margin-right:10px;text-decoration:none">
            Retour à la boutique
        </a>
        <?php if (!empty($_SESSION['auth'])): ?>
            <span><?php echo htmlspecialchars($_SESSION['auth']['email']); ?></span>
            <a href="logout" style="color:white;margin-left:10px;text-decoration:none">
                Déconnexion
            </a>
        <?php else: ?>
            <a href="login" style="color:white;margin-left:10px;text-decoration:none">
                Connexion
            </a>
        <?php endif; ?>
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
</div>

<script>
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
            const backUrl = encodeURIComponent('cart');
            window.location.href = `login?redirect=${backUrl}`;
            return;
        }

        if (!res.ok || !data || data.success === false) {
            alert((data && data.error) || 'Erreur lors de la création de la commande.');
            return;
        }

        alert('Commande confirmée ! Référence: ' + data.reference);

        cart = [];
        sessionStorage.removeItem('cart');
        window.location.href = 'shop';
    } catch (e) {
        console.error('Order error', e);
        alert('Erreur réseau lors de la commande.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadCartFromSession();
    renderCartSummary();
    confirmBtn.addEventListener('click', confirmOrder);
});
</script>
</body>
</html>
