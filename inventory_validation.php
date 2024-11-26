<?php
// inventory_validation.php
session_start();
require_once 'db.php';
require_once 'inventory_functions.php';

// Check user permissions (only managers can validate)
if (!$_SESSION['is_manager']) {
    die("Accès non autorisé");
}

// Fetch pending inventory records
$stmt = $pdo->query("
    SELECT 
        ir.id,
        p.name AS product_name,
        ir.theoretical_quantity,
        ir.actual_quantity,
        ir.difference,
        u.username AS recorded_by,
        ir.record_date,
        ir.comment
    FROM inventory_records ir
    JOIN produits p ON ir.product_id = p.id
    JOIN users u ON ir.user_id = u.id
    WHERE ir.status = 'pending'
    ORDER BY ir.record_date
");
$pendingRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Validation d'Inventaire</title>
    <style>
        .difference-negative { color: red; }
        .difference-positive { color: green; }
    </style>
</head>
<body>
    <h1>Validation des Écarts d'Inventaire</h1>
    
    <table>
        <thead>
            <tr>
                <th>Produit</th>
                <th>Stock Théorique</th>
                <th>Stock Physique</th>
                <th>Écart</th>
                <th>Enregistré Par</th>
                <th>Date</th>
                <th>Commentaire</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingRecords as $record): ?>
            <tr>
                <td><?php echo htmlspecialchars($record['product_name']); ?></td>
                <td><?php echo $record['theoretical_quantity']; ?></td>
                <td><?php echo $record['actual_quantity']; ?></td>
                <td class="<?php 
                    echo $record['difference'] < 0 ? 'difference-negative' : 
                         ($record['difference'] > 0 ? 'difference-positive' : '');
                ?>">
                    <?php echo number_format($record['difference'], 2); ?>
                </td>
                <td><?php echo htmlspecialchars($record['recorded_by']); ?></td>
                <td><?php echo $record['record_date']; ?></td>
                <td><?php echo htmlspecialchars($record['comment'] ?: 'Aucun'); ?></td>
                <td>
                    <form method="POST" action="process_inventory_validation.php">
                        <input type="hidden" name="record_id" value="<?php echo $record['id']; ?>">
                        <select name="validation_action">
                            <option value="validate">Valider</option>
                            <option value="reject">Rejeter</option>
                        </select>
                        <textarea name="manager_comment" placeholder="Commentaire du gestionnaire"></textarea>
                        <button type="submit">Soumettre</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>