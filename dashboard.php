<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Vérification de l'inactivité
$inactive = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['last_activity'] = time();

require_once 'db.php';

// Récupération des statistiques (à adapter selon votre base de données)
try {
    // Nombre total de produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits");
    $totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Produits en rupture de stock
    $stmt = $pdo->query("SELECT COUNT(*) as low FROM produits WHERE quantity = 0");
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['low'];


    // Derniers mouvements de stock avec plus de détails
    $stmt = $pdo->query("
        SELECT 
            p.name, 
            sm.quantity, 
            sm.movement_type, 
            sm.movement_date, 
            sm.movement_code,
            sm.department,
            u.username as user_name
        FROM stock_movements sm
        JOIN produits p ON sm.product_id = p.id
        JOIN users u ON sm.user_id = u.id
        ORDER BY sm.movement_date DESC
        LIMIT 10
    ");
    $recentMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage());
    $error = "Une erreur est survenue lors de la récupération des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Gestion de Stock Hospitalier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-hospital"></i>
            <span>Gestion de Stock Hospitalier</span>
        </a>
        <div class="user-menu">
            <div class="user-info">
                <div class="username">
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?></div>
            </div>
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Quick Actions -->
        <div class="quick-actions">
        <button class="action-button" onclick="window.location.href='add_product.php'">
    <i class="fas fa-plus"></i>
    Nouveau Produit
</button>
<button class="action-button" onclick="window.location.href='stock_entry.php'">
    <i class="fas fa-file-import"></i>
    Entrée Stock
</button>
<button class="action-button" onclick="window.location.href='stock_exit.php'">
    <i class="fas fa-file-export"></i>
    Sortie Stock
</button>
<button class="action-button" onclick="window.location.href='categories.php'">
    <i class="fas fa-tags"></i>
    Gérer Catégories
</button>
<button class="action-button" onclick="window.location.href='suppliers.php'">
    <i class="fas fa-truck"></i>
    Gérer Fournisseurs
</button>
<button class="action-button" onclick="window.location.href='stock_status.php'">
    <i class="fas fa-warehouse"></i>
    État du Stock
</button>
<button class="action-button" onclick="window.location.href='generate_report.php'">
    <i class="fas fa-file-alt"></i>
    Générer Rapport
</button>
<button class="action-button" onclick="window.location.href='inventory.php'">
    <i class="fas fa-file-alt"></i>
    Inventaire
</button>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Total Produits</div>
                        <div class="stat-value"><?php echo number_format($totalProducts); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card low-stock">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Rupture de Stock</div>
                        <div class="stat-value"><?php echo number_format($lowStock); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3 class="section-title">Activités Récentes</h3>
            <div class="activity-list">
                <?php foreach ($recentMovements as $movement): ?>
                    <div class="activity-item">
                    <div class="activity-icon <?php echo $movement['movement_type']; ?>">
                            <i class="fas fa-<?php echo $movement['movement_type'] === 'entry' ? 'arrow-down' : ($movement['movement_type'] === 'adjustment' ? 'sync' : 'arrow-up'); ?>"></i>
                        </div>
                        <div class="activity-details">
                            <h4><?php echo htmlspecialchars($movement['name']); ?></h4>
                            <p>
                                <?php 
                                $typeText = $movement['movement_type'] === 'entry' ? 'Entrée' : 
                                          ($movement['movement_type'] === 'exit' ? 'Sortie' : 'Ajustement');
                                echo $typeText . ' de ' . abs($movement['quantity']) . ' unités';
                                if ($movement['department']) {
                                    echo ' - Service: ' . htmlspecialchars($movement['department']);
                                }
                                if ($movement['movement_code']) {
                                    echo ' - Motif: ' . htmlspecialchars($movement['movement_code']);
                                }
                                ?>
                            </p>
                            <small>Par <?php echo htmlspecialchars($movement['user_name']); ?></small>
                        </div>
                        <div class="activity-time">
                            <?php 
                            $date = new DateTime($movement['movement_date']);
                            echo $date->format('d/m/Y H:i');
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script>
        // Initialisation des graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique des mouvements de stock
            const ctx = document.getElementById('stockMovementsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php 
                        $stmt = $pdo->query("
                            SELECT DATE_FORMAT(movement_date, '%d/%m') as date
                            FROM stock_movements
                            GROUP BY DATE(movement_date)
                            ORDER BY movement_date DESC
                            LIMIT 7
                        ");
                        echo json_encode(array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN)));
                    ?>,
                    datasets: [{
                        label: 'Mouvements de stock',
                        data: <?php 
                            $stmt = $pdo->query("
                                SELECT COUNT(*) as count
                                FROM stock_movements
                                GROUP BY DATE(movement_date)
                                ORDER BY movement_date DESC
                                LIMIT 7
                            ");
                            echo json_encode(array_reverse($stmt->fetchAll(PDO::FETCH_COLUMN)));
                        ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });

        // Fonction pour confirmer les actions importantes
        function confirmAction(message) {
            return confirm(message);
        }

        // Fonction pour mettre à jour le statut des alertes
        function updateAlertStatus(alertId, status) {
            fetch('update_alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `alert_id=${alertId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`alert-${alertId}`).remove();
                    updateAlertCount();
                }
            })
            .catch(error => console.error('Erreur:', error));
        }

        // Fonction pour mettre à jour le compteur d'alertes
        function updateAlertCount() {
            const alertContainer = document.querySelector('.alerts-container');
            const remainingAlerts = alertContainer.querySelectorAll('.alert-item').length;
            document.getElementById('alertCount').textContent = remainingAlerts;
        }
    </script>
    
    <!-- Alertes -->
    <div class="alerts-section">
        <h3 class="section-title">Alertes (<span id="alertCount"><?php echo $lowStock; ?></span>)</h3>
        <div class="alerts-container">
            <h1>Alerts Stock</h1>
        <?php
                $stmt = $pdo->query("
                    SELECT 
                        p.id,
                        p.name,
                        p.reference,
                        p.quantity,
                        p.min_quantity,
                        p.max_quantity,
                        p.unit,
                        p.location,
                        c.name as category_name,
                        s.name as supplier_name
                    FROM produits p
                    JOIN categories c ON p.category_id = c.id
                    LEFT JOIN fournisseurs s ON p.supplier_id = s.id
                    WHERE p.quantity = 0
                    LIMIT 10
                ");
                $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($alerts as $alert): 
                ?>
                          <div class="alert-item" id="alert-<?php echo $alert['id']; ?>">
                        <div class="alert-content">
                            <div class="alert-header">
                                <h4><?php echo htmlspecialchars($alert['name']); ?> (<?php echo htmlspecialchars($alert['reference']); ?>)</h4>
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($alert['category_name']); ?>
                                </span>
                            </div>
                            <div class="alert-details">
                                <p class="stock-level">
                                    Quantité actuel: <strong style="color: red"><?php echo $alert['quantity']; ?></strong> <?php echo htmlspecialchars($alert['unit']); ?>
                                    (Min: <span style="color: green"><?php echo $alert['min_quantity']; ?>)
                                </p>
                            </div>
                        </div>
                    <h3>-----------------------------------------</h3>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <!-- Graphique des mouvements -->
    <div class="chart-section">
        <h3 class="section-title">Mouvements de Stock (7 derniers jours)</h3>
        <canvas id="stockMovementsChart"></canvas>
    </div>

</body>
</html>

<?php include 'footer.php'; ?>