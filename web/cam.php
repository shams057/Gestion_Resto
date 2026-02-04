<?php
// --- SECURITY BLOCK START ---
session_start();

// 1. Check if user is logged in
// If 'auth' is empty in session, redirect to login page
if (empty($_SESSION['auth'])) {
    header("Location: login"); 
    exit;
}

// 2. Check if user is an ADMIN
// If the role is NOT admin, kick them out to the shop page
if (($_SESSION['auth']['role'] ?? '') !== 'admin') {
    header("Location: shop");
    exit;
}
// --- SECURITY BLOCK END ---
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance IA - Admin Restricted</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1a1a1a;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            transition: background-color 0.5s;
        }

        h1 {
            margin-bottom: 10px;
        }

        .container {
            position: relative;
            border: 4px solid #444;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            min-height: 400px;
            /* Evite le saut d'image lors du changement */
            background: #000;
        }

        img {
            display: block;
            max-width: 100%;
            height: auto;
            width: 800px;
        }

        .info-panel {
            margin-top: 20px;
            padding: 20px;
            background: #333;
            border-radius: 8px;
            width: 80%;
            max-width: 760px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-box {
            font-size: 1.2rem;
        }

        .stat-value {
            font-weight: bold;
            font-size: 1.5rem;
            color: #00ff00;
        }

        .live-dot {
            height: 15px;
            width: 15px;
            background-color: red;
            border-radius: 50%;
            display: inline-block;
            animation: blink 1s infinite;
        }

        /* Style du dropdown */
        select {
            padding: 5px 10px;
            font-size: 1rem;
            border-radius: 5px;
            border: none;
            background-color: #555;
            color: white;
            cursor: pointer;
        }

        @keyframes blink {
            50% {
                opacity: 0;
            }
        }
    </style>
</head>

<body>

    <h1><span class="live-dot"></span> Surveillance IA En Direct</h1>

    <div class="container">
        <img id="video-stream" src="http://localhost:3000/video_feed" alt="Flux Caméra">
    </div>

    <div class="info-panel">
        <div class="stat-box">Statut: <span id="status-txt" class="stat-value">Chargement...</span></div>
        <div class="stat-box">Personnes: <span id="count-txt" class="stat-value">0</span></div>

        <div class="stat-box">
            Source:
            <select id="cam-select" onchange="switchCamera()">
                <option value="1" selected>Table 1</option>
                <option value="0">Table 2</option>
            </select>
        </div>
    </div>

    <script>
        // Fonction pour changer de caméra
        async function switchCamera() {
            const select = document.getElementById('cam-select');
            const index = select.value;
            const img = document.getElementById('video-stream');

            // 1. Appeler l'API pour changer la variable globale python
            try {
                await fetch(`http://localhost:3000/api/set_camera/${index}`);

                // 2. Forcer le rechargement de l'image pour relancer le stream avec le nouvel index
                // On ajoute un timestamp (?t=...) pour empêcher le cache navigateur
                img.src = `http://localhost:3000/video_feed?t=${new Date().getTime()}`;

            } catch (error) {
                console.error("Erreur lors du changement de caméra:", error);
                alert("Erreur de connexion à l'API");
            }
        }

        async function fetchData() {
            try {
                const response = await fetch('http://localhost:3000/api/data');
                const data = await response.json();

                document.getElementById('status-txt').innerText = data.message;
                document.getElementById('count-txt').innerText = data.personnes;

                const statusSpan = document.getElementById('status-txt');
                const countSpan = document.getElementById('count-txt');

                if (data.presence) {
                    document.body.style.backgroundColor = "#2a1a1a";
                    statusSpan.style.color = "#00ff00";
                    countSpan.style.color = "#00ff00";
                } else {
                    document.body.style.backgroundColor = "#1a1a1a";
                    statusSpan.style.color = "#888";
                    countSpan.style.color = "#888";
                }

            } catch (error) {
                console.error("Erreur API:", error);
                document.getElementById('status-txt').innerText = "Déconnecté";
                document.getElementById('status-txt').style.color = "red";
            }
        }

        setInterval(fetchData, 500);
    </script>

</body>
</html>