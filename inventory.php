<?php
session_start();
require_once 'db.php';
require_once 'inventory_functions.php';

// Sécurité renforcée : vérification CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestion des erreurs et logging
function logError($message) {
    error_log("Inventory Error: " . $message);
}

// Authorization check avec message plus explicite
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Vous devez être connecté pour accéder à cette page.";
    header('Location: login.php');
    exit;
}

// Validation des données avant traitement
function validateInventoryData($productId, $actualQuantity) {
    if (!is_numeric($productId) || !is_numeric($actualQuantity)) {
        throw new Exception("Données invalides.");
    }
    return true;
}

// Gestion de l'inventaire avec plus de contrôles
if (isset($_POST['record_inventory']) && 
    isset($_POST['csrf_token']) && 
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    try {
        $productId = $_POST['product_id'];
        $actualQuantity = $_POST['actual_quantity'];
        
        validateInventoryData($productId, $actualQuantity);
        
        recordInventoryItem(
            $_SESSION['current_campaign'], 
            $productId, 
            $actualQuantity, 
            $_SESSION['user_id']
        );
        
        $_SESSION['success_message'] = "Inventaire enregistré avec succès.";
        $_SESSION['last_recorded_product'] = $productId;
        
    } catch (Exception $e) {
        logError($e->getMessage());
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Gestion du démarrage de campagne
if (isset($_POST['start_campaign']) && 
    isset($_POST['csrf_token']) && 
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    try {
        // Validation du nom de campagne
        $campaignName = trim($_POST['campaign_name']);
        
        if (empty($campaignName)) {
            throw new Exception("Le nom de la campagne ne peut pas être vide.");
        }

        // Vérifier s'il y a déjà une campagne active
        $stmt = $pdo->prepare("SELECT id FROM inventory_campaigns WHERE status = 'in_progress'");
        $stmt->execute();
        $existingCampaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCampaign) {
            throw new Exception("Une campagne d'inventaire est déjà en cours.");
        }

        // Utiliser la fonction de démarrage de campagne existante
        $campaignId = startInventoryCampaign($campaignName, $_SESSION['user_id']);

        // Stocker l'ID de la campagne en session
        $_SESSION['current_campaign'] = $campaignId;
        $_SESSION['success_message'] = "Campagne d'inventaire démarrée avec succès.";

    } catch (Exception $e) {
        // Log de l'erreur
        error_log("Erreur lors du démarrage de campagne : " . $e->getMessage());

        // Message d'erreur pour l'utilisateur
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Requête préparée pour plus de sécurité
$stmt = $pdo->prepare("
    SELECT id, name, reference, quantity, location
    FROM produits
    WHERE is_consumable = 0
    ORDER BY name
");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de campagne avec transaction
try {
    $pdo->beginTransaction();
    
    $currentCampaign = null;
    if (isset($_SESSION['current_campaign'])) {
        $stmt = $pdo->prepare("SELECT * FROM inventory_campaigns WHERE id = ?");
        $stmt->execute([$_SESSION['current_campaign']]);
        $currentCampaign = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    logError("Erreur lors de la récupération de la campagne : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Inventaires</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validation côté client
        function validateQuantity(input) {
            if (input.value < 0) {
                input.value = 0;
                Swal.fire({
                    icon: 'warning',
                    title: 'Quantité invalide',
                    text: 'La quantité ne peut pas être négative.'
                });
            }
        }

        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                validateQuantity(this);
            });
        });

        function confirmRecordInventory(productId) {
            const actualQuantityInput = document.getElementById('actual_quantity_' + productId);
            const actualQuantity = actualQuantityInput.value;

            Swal.fire({
                title: 'Confirmation',
                text: `Voulez-vous enregistrer ${actualQuantity} pour ce produit ?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('product_id').value = productId;
                    document.getElementById('actual_quantity').value = actualQuantity;
                    document.getElementById('record_form').submit();
                }
            });
        }

        // Attacher l'événement de confirmation
        document.querySelectorAll('.confirm-inventory').forEach(button => {
            button.addEventListener('click', function() {
                confirmRecordInventory(this.dataset.productId);
            });
        });
    });
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

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']); 
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['current_campaign'])): ?>
            <form method="POST" class="start-campaign-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="campaign_name">Nom de la campagne</label>
                    <input type="text" id="campaign_name" name="campaign_name" 
                           placeholder="Nom de la campagne" 
                           required 
                           class="form-control">
                </div>
                <button type="submit" name="start_campaign" class="btn btn-primary mt-3">
                    Démarrer Campagne
                </button>
            </form>
        <?php else: ?>
            <div class="campaign-info card mb-4">
                <div class="card-header">
                    <h2>Campagne en cours : <?php echo htmlspecialchars($currentCampaign['name']); ?></h2>
                </div>
                <div class="card-body">
                    <p>Démarrée le : <?php echo date('d/m/Y H:i', strtotime($currentCampaign['start_date'])); ?></p>
                </div>
            </div>

            <form id="record_form" method="POST" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="record_inventory" value="1">
                <input type="hidden" id="product_id" name="product_id">
                <input type="hidden" id="actual_quantity" name="actual_quantity">
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover inventory-table">
                    <thead class="thead-dark">
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
                                <button data-product-id="<?php echo $product['id']; ?>" 
                                        class="btn btn-success confirm-inventory">
                                    Enregistrer
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>