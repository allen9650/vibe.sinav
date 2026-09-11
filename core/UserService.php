<?php
declare(strict_types=1);

/**
 * PTM Assessment System — User Management Service
 * Superadmin exclusive user management & granular subuser permissions handling.
 */
class UserService
{
    /**
     * Get system permission matrix categorized by module
     */
    public static function getAllPermissions(): array
    {
        return [
            'Assessments' => [
                'assessments.view'    => ['name' => 'View Assessments', 'desc' => 'Can view assessment ledger and examination details'],
                'assessments.create'  => ['name' => 'Create Assessments', 'desc' => 'Can create and configure new assessments'],
                'assessments.edit'    => ['name' => 'Edit Assessments', 'desc' => 'Can edit assessment settings and question builder'],
                'assessments.publish' => ['name' => 'Launch Live Sessions', 'desc' => 'Can start/stop live examination sessions in lab'],
                'assessments.delete'  => ['name' => 'Delete Assessments', 'desc' => 'Can permanently delete assessments (DANGEROUS)'],
            ],
            'Question Bank' => [
                'questions.view'   => ['name' => 'View Questions', 'desc' => 'Can browse global question bank'],
                'questions.create' => ['name' => 'Create Questions', 'desc' => 'Can add single and bulk questions'],
                'questions.edit'   => ['name' => 'Edit Questions', 'desc' => 'Can update existing question text and options'],
                'questions.delete' => ['name' => 'Delete Questions', 'desc' => 'Can permanently remove questions (DANGEROUS)'],
            ],
            'Results & Merit' => [
                'results.view'   => ['name' => 'View Results', 'desc' => 'Can access candidate test scores and answer breakdowns'],
                'results.export' => ['name' => 'Export Results', 'desc' => 'Can export score rosters to CSV'],
                'results.delete' => ['name' => 'Delete / Reset Results', 'desc' => 'Can wipe candidate scores or reset attempts (DANGEROUS)'],
            ],
            'Attendance & Reports' => [
                'attendance.view'   => ['name' => 'View Attendance', 'desc' => 'Can view live workstation attendance roster'],
                'attendance.manage' => ['name' => 'Export / Print Attendance', 'desc' => 'Can download attendance CSV and print sign-off sheet'],
                'reports.view'      => ['name' => 'View Reports Center', 'desc' => 'Can access candidate scorecards and merit ranks'],
                'reports.export'    => ['name' => 'Export Reports', 'desc' => 'Can download performance CSV & Executive Dossiers'],
            ],
            'Live Monitoring' => [
                'monitoring.view' => ['name' => 'Live Lab Proctoring', 'desc' => 'Can monitor workstation screens, telemetry & violations'],
            ],
        ];
    }

    /**
     * Available Roles
     */
    public static function getAvailableRoles(): array
    {
        return [
            'teacher'     => 'Teacher / Examiner',
            'subuser'     => 'Restricted Subuser',
            'admin'       => 'Administrator',
            'super-admin' => 'Super Administrator',
        ];
    }

    /**
     * Default permissions preset for new roles
     */
    public static function getDefaultPermissionsForRole(string $role): array
    {
        switch ($role) {
            case 'super-admin':
                return ['*'];
            case 'admin':
                return [
                    'dashboard.view',
                    'assessments.view', 'assessments.create', 'assessments.edit', 'assessments.delete', 'assessments.publish',
                    'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
                    'results.view', 'results.export',
                    'attendance.view', 'attendance.manage',
                    'reports.view', 'reports.export',
                    'monitoring.view', 'security_events.view',
                ];
            case 'teacher':
                return [
                    'dashboard.view', 'teacher.dashboard',
                    'assessments.view', 'assessments.create', 'assessments.edit', 'assessments.delete', 'assessments.publish',
                    'questions.view', 'questions.create', 'questions.edit', 'questions.delete',
                    'results.view',
                    'attendance.view', 'attendance.manage',
                    'reports.view', 'reports.export',
                    'monitoring.view', 'security_events.view',
                ];
            case 'subuser':
            default:
                return [
                    'dashboard.view',
                    'assessments.view',
                    'questions.view',
                    'results.view',
                    'attendance.view',
                    'reports.view',
                ];
        }
    }

    /**
     * Fetch list of users with campus details and count of granted permissions
     */
    public static function getUsers(array $filters = []): array
    {
        $sql = "SELECT u.id, u.username, u.full_name, u.role, u.campus_id, u.status, u.created_at, u.updated_at,
                       c.name AS campus_name, c.code AS campus_code,
                       (SELECT COUNT(*) FROM user_permissions up WHERE up.user_id = u.id) AS custom_permissions_count
                FROM users u
                LEFT JOIN campuses c ON u.campus_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $s = '%' . trim((string)$filters['search']) . '%';
            $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ?)";
            $params[] = $s;
            $params[] = $s;
        }

        $sql .= " ORDER BY (u.role = 'super-admin') DESC, u.username ASC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Fetch single user with permissions
     */
    public static function getUser(int $id): ?array
    {
        $user = Database::fetch(
            "SELECT u.*, c.name AS campus_name, c.code AS campus_code
             FROM users u
             LEFT JOIN campuses c ON u.campus_id = c.id
             WHERE u.id = ?",
            [$id]
        );

        if (!$user) {
            return null;
        }

        $user['permissions'] = self::getUserPermissions($id);
        return $user;
    }

    /**
     * Get list of permission slugs granted to user
     */
    public static function getUserPermissions(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT permission_slug FROM user_permissions WHERE user_id = ? ORDER BY permission_slug ASC",
            [$userId]
        );
        return array_column($rows, 'permission_slug');
    }

    /**
     * Assign permission slugs to user
     */
    public static function setUserPermissions(int $userId, array $permissions): void
    {
        Database::delete('user_permissions', 'user_id = ?', [$userId]);

        foreach ($permissions as $slug) {
            $slug = trim((string)$slug);
            if ($slug !== '' && $slug !== '*') {
                Database::insert('user_permissions', [
                    'user_id'         => $userId,
                    'permission_slug' => $slug,
                ]);
            }
        }
    }

    /**
     * Create user (Superadmin only)
     */
    public static function createUser(array $data, array $permissions = []): int
    {
        if (class_exists('Auth') && !Auth::isSuperAdmin()) {
            throw new RuntimeException("Permission denied: Only Super Administrators can create users.");
        }

        $username = trim((string)($data['username'] ?? ''));
        $fullName = trim((string)($data['full_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role = trim((string)($data['role'] ?? 'teacher'));
        $campusId = !empty($data['campus_id']) ? (int)$data['campus_id'] : 1;
        $status = in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] : 'active';

        if (strlen($username) < 3) {
            throw new InvalidArgumentException("Username must be at least 3 characters long.");
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) {
            throw new InvalidArgumentException("Username can only contain letters, numbers, hyphens, and underscores.");
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException("Password must be at least 6 characters long.");
        }

        // Check uniqueness
        $existing = Database::fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            throw new InvalidArgumentException("Username '{$username}' is already taken.");
        }

        if ($fullName === '') {
            $fullName = ucfirst($username);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $userId = Database::insert('users', [
            'username'      => $username,
            'full_name'     => $fullName,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'campus_id'     => $campusId,
            'status'        => $status,
        ]);

        // If specific permissions were passed, save them; otherwise save role defaults
        if (empty($permissions) && $role !== 'super-admin') {
            $permissions = self::getDefaultPermissionsForRole($role);
        }

        if ($role !== 'super-admin') {
            self::setUserPermissions($userId, $permissions);
        }

        if (class_exists('AuditLog')) {
            AuditLog::log('user_created', 'users', "Created user '{$username}' with role '{$role}'", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
        }

        return $userId;
    }

    /**
     * Update user (Superadmin only)
     */
    public static function updateUser(int $id, array $data, ?array $permissions = null): bool
    {
        if (class_exists('Auth') && !Auth::isSuperAdmin()) {
            throw new RuntimeException("Permission denied: Only Super Administrators can edit users.");
        }

        $existing = self::getUser($id);
        if (!$existing) {
            throw new InvalidArgumentException("User not found.");
        }

        $username = trim((string)($data['username'] ?? $existing['username']));
        $fullName = trim((string)($data['full_name'] ?? ($existing['full_name'] ?? '')));
        $role = trim((string)($data['role'] ?? $existing['role']));
        $campusId = !empty($data['campus_id']) ? (int)$data['campus_id'] : (int)$existing['campus_id'];
        $statusCandidate = $data['status'] ?? ($existing['status'] ?? 'active');
        $status = in_array($statusCandidate, ['active', 'inactive'], true) ? $statusCandidate : ($existing['status'] ?? 'active');

        if (strlen($username) < 3) {
            throw new InvalidArgumentException("Username must be at least 3 characters long.");
        }

        // Check uniqueness if username changed
        if ($username !== $existing['username']) {
            $conflict = Database::fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
            if ($conflict) {
                throw new InvalidArgumentException("Username '{$username}' is already taken.");
            }
        }

        $updatePayload = [
            'username'  => $username,
            'full_name' => $fullName,
            'role'      => $role,
            'campus_id' => $campusId,
            'status'    => $status,
        ];

        // Optional password change
        if (!empty($data['password'])) {
            $password = (string)$data['password'];
            if (strlen($password) < 6) {
                throw new InvalidArgumentException("New password must be at least 6 characters long.");
            }
            $updatePayload['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        Database::update('users', $updatePayload, 'id = ?', [$id]);

        // Update permissions if provided
        if ($permissions !== null) {
            if ($role === 'super-admin') {
                Database::delete('user_permissions', 'user_id = ?', [$id]);
            } else {
                self::setUserPermissions($id, $permissions);
            }
        }

        if (class_exists('AuditLog')) {
            AuditLog::log('user_updated', 'users', "Updated user '{$username}' (ID #{$id})", class_exists('Auth') ? (Auth::id() ?: 1) : 1);
        }

        return true;
    }

    /**
     * Delete user (Superadmin only)
     */
    public static function deleteUser(int $id): bool
    {
        if (class_exists('Auth') && !Auth::isSuperAdmin()) {
            throw new RuntimeException("Permission denied: Only Super Administrators can delete users.");
        }

        $currentUserId = class_exists('Auth') ? Auth::id() : 0;
        if ($currentUserId === $id) {
            throw new RuntimeException("Security restriction: You cannot delete your own user account.");
        }

        $user = self::getUser($id);
        if (!$user) {
            return false;
        }

        // Verify that this is not the last remaining super-admin
        if ($user['role'] === 'super-admin') {
            $superAdminCount = (int)Database::fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'super-admin'");
            if ($superAdminCount <= 1) {
                throw new RuntimeException("Cannot delete the last remaining Super Administrator in the system.");
            }
        }

        // Determine fallback admin ID for preserving institutional assets
        $fallbackAdminId = $currentUserId ?: ((int)Database::fetchColumn("SELECT id FROM users WHERE role = 'super-admin' AND id != ? LIMIT 1", [$id]) ?: 1);

        // Reassign authored questions and assessments to superadmin so question bank is preserved
        Database::update('questions', ['created_by' => $fallbackAdminId], 'created_by = ?', [$id]);
        Database::update('assessments', ['created_by' => $fallbackAdminId], 'created_by = ?', [$id]);
        Database::update('audit_logs', ['user_id' => $fallbackAdminId], 'user_id = ?', [$id]);
        Database::update('security_events', ['user_id' => $fallbackAdminId], 'user_id = ?', [$id]);

        // Remove user permissions & legacy roles
        Database::delete('user_permissions', 'user_id = ?', [$id]);
        Database::delete('user_roles', 'user_id = ?', [$id]);

        Database::delete('users', 'id = ?', [$id]);

        if (class_exists('AuditLog')) {
            AuditLog::log('user_deleted', 'users', "Deleted user '{$user['username']}' (ID #{$id})", $currentUserId ?: 1);
        }

        return true;
    }
}
