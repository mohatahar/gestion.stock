<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Liste des types de sortie
$exit_types = [
    'CONSOMMATION' => 'Utilisation normale par un service',
    'RETOUR' => 'Retour au fournisseur',
    'ENDOMMAGE' => 'Perte/Casse',
    'PEREMPTION' => 'Péremption',
    'CORRECTION INVENTAIRE' => 'Correction inventaire -'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $movement_code = trim($_POST['movement_code']);
    $department = trim($_POST['department']);
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT quantity FROM produits WHERE id = :product_id");
    $stmt->execute([':product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product && $quantity > 0 && $quantity <= $product['quantity'] && array_key_exists($movement_code, $exit_types)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    product_id, quantity, movement_type, movement_date, 
                    movement_code, department, user_id
                )
                VALUES (
                    :product_id, :quantity, 'exit', NOW(),
                    :movement_code, :department, :user_id
                )
            ");

            $stmt->execute([
                ':product_id' => $product_id,
                ':quantity' => $quantity,
                ':movement_code' => $movement_code,
                ':department' => $department,
                ':user_id' => $user_id,
            ]);

            $updateStmt = $pdo->prepare("
                UPDATE produits 
                SET quantity = quantity - :quantity 
                WHERE id = :product_id
            ");
            
            $updateStmt->execute([
                ':quantity' => $quantity,
                ':product_id' => $product_id,
            ]);

            $pdo->commit();
            $success = "Sortie de stock enregistrée avec succès.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la sortie : " . $e->getMessage();
        }
    } else {
        $error = "Quantité invalide ou insuffisante en stock.";
    }
}

$products = $pdo->query("SELECT id, name, reference, quantity FROM produits ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sortie Stock - Gestion de Stock Hospitalier</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<nav class="navbar">
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
        <h1>Sortie Stock</h1>

        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="stock_exit.php">
        <div class="form-group">
                <label for="movement_code">Type de sortie : *</label>
                <select name="movement_code" id="movement_code" required>
                    <option value="">-- Sélectionnez le type de sortie --</option>
                    <?php foreach ($exit_types as $code => $description): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>">
                            <?php echo htmlspecialchars($description); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="product_id">Produit :</label>
                <select name="product_id" id="product_id" required>
                    <option value="">-- Sélectionnez un produit --</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name'] . ' - Réf: ' . $product['reference']); ?> (Stock: <?php echo $product['quantity']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="quantity">Quantité :</label>
                <input type="number" name="quantity" id="quantity" min="1" required>
            </div>

            <div class="form-group">
                <label for="reason">Raison (facultatif) :</label>
                <input type="text" name="reason" id="reason">
            </div>

            <div class="form-group">
                <label for="department">Service *</label>
                <input type="text" name="department" id="department">
            </div>

            <button type="submit" class="action-button">Enregistrer</button>
        </form>
    </main>

</body>
</html>

<?php include 'footer.php'; ?>