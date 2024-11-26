<?php
session_start();

// Vérification de la connexion avec CSRF protection
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';


// Génération du token CSRF si non existant
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestion des actions POST avec vérification CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'edit':
                header('Location: edit_product.php?id=' . intval($_POST['id']));
                exit;
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM produits WHERE id = ?");
                    $stmt->execute([intval($_POST['id'])]);
                    $_SESSION['success_message'] = "Produit supprimé avec succès.";
                } catch (PDOException $e) {
                    error_log("Erreur de suppression : " . $e->getMessage());
                    $_SESSION['error_message'] = "Erreur lors de la suppression du produit.";
                }
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
        }
    }
}

// Récupération des produits avec gestion des filtres et de la pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // Nombre d'éléments par page
$offset = ($page - 1) * $limit;

try {
    // Préparation de la requête avec filtres
    $where = [];
    $params = [];

    if (!empty($_GET['search'])) {
        $where[] = "name LIKE ?";
        $params[] = "%" . $_GET['search'] . "%";
    }

    if (!empty($_GET['stock_status'])) {
        switch ($_GET['stock_status']) {
            case 'out':
                $where[] = "quantity = 0";
                break;
            case 'low':
                $where[] = "quantity > 0 AND quantity <= min_quantity";
                break;
            case 'normal':
                $where[] = "quantity > min_quantity AND quantity < max_quantity";
                break;
            case 'high':
                $where[] = "quantity >= max_quantity";
                break;
        }
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Compte total pour la pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits $whereClause");
    $stmt->execute($params);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Récupération des produits
    $stmt = $pdo->prepare("SELECT * FROM produits $whereClause ORDER BY name ASC LIMIT ? OFFSET ?");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des produits.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>État du Stock</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Variables globales */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --danger-color: #dc2626;
            --success-color: #16a34a;
            --warning-color: #ca8a04;
            --out-of-stock-color: #991b1b;
            --background-color: #f8fafc;
            --card-background: #ffffff;
            --text-color: #1e293b;
            --border-color: #e2e8f0;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --radius: 0.5rem;
        }

        /* Styles de base */
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* Navbar */
        .navbar {
            background-color: var(--card-background);
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .username {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            color: blue;
        }

        .logout-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: var(--danger-color);
            color: white;
            border-radius: var(--radius);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .logout-button:hover {
            background-color: #b91c1c;
        }

        /* Contenu principal */
        .main-content {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }

        /* Messages */
        .success-message,
        .error-message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease-out;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .success-message {
            background-color: #dcfce7;
            color: var(--success-color);
            border: 1px solid #86efac;
        }

        .error-message {
            background-color: #fee2e2;
            color: var(--danger-color);
            border: 1px solid #fca5a5;
        }

        /* Filtres */
        .filters-section {
            background-color: var(--card-background);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .search-input,
        .filter-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: white;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .search-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-filter {
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .btn-filter:hover {
            background-color: var(--primary-hover);
        }

        /* Table des produits */
        .products-table {
            width: 100%;
            background-color: var(--card-background);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        .products-table th,
        .products-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .products-table th {
            background-color: #f8fafc;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        .products-table tr:hover {
            background-color: #f8fafc;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-out {
            background-color: #fee2e2;
            color: var(--out-of-stock-color);
            font-weight: 700;
        }

        .status-low {
            background-color: #fee2e2;
            color: var(--danger-color);
        }

        .status-normal {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .status-high {
            background-color: #dcfce7;
            color: var(--success-color);
        }

        /* Boutons d'action */
        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .inline-form {
            margin: 0;
        }

        .btn-edit,
        .btn-delete {
            padding: 0.5rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-edit {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .btn-edit:hover {
            background-color: #bae6fd;
        }

        .btn-delete {
            background-color: #fee2e2;
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: #fca5a5;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-color);
            background-color: var(--card-background);
            transition: all 0.2s;
        }

        .page-link:hover {
            background-color: #f8fafc;
            border-color: var(--primary-color);
        }

        .page-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Message "Aucun résultat" */
        .no-results {
            text-align: center;
            padding: 3rem;
            background-color: var(--card-background);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .no-results i {
            font-size: 3rem;
            color: var(--border-color);
            margin-bottom: 1rem;
        }

        .no-results p {
            color: var(--text-color);
            font-size: 1.125rem;
            margin: 0;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                transform: translateY(-1rem);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .user-menu {
                flex-direction: column;
                gap: 1rem;
                width: 100%;
            }

            .filters-form {
                flex-direction: column;
            }

            .form-group {
                width: 100%;
            }

            .products-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-home"></i>
            <span>Tableau de Bord</span>
        </a>

        <div class="user-menu">
            <span class="username">
                <i class="fas fa-user"></i>
                <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <h1>État du Stock</h1>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <?php
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <?php
                echo htmlspecialchars($_SESSION['error_message']);
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <input type="text" name="search"
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        placeholder="Rechercher un produit..." class="search-input">
                </div>

                <div class="form-group">
                    <select name="stock_status" class="filter-select">
                        <option value="">Tous les statuts</option>
                        <option value="out" <?php echo isset($_GET['stock_status']) && $_GET['stock_status'] === 'out' ? 'selected' : ''; ?>>
                            Rupture de stock
                        </option>
                        <option value="low" <?php echo isset($_GET['stock_status']) && $_GET['stock_status'] === 'low' ? 'selected' : ''; ?>>
                            Stock bas
                        </option>
                        <option value="normal" <?php echo isset($_GET['stock_status']) && $_GET['stock_status'] === 'normal' ? 'selected' : ''; ?>>
                            Stock normal
                        </option>
                        <option value="high" <?php echo isset($_GET['stock_status']) && $_GET['stock_status'] === 'high' ? 'selected' : ''; ?>>
                            Stock élevé
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </form>
        </div>

        <!-- Liste des produits -->
        <div class="stock-status">
            <?php if (isset($products) && count($products) > 0): ?>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Nom du Produit</th>
                            <th>référence</th>
                            <th>Quantité</th>
                            <th>Min</th>
                            <th>Max</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $stock_status = '';
                            $status_class = '';

                            if ($product['quantity'] == 0) {
                                $stock_status = 'Rupture de stock';
                                $status_class = 'status-out';
                            } elseif ($product['quantity'] <= $product['min_quantity']) {
                                $stock_status = 'Stock bas';
                                $status_class = 'status-low';
                            } elseif ($product['quantity'] >= $product['max_quantity']) {
                                $stock_status = 'Stock élevé';
                                $status_class = 'status-high';
                            } else {
                                $stock_status = 'Stock normal';
                                $status_class = 'status-normal';
                            }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo htmlspecialchars($product['reference']); ?></td>
                                <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['min_quantity']); ?></td>
                                <td><?php echo htmlspecialchars($product['max_quantity']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $stock_status; ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn-edit" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline-form"
                                        onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" class="btn-delete" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination existante... -->
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-box-open"></i>
                    <p>Aucun produit trouvé.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Animation des messages de notification
        document.addEventListener('DOMContentLoaded', function () {
            const messages = document.querySelectorAll('.success-message, .error-message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 600);
                }, 5000);
            });
        });
    </script>
</body>

</html>