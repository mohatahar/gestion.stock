<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

$error = '';
$success = '';

// Validation des inputs
function validateInput($input) {
    return htmlspecialchars(trim($input));
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = validateInput($_POST['name'] ?? '');
    $reference = validateInput($_POST['reference'] ?? '');
    $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $min_quantity = filter_var($_POST['min_quantity'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $max_quantity = filter_var($_POST['max_quantity'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $category_id = $_POST['category_id'] ?? null;
    $location = validateInput($_POST['location'] ?? '');
    $unit = validateInput($_POST['unit'] ?? '');
    $is_consumable = filter_var($_POST['is_consumable'] ?? '');

    // Vérification de l'unicité de la référence
    $checkRef = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE reference = :reference");
    $checkRef->execute([':reference' => $reference]);
    
    if ($checkRef->fetchColumn() > 0) {
        $error = "Cette référence existe déjà.";
    } elseif ($name && $reference && $quantity !== false && $min_quantity !== false && $max_quantity !== false) {
        try {
            $stmt = $pdo->prepare("
            INSERT INTO produits 
            (name, reference, quantity, min_quantity, max_quantity, category_id, location, unit, is_active, is_consumable) 
            VALUES (:name, :reference, :quantity, :min_quantity, :max_quantity, :category_id, :location, :unit, true, :is_consumable)
        ");
            $stmt->execute([
                ':name' => $name,
                ':reference' => $reference,
                ':quantity' => $quantity,
                ':min_quantity' => $min_quantity,
                ':max_quantity' => $max_quantity,
                ':category_id' => $category_id,
                ':location' => $location,
                ':unit' => $unit,
                ':is_consumable' => $is_consumable,
            ]);
            $success = "Produit ajouté avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = "Veuillez vérifier les informations saisies.";
    }
}

// Récupération des catégories
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit - Gestion de Stock Hospitalier</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .error-input { border: 2px solid red; }
        form div { margin-bottom: 15px; }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert.error {
            background-color: #ffdddd;
            color: #ff0000;
        }
        .alert.success {
            background-color: #ddffdd;
            color: #009900;
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
            <span class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="logout-button">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>
    </nav>

    <main class="main-content">
        <h1>Ajouter un Nouveau Produit</h1>
    
        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="add_product.php" method="POST" id="productForm">
            <div>
                <label for="name">Nom du Produit *</label>
                <input type="text" id="name" name="name" required 
                       placeholder="Ex: Flash disque 4 Go"
                       value="<?php echo isset($name) ? $name : ''; ?>">
            </div>
            <div>
                <label for="reference">Référence *</label>
                <input type="text" id="reference" name="reference" required 
                       placeholder="Ex: FDS-10-0001"
                       value="<?php echo isset($reference) ? $reference : ''; ?>">
            </div>
            <div>
    <label for="is_consumable">Type de Produit *</label>
    <select id="is_consumable" name="is_consumable" required>
        <option value="0">Non Consommable</option>
        <option value="1">Consommable</option>
    </select>
</div>
            <div>
                <label for="quantity">Quantité Initiale *</label>
                <input type="number" id="quantity" name="quantity" min="0" required
                       value="<?php echo isset($quantity) ? $quantity : '0'; ?>">
            </div>
            <div>
                <label for="min_quantity">Quantité Minimale *</label>
                <input type="number" id="min_quantity" name="min_quantity" min="0" required
                       value="<?php echo isset($min_quantity) ? $min_quantity : '0'; ?>">
            </div>
            <div>
                <label for="max_quantity">Quantité Maximale *</label>
                <input type="number" id="max_quantity" name="max_quantity" min="0" required
                       value="<?php echo isset($max_quantity) ? $max_quantity : '0'; ?>">
            </div>
            <div>
                <label for="category_id">Catégorie</label>
                <select id="category_id" name="category_id">
                    <option value="">Aucune</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="location">Emplacement</label>
                <input type="text" id="location" name="location" 
                       placeholder="Ex: Armoire 2, Étagère B"
                       value="<?php echo isset($location) ? $location : ''; ?>">
            </div>
            <div>
                <label for="unit">Unité</label>
                <input type="text" id="unit" name="unit" 
                       placeholder="Ex: unités, boîtes, pièces"
                       value="<?php echo isset($unit) ? $unit : ''; ?>">
            </div>
            <button type="submit">Ajouter le Produit</button>
        </form>
    </main>

    <script>
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const requiredFields = ['name', 'reference', 'quantity', 'min_quantity', 'max_quantity'];
        let isValid = true;

        requiredFields.forEach(field => {
            const input = document.getElementById(field);
            if (!input.value.trim()) {
                input.classList.add('error-input');
                isValid = false;
            } else {
                input.classList.remove('error-input');
            }
        });

        if (!isValid) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
        }
    });
    </script>
</body>
</html>

<?php include 'footer.php'; ?>