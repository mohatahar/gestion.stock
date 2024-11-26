<?php
session_start();
require_once 'auth_check.php';

$auth = AuthenticationManager::getInstance();

// Enregistrer un message de déconnexion avant de détruire la session
$username = $auth->getCurrentUsername();
$message = "Au revoir, " . htmlspecialchars($username) . " !";

// Effectuer la déconnexion
$auth->logout();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - Gestion de Protocoles Opératoires</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --gradient-1: #0beef9;
            --gradient-2: #48ff9f;
            --dark-bg: #1a1a1a;
            --text-color: #e0e0e0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--dark-bg);
            color: var(--text-color);
        }

        .logout-container {
            text-align: center;
            padding: 40px;
            background: rgba(28, 28, 28, 0.8);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 400px;
            width: 90%;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logout-icon {
            font-size: 48px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, var(--gradient-1), var(--gradient-2));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .logout-message {
            margin-bottom: 30px;
            font-size: 1.2em;
            color: var(--text-color);
        }

        .redirect-message {
            font-size: 0.9em;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
        }

        .login-link {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(45deg, var(--gradient-1), var(--gradient-2));
            color: var(--dark-bg);
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .login-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(11, 238, 249, 0.4);
        }

        .countdown {
            font-weight: bold;
            color: var(--gradient-1);
        }

        #progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 20px;
            overflow: hidden;
        }

        #progress-bar-fill {
            height: 100%;
            background: linear-gradient(45deg, var(--gradient-1), var(--gradient-2));
            width: 0%;
            transition: width 1s linear;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <i class="fas fa-sign-out-alt logout-icon"></i>
        <div class="logout-message"><?php echo $message; ?></div>
        <div class="redirect-message">
            Redirection automatique dans <span id="countdown" class="countdown">5</span> secondes
        </div>
        <a href="login.php" class="login-link">Se reconnecter</a>
        <div id="progress-bar">
            <div id="progress-bar-fill"></div>
        </div>
    </div>

    <script>
        // Compte à rebours et barre de progression
        let timeLeft = 5;
        const countdownElement = document.getElementById('countdown');
        const progressBar = document.getElementById('progress-bar-fill');
        
        // Démarrer la barre de progression
        progressBar.style.width = '0%';
        setTimeout(() => {
            progressBar.style.width = '100%';
        }, 50);

        const countdown = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = 'login.php';
            }
        }, 1000);
    </script>
</body>
</html>