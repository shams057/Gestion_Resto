<?php
// signup.php
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="utf-8">
  <title>Inscription - Gestion Resto</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="img/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="img/favicon.png/favicon.svg" />
    <link rel="shortcut icon" href="img/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="Gresto" />
    <link rel="manifest" href="img/site.webmanifest" />

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

    /* Social Buttons Container */
    .social-container {
      margin-top: 20px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .btn-social {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: background 0.3s ease, transform 0.1s ease;
      border: 1px solid #ddd;
    }

    .btn-social:active {
      transform: scale(0.98);
    }

    /* Google Specific */
    .btn-social.google {
      background: #ffffff;
      color: #757575;
    }

    .btn-social.google:hover {
      background: #f8f8f8;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Facebook Specific */
    .btn-social.facebook {
      background: #1877F2;
      color: #ffffff;
      border: none;
    }

    .btn-social.facebook:hover {
      background: #166fe5;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Divider "OR" */
    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 20px 0;
      color: #aaa;
      font-size: 12px;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #eee;
    }

    .divider:not(:empty)::before {
      margin-right: .5em;
    }

    .divider:not(:empty)::after {
      margin-left: .5em;
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

    <div>
      <label>Confirmer le mot de passe</label>
      <input id="passwordConfirm" type="password" placeholder="Confirmez le mot de passe">
    </div>


    <button id="signupBtn">S'inscrire</button>
    <div class="divider">OU</div>
    <div class="social-container">
      <a href="/auth?provider=google" class="btn-social google">
        <img src="img/google.png" alt="Google" style="width:18px; height:18px; margin-right:10px;">
        Continuer avec Google
      </a>

      
    </div>

    <p id="error"></p>

    <p class="muted">
      Vous avez déjà un compte ?
      <a class="link" href="login">Connexion</a>
    </p>
  </div>

  <script>
    document.getElementById('signupBtn').addEventListener('click', async () => {
      const nom = document.getElementById('nom').value.trim();
      const telephone = document.getElementById('telephone').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;
      const passwordConfirm = document.getElementById('passwordConfirm').value;
      const err = document.getElementById('error');

      err.textContent = '';

      if (!nom || !email || !password) {
        err.textContent = 'Nom, email et mot de passe requis.';
        return;
      }

      if (password !== passwordConfirm) {
        err.textContent = 'Les mots de passe ne correspondent pas.';
        return;
      }

      try {
        const res = await fetch('api?action=signup', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ nom, telephone, email, password })
        });

        const text = await res.text();
        let data = null;
        try {
          data = JSON.parse(text);
        } catch (e) {
          console.error('Invalid JSON from signup API:', text);
          err.textContent = 'Erreur serveur.';
          return;
        }

        if (!res.ok || !data) {
          err.textContent = (data && data.error) || 'Erreur lors de l\'inscription.';
          return;
        }

        if (data.success) {
          window.location.href = 'login';
        } else {
          err.textContent = data.error || 'Erreur lors de l\'inscription.';
        }
      } catch (e) {
        console.error('Signup fetch error:', e);
        err.textContent = 'Erreur réseau';
      }
    });
  </script>

</body>

</html>