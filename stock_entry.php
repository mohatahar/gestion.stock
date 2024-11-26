<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Liste des types d'entrée
$entry_types = [
    'ACHAT' => 'Achat',
    'RETOUR' => 'Retour service',
    'DON' => 'Don',
    'Correction inventaire' => 'Correction inventaire +'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $movement_code = trim($_POST['movement_code']); // Nouveau champ
    $department = trim($_POST['department']);
    $supplier_id = intval($_POST['supplier_id']);
    $purchase_price = floatval($_POST['purchase_price']);
    $invoice_number = trim($_POST['invoice_number']);
    $user_id = $_SESSION['user_id'];

    if ($product_id > 0 && $quantity > 0 && array_key_exists($movement_code, $entry_types)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    product_id, quantity, movement_type, movement_date, 
                    movement_code, department, user_id, supplier_id, 
                    purchase_price, invoice_number
                )
                VALUES (
                    :product_id, :quantity, 'entry', NOW(),
                    :movement_code, :department, :user_id, :supplier_id,
                    :purchase_price, :invoice_number
                )
            ");

            $stmt->execute([
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':movement_code' => $movement_code,
                ':department' => $department,
                ':user_id' => $user_id,
                ':supplier_id' => $supplier_id,
                ':purchase_price' => $purchase_price,
                ':invoice_number' => $invoice_number
            ]);

            $updateStmt = $pdo->prepare("
                UPDATE produits 
                SET quantity = quantity + :quantity 
                WHERE id = :product_id
            ");
            
            $updateStmt->execute([
                ':quantity' => $quantity,
                ':product_id' => $product_id,
            ]);

            $pdo->commit();
            $success = "Entrée de stock enregistrée avec succès.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez saisir des informations valides.";
    }
}

$products = $pdo->query("SELECT id, name, reference FROM produits ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $pdo->query("SELECT id, name FROM fournisseurs ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrée Stock - Gestion de Stock Hospitalier</title>
    <link rel="stylesheet" href="style.css">

    <style type="text/css">
        .hidden {
            display: none !important;
        }
        /* Ajout d'un style visible pour debug */
        .visible {
            display: block !important;
        }
    </style>
</head>
<body>
<<nav class="navbar">
    <a href="dashboard.php" class="logo">
            <i class="fas fa-home"></i>
            <span>Tableau de Bord</span>
        </a>
      
        <div class="user-menu">
            <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
    </nav>

    <main class="main-content">
        <h1>Entrée Stock</h1>

        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="stock_entry.php">
        <div class="form-group">
                <label for="movement_code">Type d'entrée : *</label>
                <select name="movement_code" id="movement_code" required>
                    <option value="">-- Sélectionnez le type d'entrée --</option>
                    <?php foreach ($entry_types as $code => $description): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>">
                            <?php echo htmlspecialchars($description); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="product_id">Produit : *</label>
                <select name="product_id" id="product_id" required>
                    <option value="">-- Sélectionnez un produit --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name'] . ' - Réf: ' . $product['reference']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="quantity">Quantité : *</label>
                <input type="number" name="quantity" id="quantity" min="1" required>
            </div>

             <!-- Champs conditionnels -->
             <div id="achatFields1" class="hidden">
                <div class="form-group">
                    <label for="supplier_id">Fournisseur : *</label>
                    <select name="supplier_id" id="supplier_id">
                        <option value="">-- Sélectionnez un fournisseur --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>">
                                <?php echo htmlspecialchars($supplier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="achatFields2" class="hidden">
                <div class="form-group">
                    <label for="purchase_price">Prix d'achat unitaire : *</label>
                    <input type="number" name="purchase_price" id="purchase_price" step="0.01" min="0">
                </div>

                <div class="form-group">
                    <label for="invoice_number">Numéro de facture : *</label>
                    <input type="text" name="invoice_number" id="invoice_number">
                </div>
            </div>

            <div id="retourFields" class="hidden">
                <div class="form-group">
                    <label for="department">Service/Department : *</label>
                    <input type="text" name="department" id="department">
                </div>
            </div>

            <div id="correctionFields" class="hidden">
                <div class="form-group">
                    <label for="reason">Raison : *</label>
                    <input type="text" name="reason" id="reason">
                </div>
            </div>

            <button type="submit" class="action-button">Enregistrer</button>
        </form>
    </main>

    <script>
        document.getElementById('movement_code').addEventListener('change', function() {
            // Cacher tous les champs conditionnels
            document.getElementById('achatFields1').classList.add('hidden');
            document.getElementById('achatFields2').classList.add('hidden');
            document.getElementById('retourFields').classList.add('hidden');
            document.getElementById('correctionFields').classList.add('hidden');

            // Désactiver tous les champs required
            document.getElementById('supplier_id').required = false;
            document.getElementById('purchase_price').required = false;
            document.getElementById('invoice_number').required = false;
            document.getElementById('department').required = false;
            document.getElementById('reason').required = false;

            // Afficher les champs selon le type sélectionné
            switch(this.value) {
                case 'ACHAT':
                    document.getElementById('achatFields1').classList.remove('hidden');
                    document.getElementById('achatFields2').classList.remove('hidden');
                    document.getElementById('supplier_id').required = true;
                    document.getElementById('purchase_price').required = true;
                    document.getElementById('invoice_number').required = true;
                    break;
                case 'RETOUR':
                    document.getElementById('retourFields').classList.remove('hidden');
                    document.getElementById('department').required = true;
                    break;
                case 'DON':
                    document.getElementById('achatFields1').classList.remove('hidden');
                    document.getElementById('supplier_id').required = true;
                    break;
                case 'Correction inventaire':
                    document.getElementById('correctionFields').classList.remove('hidden');
                    document.getElementById('reason').required = true;
                    break;
            }
        });
    </script>

</body>
</html>

<?php include 'footer.php'; ?>