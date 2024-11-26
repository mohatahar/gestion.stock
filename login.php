<?php 
session_start(); 
require_once 'db.php'; 
$error = ''; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($password == $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_activity'] = time();
                    session_regenerate_id(true);
                    header('Location: dashboard.php');
                    exit;
                }
            }
            $error = 'Nom d\'utilisateur ou mot de passe incorrect';
        } catch (PDOException $e) {
            error_log("Erreur de connexion : " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la connexion';
        }
    } else {
        $error = 'Veuillez remplir tous les champs';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Connexion - Gestion de Stock</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #3b82f6;
            --error: #ef4444;
            --text: #f8fafc;
            --text-dark: #1e293b;
            --background: #0f172a;
            --card: rgba(30, 41, 59, 0.7);
            --border: rgba(37, 99, 235, 0.2);
            --gradient-start: #2563eb;
            --gradient-end: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes float {
            0% { transform: translatey(0px); }
            50% { transform: translatey(-20px); }
            100% { transform: translatey(0px); }
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 0;
            background: linear-gradient(-45deg, #2563eb, #3b82f6, #1e40af, #2563eb);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            opacity: 0.15;
        }

        .floating-shapes {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(37, 99, 235, 0.1) 0%, rgba(59, 130, 246, 0.1) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
        }

        .shape:nth-child(1) {
            width: 300px;
            height: 300px;
            top: -150px;
            right: -150px;
            animation: float 6s ease-in-out infinite;
            clip-path: polygon(25% 0%, 100% 0%, 75% 100%, 0% 100%);
        }

        .shape:nth-child(2) {
            width: 200px;
            height: 200px;
            bottom: -100px;
            left: -100px;
            animation: float 8s ease-in-out infinite;
            clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%);
        }

        .container {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            padding: 2.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            position: relative;
            z-index: 2;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transform-style: preserve-3d;
            transform: perspective(1000px);
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: perspective(1000px) rotateX(2deg) rotateY(2deg);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            transform-style: preserve-3d;
        }

        .logo i {
            font-size: 3rem;
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
            display: inline-block;
            animation: float 6s ease-in-out infinite;
        }

        h1, h2 {
            color: var(--text);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        h2 {
            font-size: 1.25rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--error);
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            color: #fecaca;
            font-size: 0.875rem;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }

        .form-group:hover {
            transform: translateZ(20px);
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
            transform-style: preserve-3d;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text);
            opacity: 0.7;
            transition: all 0.3s ease;
        }

        input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            font-size: 0.875rem;
            color: var(--text);
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
            transform: translateZ(10px);
        }

        input::placeholder {
            color: rgba(248, 250, 252, 0.5);
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(to right, var(--gradient-start), var(--gradient-end));
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to right,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            transition: 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px) translateZ(20px);
            box-shadow: 0 20px 40px -15px rgba(37, 99, 235, 0.5);
        }

        @media (max-width: 480px) {
            .container {
                padding: 1.5rem;
                margin: 1rem;
            }
        }

        .glow {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: radial-gradient(circle at 50% 50%, 
                rgba(37, 99, 235, 0.1), 
                transparent 60%);
            pointer-events: none;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="background-animation"></div>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="glow"></div>
        <div class="logo">
            <i class="fas fa-hospital"></i>
            <h1>Gestion de Stock</h1>
            <h2>EPH SOBHA</h2>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <div class="input-wrapper">
                    <i class="fas fa-user-nurse"></i>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        placeholder="Votre identifiant"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        placeholder="Mot de passe"
                    >
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Se connecter
            </button>
        </form>
    </div>
</body>
</html>