<?php

class AuditLogger
{
    private static ?PDO $pdo = null;

    public static function init(PDO $connection): void
    {
        self::$pdo = $connection;
        self::$pdo->exec(
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_role VARCHAR(20) NULL,
                action VARCHAR(100) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                resource_id VARCHAR(64) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_action (user_id, action),
                INDEX idx_resource (resource_type, resource_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function log(
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?int $userId = null,
        ?string $userRole = null
    ): void {
        if (self::$pdo === null) {
            return;
        }

        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $userRole = $userRole ?? ($_SESSION['user_role'] ?? null);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = self::$pdo->prepare(
            "INSERT INTO audit_logs (user_id, user_role, action, resource_type, resource_id, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $userRole, $action, $resourceType, $resourceId, $ipAddress, $userAgent]);
    }

    public static function getLogs(int $limit = 100, ?string $resourceType = null): array
    {
        if (self::$pdo === null) {
            return [];
        }

        if ($resourceType) {
            $stmt = self::$pdo->prepare(
                "SELECT * FROM audit_logs WHERE resource_type = ? ORDER BY created_at DESC LIMIT ?"
            );
            $stmt->execute([$resourceType, $limit]);
        } else {
            $stmt = self::$pdo->query(
                "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $limit"
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
