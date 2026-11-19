<?php

namespace App\Models;

use App\Core\Database;

class ActivityLog
{
    public static function record(
        int $adminId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description
    ): void {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, description, ip_address)
             VALUES (:admin, :action, :type, :entity, :desc, :ip)'
        );
        $stmt->execute([
            'admin' => $adminId > 0 ? $adminId : null,
            'action' => mb_substr($action, 0, 50),
            'type' => mb_substr($entityType, 0, 50),
            'entity' => $entityId,
            'desc' => mb_substr($description, 0, 255),
            'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
    }
}