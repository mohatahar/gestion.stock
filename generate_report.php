<?php
session_start();
require_once 'db.php';

// Vérification de la connexion et des permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Vérification du CSRF Token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Initialisation des variables
$report = [];
$error = null;
$success = null;
$departments = []; // Pour la liste déroulante des départements
$products = []; // Pour la liste déroulante des produits

try {
    // Récupération de la liste des départements
    $stmt = $pdo->query("SELECT DISTINCT department FROM stock_movements ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupération de la liste des produits
    $stmt = $pdo->query("SELECT id, name FROM produits ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $movement_type = $_POST['movement_type'] ?? 'all';
    $department = $_POST['department'] ?? 'all';
    $product_id = $_POST['product_id'] ?? 'all';
    $export_format = $_POST['export_format'] ?? '';

    // Validation des données
    if (empty($start_date) || empty($end_date)) {
        $error = "Veuillez sélectionner une plage de dates.";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $error = "La date de début ne peut pas être postérieure à la date de fin.";
    } else {
        try {
            // Construction de la requête de base
            $query = "
                SELECT 
                    sm.id,
                    p.name AS product_name,
                    p.reference,
                    sm.quantity,
                    sm.movement_type,
                    sm.movement_date,
                    sm.movement_code,
                    sm.department,
                    u.username,
                    CASE 
                        WHEN sm.movement_type = 'entry' THEN sm.quantity
                        ELSE -sm.quantity
                    END as stock_impact
                FROM stock_movements sm
                JOIN produits p ON sm.product_id = p.id
                JOIN users u ON sm.user_id = u.id
                WHERE sm.movement_date BETWEEN :start_date AND :end_date
            ";

            $params = [
                ':start_date' => $start_date . ' 00:00:00',
                ':end_date' => $end_date . ' 23:59:59'
            ];

            // Ajout des filtres conditionnels
            if ($movement_type !== 'all') {
                $query .= " AND sm.movement_type = :movement_type";
                $params[':movement_type'] = $movement_type;
            }
            if ($department !== 'all') {
                $query .= " AND sm.department = :department";
                $params[':department'] = $department;
            }
            if ($product_id !== 'all') {
                $query .= " AND sm.product_id = :product_id";
                $params[':product_id'] = $product_id;
            }

            $query .= " ORDER BY sm.movement_date DESC";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcul des totaux
            $totals = [
                'entries' => 0,
                'exits' => 0,
                'total_movements' => count($report)
            ];

            foreach ($report as $row) {
                if ($row['movement_type'] === 'entry') {
                    $totals['entries'] += $row['quantity'];
                } else {
                    $totals['exits'] += $row['quantity'];
                }
            }

            // Export si demandé
            if ($export_format === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="rapport_stock_' . date('Y-m-d') . '.csv"');

                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Produit', 'Référence', 'Quantité', 'Type', 'Date', 'Raison', 'Service', 'Utilisateur']);

                foreach ($report as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['product_name'],
                        $row['reference'],
                        $row['quantity'],
                        $row['movement_type'],
                        $row['movement_date'],
                        $row['movement_code'],
                        $row['department'],
                        $row['username']
                    ]);
                }
                fclose($output);
                exit;
            }

            $success = "Rapport généré avec succès.";

        } catch (PDOException $e) {
            $error = "Erreur lors de la génération du rapport : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Générer Rapport - Gestion de Stock</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .filters-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .export-buttons {
            margin: 1rem 0;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
        }

        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .movement-type-entry {
            color: #28a745;
        }

        .movement-type-exit {
            color: #dc3545;
        }
    </style>
</head>

<body>
    <header>
        <nav>
            <a href="dashboard.php"><i class="fas fa-home"></i> Tableau de Bord</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </header>

    <main>
        <h1><i class="fas fa-chart-bar"></i> Générer Rapport</h1>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="generate_report.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="filters-container">
                <div class="form-group">
                    <label for="start_date">Date de début :</label>
                    <input type="date" name="start_date" id="start_date"
                        value="<?php echo $_POST['start_date'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="end_date">Date de fin :</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $_POST['end_date'] ?? ''; ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="movement_type">Type de mouvement :</label>
                    <select name="movement_type" id="movement_type">
                        <option value="all">Tous</option>
                        <option value="entry" <?php echo ($_POST['movement_type'] ?? '') === 'entry' ? 'selected' : ''; ?>>Entrée</option>
                        <option value="exit" <?php echo ($_POST['movement_type'] ?? '') === 'exit' ? 'selected' : ''; ?>>
                            Sortie</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="department">Service :</label>
                    <select name="department" id="department">
                        <option value="all">Tous</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($_POST['department'] ?? '') === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="product_id">Produit :</label>
                    <select name="product_id" id="product_id">
                        <option value="all">Tous</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo ($_POST['product_id'] ?? '') == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="action-button">
                    <i class="fas fa-search"></i> Générer
                </button>
                <button type="submit" name="export_format" value="csv" class="action-button secondary">
                    <i class="fas fa-download"></i> Exporter en CSV
                </button>
            </div>
        </form>

        <?php if (!empty($report)): ?>
            <div class="summary-box">
                <h2><i class="fas fa-info-circle"></i> Résumé</h2>
                <div class="summary-grid">
                    <div class="stat-card">
                        <h3>Total Mouvements</h3>
                        <p><?php echo $totals['total_movements']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Entrées</h3>
                        <p class="movement-type-entry"><?php echo $totals['entries']; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Sorties</h3>
                        <p class="movement-type-exit"><?php echo $totals['exits']; ?></p>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produit</th>
                            <th>Référence</th>
                            <th>Quantité</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Raison</th>
                            <th>Service</th>
                            <th>Utilisateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['reference']); ?></td>
                                <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                                <td class="movement-type-<?php echo $row['movement_type']; ?>">
                                    <?php echo $row['movement_type'] === 'entry' ? 'Entrée' : 'Sortie'; ?>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['movement_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['movement_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Valider que la date de fin n'est pas antérieure à la date de début
        document.getElementById('end_date').addEventListener('change', function () {
            var startDate = document.getElementById('start_date').value;
            var endDate = this.value;

            if (startDate && endDate && startDate > endDate) {
                alert('La date de fin ne peut pas être antérieure à la date de début.');
                this.value = startDate;
            }
        });

        // Mettre à jour automatiquement la date de fin si la date de début est postérieure
        document.getElementById('start_date').addEventListener('change', function () {
            var endDate = document.getElementById('end_date');
            if (endDate.value && this.value > endDate.value) {
                endDate.value = this.value;
            }
        });

        // Fonction pour pré-remplir les dates avec le mois en cours
        function setCurrentMonthDates() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

            document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
        }

        // Fonction pour pré-remplir les dates du mois précédent
        function setPreviousMonthDates() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);

            document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
            document.getElementById('end_date').value = lastDay.toISOString().split('T')[0];
        }

        // Ajouter une recherche rapide dans le tableau
        function searchTable() {
            const input = document.getElementById('tableSearch');
            const filter = input.value.toLowerCase();
            const table = document.querySelector('table');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;

                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell) {
                        const text = cell.textContent || cell.innerText;
                        if (text.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                row.style.display = found ? '' : 'none';
            }
        }

        // Fonction pour trier le tableau
        function sortTable(n) {
            const table = document.querySelector('table');
            let rows, switching, i, x, y, shouldSwitch, dir = 'asc';
            let switchcount = 0;
            switching = true;

            while (switching) {
                switching = false;
                rows = table.rows;

                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName('td')[n];
                    y = rows[i + 1].getElementsByTagName('td')[n];

                    if (dir === 'asc') {
                        if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir === 'desc') {
                        if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                            shouldSwitch = true;
                            break;
                        }
                    }
                }

                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else {
                    if (switchcount === 0 && dir === 'asc') {
                        dir = 'desc';
                        switching = true;
                    }
                }
            }

            // Mettre à jour les indicateurs de tri
            const headers = table.getElementsByTagName('th');
            for (let i = 0; i < headers.length; i++) {
                headers[i].classList.remove('sort-asc', 'sort-desc');
            }
            headers[n].classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');
        }

        // Ajout des raccourcis pour les périodes courantes
        document.addEventListener('DOMContentLoaded', function () {
            // Ajouter la barre de recherche si un rapport est présent
            if (document.querySelector('table')) {
                const searchBox = document.createElement('div');
                searchBox.className = 'search-box';
                searchBox.innerHTML = `
                    <input type="text" id="tableSearch" 
                           placeholder="Rechercher dans le rapport..." 
                           onkeyup="searchTable()"
                           class="search-input">
                `;
                document.querySelector('.table-container').insertBefore(
                    searchBox,
                    document.querySelector('table')
                );
            }

            // Ajouter les boutons de raccourcis de période
            const periodShortcuts = document.createElement('div');
            periodShortcuts.className = 'period-shortcuts';
            periodShortcuts.innerHTML = `
                <button type="button" onclick="setCurrentMonthDates()" class="shortcut-button">
                    <i class="fas fa-calendar-alt"></i> Mois en cours
                </button>
                <button type="button" onclick="setPreviousMonthDates()" class="shortcut-button">
                    <i class="fas fa-calendar-minus"></i> Mois précédent
                </button>
            `;
            document.querySelector('.filters-container').insertBefore(
                periodShortcuts,
                document.querySelector('.form-group')
            );

            // Rendre les en-têtes de tableau triables
            const headers = document.querySelectorAll('table th');
            headers.forEach((header, index) => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => sortTable(index));
            });
        });
    </script>

    <style>
        .period-shortcuts {
            grid-column: 1 / -1;
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .shortcut-button {
            padding: 0.5rem 1rem;
            background-color: #007bff;
            /* Corrected hex color code */
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .shortcut-button:hover {
            background-color: #0056b3;
            /* Darker blue for hover state */
        }

        .search-box {
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 1rem;
        }

        th.sort-asc::after {
            content: ' ↑';
            color: #666;
        }

        th.sort-desc::after {
            content: ' ↓';
            color: #666;
        }

        @media print {

            .filters-container,
            .form-actions,
            .period-shortcuts,
            .search-box,
            nav {
                display: none;
            }
        }
    </style>

</body>

</html>

<?php include 'footer.php'; ?>