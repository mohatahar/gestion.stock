<?php
session_start();
require_once 'db.php';
require_once 'inventory_functions.php';

// Authorization check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Handle inventory item recording
if (isset($_POST['record_inventory'])) {
    $productId = $_POST['product_id'];
    $actualQuantity = $_POST['actual_quantity'];
    
    try {
        recordInventoryItem(
            $_SESSION['current_campaign'], 
            $productId, 
            $actualQuantity, 
            $_SESSION['user_id']
        );
        $_SESSION['last_recorded_product'] = $productId;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Fetch products for inventory
$products = $pdo->query("
    SELECT id, name, reference, quantity, location
    FROM produits
    WHERE is_consumable = 0
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch campaign information if exists
$currentCampaign = null;
if (isset($_SESSION['current_campaign'])) {
    $stmt = $pdo->prepare("SELECT * FROM inventory_campaigns WHERE id = ?");
    $stmt->execute([$_SESSION['current_campaign']]);
    $currentCampaign = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Inventaires</title>
    <link rel="stylesheet" href="style.css">
    <script>
    function confirmRecordInventory(productId) {
        const actualQuantity = document.getElementById('actual_quantity_' + productId).value;
        if (confirm(`Confirmer l'enregistrement de ${actualQuantity} pour ce produit ?`)) {
            document.getElementById('product_id').value = productId;
            document.getElementById('actual_quantity').value = actualQuantity;
            document.getElementById('record_form').submit();
        }
    }
    </script>
</head>
<body>
    <div class="container">
        <h1>Campagne d'Inventaire</h1>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo htmlspecialchars($_SESSION['error_message']); 
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['current_campaign'])): ?>
            <form method="POST" class="start-campaign-form">
                <input type="text" name="campaign_name" placeholder="Nom de la campagne" required>
                <button type="submit" name="start_campaign" class="btn btn-primary">
                    Démarrer Campagne
                </button>
            </form>
        <?php else: ?>
            <div class="campaign-info">
                <h2>Campagne en cours : <?php echo htmlspecialchars($currentCampaign['name']); ?></h2>
                <p>Démarrée le : <?php echo date('d/m/Y H:i', strtotime($currentCampaign['start_date'])); ?></p>
            </div>

            <form id="record_form" method="POST" style="display:none;">
                <input type="hidden" name="record_inventory" value="1">
                <input type="hidden" id="product_id" name="product_id">
                <input type="hidden" id="actual_quantity" name="actual_quantity">
            </form>

            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Référence</th>
                        <th>Emplacement</th>
                        <th>Stock Théorique</th>
                        <th>Stock Physique</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['reference']); ?></td>
                        <td><?php echo htmlspecialchars($product['location'] ?? 'Non spécifié'); ?></td>
                        <td><?php echo $product['quantity']; ?></td>
                        <td>
                            <input type="number" 
                                   id="actual_quantity_<?php echo $product['id']; ?>"
                                   name="actual_quantity_<?php echo $product['id']; ?>" 
                                   min="0" 
                                   value="<?php echo $product['quantity']; ?>"
                                   class="form-control">
                        </td>
                        <td>
                            <button onclick="confirmRecordInventory(<?php echo $product['id']; ?>)" 
                                    class="btn btn-success">
                                Enregistrer
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>