<?php
session_start();
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Connexion - Gestion Resto</title>
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
            width: 420px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
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

        #errorMsg {
            color: #c0392b;
            font-size: 14px;
            margin-top: 8px;
            display: none;
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
        <h2>Welcome</h2>
        <form id="loginForm">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p id="errorMsg"></p>
        <p class="muted">
            Don't have an account?
            <a class="link" href="signup">Create one</a>
        </p>
        <div class="divider">OU</div>

        <div class="social-container">
            <a href="/auth?provider=google" class="btn-social google">
                <img src="img/google.png" alt="Google" style="width:18px; height:18px; margin-right:10px;">
                Continuer avec Google
            </a>

            
            </a>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('loginForm');
            const errorMsg = document.getElementById('errorMsg');

            if (!form) return;

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                errorMsg.style.display = 'none';
                errorMsg.textContent = '';

                const formData = new FormData(this);
                const payload = {
                    email: formData.get('email'),
                    password: formData.get('password')
                };

                try {
                    const res = await fetch('api?action=login', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify(payload)
                    });

                    const text = await res.text();
                    let data = null;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON from login API. Raw:', text);
                        throw new Error('Invalid JSON');
                    }

                    if (data.status === 'ok') {
                        sessionStorage.setItem('auth', '1');
                        sessionStorage.setItem('role', data.role || 'client');

                        const params = new URLSearchParams(window.location.search);
                        const redirect = params.get('redirect');

                        if (redirect) {
                            window.location.href = redirect;
                        } else if (data.role === 'admin') {
                            window.location.href = 'dashboard';
                        } else {
                            window.location.href = 'shop';
                        }
                    } else {
                        const msg = data.message || data.error || 'Incorrect login';
                        alert(msg);
                        errorMsg.textContent = msg;
                        errorMsg.style.display = 'block';
                    }
                } catch (err) {
                    console.error('Fetch Error:', err);
                    alert('Erreur réseau');
                    errorMsg.textContent = 'Erreur réseau';
                    errorMsg.style.display = 'block';
                }
            });
        });
    </script>
</body>

</html>