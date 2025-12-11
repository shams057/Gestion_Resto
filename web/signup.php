<?php
// signup.php
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Inscription - Gestion Resto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: #f4f6f8;
        }
        .card {
            background: #fff;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            width: 520px;
        }
        label {
            font-size: 14px;
            font-weight: 600;
            display: block;
            margin-top: 10px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 6px 0 10px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        button {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            background: #2c3e50;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
        }
        .muted {
            color: #666;
            font-size: 14px;
            margin-top: 8px;
        }
        .small {
            font-size: 13px;
            color: #999;
            margin-top: 6px;
        }
        a.link {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
        }
        #error {
            color: #c0392b;
            font-size: 14px;
            margin-top: 10px;
            min-height: 18px;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Créer un compte</h2>

    <div>
        <label>Nom complet</label>
        <input id="nom" type="text" placeholder="Votre nom">
    </div>

    <div>
        <label>Téléphone</label>
        <input id="telephone" type="text" placeholder="+216XXXXXXXX">
    </div>

    <div>
        <label>Email</label>
        <input id="email" type="email" placeholder="votre@email.com">
    </div>

    <div>
        <label>Mot de passe</label>
        <input id="password" type="password" placeholder="Mot de passe">
    </div>

    <button id="signupBtn">S'inscrire</button>

    <p id="error"></p>

    <p class="muted">
        Vous avez déjà un compte ?
        <a class="link" href="login.php">Connexion</a>
    </p>
</div>

<script>
document.getElementById('signupBtn').addEventListener('click', async () => {
    const nom = document.getElementById('nom').value.trim();
    const telephone = document.getElementById('telephone').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const err = document.getElementById('error');

    err.textContent = '';

    if (!nom || !email || !password) {
        err.textContent = 'Nom, email et mot de passe requis.';
        return;
    }

    try {
        const res = await fetch('api.php?action=signup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                nom,
                telephone,
                email,
                password
            })
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data) {
            err.textContent = (data && data.error) || 'Erreur lors de l\'inscription.';
            return;
        }

        if (data.success) {
            // after signup, go to buypage.html for client
            window.location.href = data.redirect || 'buypage.html';
        } else {
            err.textContent = data.error || 'Erreur lors de l\'inscription.';
        }
    } catch (e) {
        console.error(e);
        err.textContent = 'Erreur réseau';
    }
});
</script>
</body>
</html>
