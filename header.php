<?php
require_once 'auth_check.php';
$auth = AuthenticationManager::getInstance();
$auth->enforceAuthentication();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EPH SOBHA - <?php echo isset($page_title) ? $page_title : 'Tableau de Bord'; ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="">
                <i class="fas fa-hospital me-2"></i>
                EPH SOBHA
            </a>
            <div class="user-menu">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="icon-circle">
                            <i class="fas fa-user-circle"></i>
                        </span>
                        Mon compte
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="modifier_mot_de_passe.php">
                                <i class="fas fa-key me-2"></i>
                                Modifier mot de passe
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Déconnexion
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Optional page header - can be customized in individual pages -->
    <?php if(isset($show_page_header) && $show_page_header): ?>
    <header class="page-header">
        <div class="container text-center">
            <h1 class="hospital-title">
                <i class="<?php echo isset($page_header_icon) ? $page_header_icon : 'fas fa-file-alt'; ?> me-2"></i>
                <?php echo isset($page_header_title) ? $page_header_title : 'Page Titre'; ?>
            </h1>
            <p class="lead">
                <?php echo isset($page_header_description) ? $page_header_description : 'Description de la page'; ?>
            </p>
        </div>
    </header>
    <?php endif; ?>
