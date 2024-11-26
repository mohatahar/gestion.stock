<?php
// inventory_functions.php

function startInventoryCampaign($name, $userId) {
    global $pdo; // Use global PDO connection
    $stmt = $pdo->prepare("
        INSERT INTO inventory_campaigns 
        (name, start_date, status, created_by) 
        VALUES (:name, CURRENT_DATE, 'in_progress', :user)
    ");
    $stmt->execute([
        ':name' => $name,
        ':user' => $userId
    ]);
    return $pdo->lastInsertId();
}

function recordInventoryItem($campaignId, $productId, $actualQuantity, $userId, $comment = '') {
    global $pdo;
    try {
        // Validate campaign and product exist
        $stmt = $pdo->prepare("SELECT 1 FROM inventory_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid campaign");
        }

        $stmt = $pdo->prepare("SELECT 1 FROM produits WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid product");
        }

        // Existing insert logic with campaign_id added
        $stmt = $pdo->prepare("
            INSERT INTO inventory_records
            (campaign_id, product_id, theoretical_quantity, actual_quantity, 
             difference, user_id, record_date, status, comment)
            VALUES (
                :campaign_id,
                :product_id,
                (SELECT quantity FROM produits WHERE id = :product_id),
                :actual_quantity,
                :actual_quantity - (SELECT quantity FROM produits WHERE id = :product_id),
                :user_id,
                NOW(),
                'pending',
                :comment
            )
        ");

        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':product_id' => $productId,
            ':actual_quantity' => $actualQuantity,
            ':user_id' => $userId,
            ':comment' => $comment
        ]);
    } catch (PDOException $e) {
        error_log("Inventory record error: " . $e->getMessage());
        throw new Exception("Database error occurred");
    }
}

function validateInventoryCampaign($campaignId) {
    global $pdo;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE produits p
            JOIN (
                SELECT product_id, actual_quantity 
                FROM inventory_records 
                WHERE campaign_id = :campaign_id AND status = 'validated'
            ) ir ON p.id = ir.product_id
            SET p.quantity = ir.actual_quantity
        ");
        $stmt->execute([':campaign_id' => $campaignId]);

        $pdo->prepare("
            UPDATE inventory_campaigns 
            SET status = 'completed', end_date = CURRENT_DATE 
            WHERE id = :campaign_id
        ")->execute([':campaign_id' => $campaignId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

