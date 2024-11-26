<?php
session_start();

// Vérification de la connexion
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'db.php';

// Gestion des actions (ajout, modification, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'add') {
                // Ajouter un fournisseur
                $stmt = $pdo->prepare("INSERT INTO fournisseurs (name, contact, address, email, phone) VALUES (:name, :contact, :address, :email, :phone)");
                $stmt->execute([
                    ':name' => htmlspecialchars($_POST['name']),
                    ':contact' => htmlspecialchars($_POST['contact']),
                    ':address' => htmlspecialchars($_POST['address']),
                    ':email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '',
                    ':phone' => htmlspecialchars($_POST['phone']),
                ]);
                $success_message = "Fournisseur ajouté avec succès!";
            } elseif ($_POST['action'] === 'edit') {
                // Modifier un fournisseur
                $stmt = $pdo->prepare("UPDATE fournisseurs SET name = :name, contact = :contact, address = :address, email = :email, phone = :phone WHERE id = :id");
                $stmt->execute([
                    ':name' => htmlspecialchars($_POST['name']),
                    ':contact' => htmlspecialchars($_POST['contact']),
                    ':address' => htmlspecialchars($_POST['address']),
                    ':email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '',
                    ':phone' => htmlspecialchars($_POST['phone']),
                    ':id' => $_POST['id'],
                ]);
                $success_message = "Fournisseur modifié avec succès!";
            } elseif ($_POST['action'] === 'delete') {
                // Supprimer un fournisseur
                $stmt = $pdo->prepare("DELETE FROM fournisseurs WHERE id = :id");
                $stmt->execute([':id' => $_POST['id']]);
                $success_message = "Fournisseur supprimé avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Une erreur est survenue lors de la gestion des fournisseurs. " . $e->getMessage();
    }
}

// Récupération des fournisseurs
try {
    $stmt = $pdo->query("SELECT * FROM fournisseurs ORDER BY name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Une erreur est survenue lors de la récupération des fournisseurs.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fournisseurs</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-hospital"></i>
            <span>Gestion de Stock Hospitalier</span>
        </a>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <h1>Gestion des Fournisseurs</h1>

        <!-- Affichage des messages de succès ou d'erreur -->
        <?php if (isset($success_message)): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout -->
        <div class="form-container">
            <h2>Ajouter un Fournisseur</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" placeholder="Nom du fournisseur" required>
                <input type="text" name="contact" placeholder="Personne de contact">
                <textarea name="address" placeholder="Adresse"></textarea>
                <input type="email" name="email" placeholder="Email" required>
                <input type="tel" name="phone" placeholder="Téléphone" required>
                <button type="submit">Ajouter</button>
            </form>
        </div>

        <!-- Liste des fournisseurs -->
        <div class="suppliers-list">
            <h2>Liste des Fournisseurs</h2>
            <?php if (isset($suppliers) && count($suppliers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Adresse</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['contact']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['address']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
                                        <button type="submit">Modifier</button>
                                    </form>
                                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Confirmer la suppression ?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $supplier['id']; ?>">
                                        <button type="submit">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Aucun fournisseur trouvé.</p>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>

<?php include 'footer.php'; ?>
