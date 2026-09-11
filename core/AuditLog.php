<?php
/**
 * Audit Logger
 * Records system events for accountability and traceability
 */

class AuditLog
{
    /**
     * Log an auditable action
     */
    public static function log(
        string $action,
        string $module = 'system',
        ?string $description = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            // Use session user if not specified
            if ($userId === null && Session::isAuthenticated()) {
                $userId = Session::get('user_id');
            }

            Database::insert('audit_logs', [
                'user_id'     => $userId,
                'action'      => $action,
                'module'      => $module,
                'description' => $description,
                'old_values'  => $oldValues ? json_encode($oldValues) : null,
                'new_values'  => $newValues ? json_encode($newValues) : null,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Exception $e) {
            // Don't break application flow for audit failures
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get audit logs with filtering
     */
    public static function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'a.user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'a.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['module'])) {
            $where[] = 'a.module = ?';
            $params[] = $filters['module'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT a.*, u.username, u.username AS full_name
             FROM audit_logs a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE {$whereClause}
             ORDER BY a.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Count audit logs
     */
    public static function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['module'])) {
            $where[] = 'module = ?';
            $params[] = $filters['module'];
        }

        $whereClause = implode(' AND ', $where);

        return (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM audit_logs WHERE {$whereClause}",
            $params
        );
    }
}
