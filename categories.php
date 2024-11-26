<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// Traitement de l'ajout d'une catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description']]);
            $_SESSION['success_message'] = "Catégorie ajoutée avec succès.";
        } elseif ($_POST['action'] === 'edit') {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['category_id']]);
            $_SESSION['success_message'] = "Catégorie mise à jour avec succès.";
        } elseif ($_POST['action'] === 'delete') {
            // Vérifier si la catégorie est utilisée
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE category_id = ?");
            $stmt->execute([$_POST['category_id']]);
            if ($stmt->fetchColumn() > 0) {
                $_SESSION['error_message'] = "Cette catégorie ne peut pas être supprimée car elle est utilisée par des produits.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$_POST['category_id']]);
                $_SESSION['success_message'] = "Catégorie supprimée avec succès.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de l'opération : " . $e->getMessage();
    }
    header('Location: categories.php');
    exit;
}

// Récupération des catégories
try {
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(p.id) as product_count
        FROM categories c
        LEFT JOIN produits p ON c.id = p.category_id
        GROUP BY c.id
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Erreur lors de la récupération des catégories : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Gestion de Stock Hospitalier</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }
        .category-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .category-stats {
            font-size: 0.9rem;
            color: #666;
        }
        .category-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
        }
        .close {
            float: right;
            cursor: pointer;
            font-size: 1.5rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
        <div class="page-header">
            <h2>Gestion des Catégories</h2>
            <button onclick="openModal('add')" class="primary-button">
                <i class="fas fa-plus"></i> Nouvelle Catégorie
            </button>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success">
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error">
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
                <div class="category-card">
                    <div class="category-header">
                        <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                        <span class="category-stats">
                            <i class="fas fa-box"></i> <?php echo $category['product_count']; ?> produits
                        </span>
                    </div>
                    <p><?php echo htmlspecialchars($category['description']); ?></p>
                    <div class="category-actions">
                        <button onclick="openEditModal(<?php 
                            echo htmlspecialchars(json_encode([
                                'id' => $category['id'],
                                'name' => $category['name'],
                                'description' => $category['description']
                            ])); 
                        ?>)" class="action-button secondary">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button onclick="confirmDelete(<?php echo $category['id']; ?>)" 
                                class="action-button danger"
                                <?php echo $category['product_count'] > 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Modal d'ajout/modification -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Nouvelle Catégorie</h2>
            <form id="categoryForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="category_id" id="categoryId">
                
                <div class="form-group">
                    <label for="name">Nom de la catégorie</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="primary-button">Enregistrer</button>
                    <button type="button" onclick="closeModal()" class="secondary-button">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action) {
            document.getElementById('categoryModal').style.display = 'block';
            document.getElementById('formAction').value = action;
            document.getElementById('modalTitle').textContent = 'Nouvelle Catégorie';
            document.getElementById('categoryForm').reset();
        }

        function openEditModal(category) {
            document.getElementById('categoryModal').style.display = 'block';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('modalTitle').textContent = 'Modifier la Catégorie';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('description').value = category.description;
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        function confirmDelete(categoryId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            if (event.target == document.getElementById('categoryModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>

<?php include 'footer.php'; ?>