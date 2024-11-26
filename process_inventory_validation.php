<?php
// process_inventory_validation.php
session_start();
require_once 'db.php';
require_once 'inventory_functions.php';

// Ensure only managers can validate
if (!$_SESSION['is_manager']) {
    die("Accès non autorisé");
}

$recordId = $_POST['record_id'];
$action = $_POST['validation_action'];
$managerComment = $_POST['manager_comment'] ?? '';

$pdo->beginTransaction();
try {
    if ($action === 'validate') {
        // Update product quantity
        $stmt = $pdo->prepare("
            UPDATE produits p
            JOIN inventory_records ir ON p.id = ir.product_id
            SET 
                p.quantity = ir.actual_quantity,
                ir.status = 'validated',
                ir.manager_comment = :comment,
                ir.validated_by = :manager_id,
                ir.validation_date = NOW()
            WHERE ir.id = :record_id
        ");
        
        $stmt->execute([
            ':record_id' => $recordId,
            ':comment' => $managerComment,
            ':manager_id' => $_SESSION['user_id']
        ]);

        // Log stock adjustment
        $pdo->prepare("
            INSERT INTO stock_movements 
            (product_id, quantity, movement_type, user_id, movement_date, notes)
            SELECT 
                product_id, 
                actual_quantity - theoretical_quantity, 
                'adjustment', 
                :manager_id, 
                NOW(), 
                :comment
            FROM inventory_records
            WHERE id = :record_id
        ")->execute([
            ':record_id' => $recordId,
            ':manager_id' => $_SESSION['user_id'],
            ':comment' => $managerComment
        ]);
    } else {
        // Reject the record
        $stmt = $pdo->prepare("
            UPDATE inventory_records 
            SET 
                status = 'rejected', 
                manager_comment = :comment,
                validated_by = :manager_id,
                validation_date = NOW()
            WHERE id = :record_id
        ");
        $stmt->execute([
            ':record_id' => $recordId,
            ':comment' => $managerComment,
            ':manager_id' => $_SESSION['user_id']
        ]);
    }

    $pdo->commit();
    header('Location: inventory_validation.php?success=1');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: inventory_validation.php?error=' . urlencode($e->getMessage()));
}
exit;