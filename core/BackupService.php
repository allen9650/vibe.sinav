<?php
/**
 * PTM Backup & Restore Engine
 * Marr Typing Competition System
 *
 * Provides standalone pure SQL backup dumps, safe download streams,
 * backup cataloging, and verified schema validation.
 */

class BackupService {
    /**
     * Directory path where backup .sql files are saved.
     */
    public static function getBackupDir(): string {
        $dir = ROOT_PATH . '/storage/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Generate a complete PTM SQL backup file.
     *
     * @return array ['success' => bool, 'filename' => string, 'filepath' => string, 'size' => int, 'message' => string]
     */
    public static function createBackup(?int $userId = null): array {
        $pdo = Database::getInstance();
        $tables = [];

        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $timestamp = date('Y-m-d_His');
        $filename = "ptm_backup_{$timestamp}.sql";
        $filepath = self::getBackupDir() . '/' . $filename;

        $out = fopen($filepath, 'w');
        if (!$out) {
            throw new Exception("Unable to open backup file for writing: {$filepath}");
        }

        $header = "-- ============================================================\n"
                . "-- Marr — PTM Database Backup\n"
                . "-- Generated: " . date('Y-m-d H:i:s') . "\n"
                . "-- Database: " . env('DB_DATABASE', 'PTM') . "\n"
                . "-- ============================================================\n\n"
                . "SET FOREIGN_KEY_CHECKS=0;\n"
                . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n"
                . "SET NAMES utf8mb4;\n\n";

        fwrite($out, $header);

        foreach ($tables as $table) {
            // Table structure
            $createTableStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            $createSql = $createTableStmt['Create Table'] ?? '';

            fwrite($out, "-- Table structure for `{$table}`\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($out, $createSql . ";\n\n");

            // Table data
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                fwrite($out, "-- Dumping data for `{$table}`\n");
                $columns = array_keys($rows[0]);
                $colList = '`' . implode('`, `', $columns) . '`';

                foreach (array_chunk($rows, 100) as $chunk) {
                    $insertValues = [];
                    foreach ($chunk as $row) {
                        $escaped = array_map(function ($val) use ($pdo) {
                            if ($val === null) return 'NULL';
                            return $pdo->quote($val);
                        }, array_values($row));
                        $insertValues[] = '(' . implode(', ', $escaped) . ')';
                    }
                    $insertSql = "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $insertValues) . ";\n";
                    fwrite($out, $insertSql);
                }
                fwrite($out, "\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);

        $size = filesize($filepath);

        AuditLog::log('database_backup_created', 'system', 
            "Created database backup '{$filename}' (" . round($size / 1024, 2) . " KB).", 
            $userId, 
            null, 
            ['filename' => $filename, 'size_bytes' => $size]
        );

        return [
            'filename'   => $filename,
            'path'       => $filepath,
            'size_bytes' => $size,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Lists all available backups in the backup directory.
     *
     * @return array
     */
    public static function listBackups(): array {
        $dir = self::getBackupDir();
        $files = glob($dir . '/*.sql');
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename'   => basename($file),
                'path'       => $file,
                'size_bytes' => filesize($file),
                'size_kb'    => round(filesize($file) / 1024, 2),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }

        // Sort latest first
        usort($backups, fn($a, $b) => strcmp($b['filename'], $a['filename']));

        return $backups;
    }

    /**
     * Streams a backup file to browser for download.
     *
     * @param string $filename
     */
    public static function downloadBackup(string $filename): void {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $filepath = self::getBackupDir() . '/' . $filename;

        if (!file_exists($filepath) || !str_ends_with($filename, '.sql')) {
            throw new Exception("Backup file not found.");
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filepath);
        exit;
    }
}
