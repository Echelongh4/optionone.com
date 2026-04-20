<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use PDO;

class DatabaseBackupService
{
    private const MAX_RESTORE_SIZE = 20 * 1024 * 1024;

    public function create(string $prefix = 'backup', bool $schemaOnly = false): array
    {
        $directory = $this->ensureBackupDirectory();
        $filename = sprintf(
            '%s-%s%s.sql',
            preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($prefix)) ?: 'backup',
            date('Ymd-His'),
            $schemaOnly ? '-schema' : ''
        );
        $path = $directory . DIRECTORY_SEPARATOR . $filename;
        $database = (string) config('database.database', 'pos_system');
        $pdo = Database::connection();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

        $lines = [
            '-- NovaPOS database backup',
            '-- Generated at ' . date('c'),
            'SET FOREIGN_KEY_CHECKS=0;',
            'USE ' . $this->quotedIdentifier($database) . ';',
        ];

        foreach ($tables as $tableRow) {
            $table = (string) ($tableRow[0] ?? '');
            if ($table === '') {
                continue;
            }

            $createStatement = $pdo->query('SHOW CREATE TABLE ' . $this->quotedIdentifier($table))->fetch(PDO::FETCH_ASSOC);
            $createSql = $createStatement['Create Table'] ?? array_values($createStatement ?: [])[1] ?? null;

            if ($createSql === null) {
                throw new HttpException(500, 'Could not read table structure for ' . $table . '.');
            }

            $lines[] = '';
            $lines[] = '-- Table structure for ' . $this->quotedIdentifier($table);
            $lines[] = 'DROP TABLE IF EXISTS ' . $this->quotedIdentifier($table) . ';';
            $lines[] = $createSql . ';';

            if ($schemaOnly) {
                continue;
            }

            $statement = $pdo->query('SELECT * FROM ' . $this->quotedIdentifier($table));
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $lines[] = $this->insertStatement($table, $row);
            }
        }

        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
            throw new HttpException(500, 'Unable to write the backup file.');
        }

        return $this->backupDetails($path);
    }

    public function list(): array
    {
        $directory = $this->ensureBackupDirectory();
        $files = glob($directory . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $backups = array_map(fn (string $path): array => $this->backupDetails($path), $files);

        usort($backups, static fn (array $left, array $right): int => strcmp($right['modified_at'], $left['modified_at']));

        return $backups;
    }

    public function pathFor(string $filename): string
    {
        $safeFilename = basename(trim($filename));
        if ($safeFilename === '' || $safeFilename !== $filename || !preg_match('/\.(sql|zip)$/i', $safeFilename)) {
            throw new HttpException(404, 'Backup file not found.');
        }

        $backupPath = $this->ensureBackupDirectory() . DIRECTORY_SEPARATOR . $safeFilename;
        $restoreKitPath = $this->ensureRestoreKitDirectory() . DIRECTORY_SEPARATOR . $safeFilename;
        $path = is_file($backupPath) ? $backupPath : $restoreKitPath;

        if (!is_file($path)) {
            throw new HttpException(404, 'Backup file not found.');
        }

        return $path;
    }

    public function prune(int $keepCount = 14): int
    {
        $keepCount = max(1, $keepCount);
        $backups = $this->list();
        $removed = 0;

        foreach (array_slice($backups, $keepCount) as $backup) {
            $path = (string) ($backup['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }

            if (@unlink($path)) {
                $removed++;
            }

            $kitName = pathinfo((string) ($backup['name'] ?? ''), PATHINFO_FILENAME) . '-restore-kit.zip';
            $kitPath = $this->ensureRestoreKitDirectory() . DIRECTORY_SEPARATOR . $kitName;
            if (is_file($kitPath)) {
                @unlink($kitPath);
            }
        }

        return $removed;
    }

    public function createRestoreKit(?string $backupPath = null, ?string $backupName = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new HttpException(500, 'ZipArchive support is required to build an offline restore kit.');
        }

        if ($backupPath === null || trim($backupPath) === '') {
            $latestBackup = $this->list()[0] ?? null;
            if (!is_array($latestBackup)) {
                $latestBackup = $this->create(prefix: 'restore-kit-source');
            }

            $backupPath = (string) ($latestBackup['path'] ?? '');
            $backupName = (string) ($latestBackup['name'] ?? '');
        }

        if ($backupName === null || trim($backupName) === '') {
            $backupName = basename($backupPath);
        }

        if (!is_file($backupPath) || !preg_match('/\.sql$/i', (string) $backupName)) {
            throw new HttpException(500, 'A valid SQL backup file is required to build the restore kit.');
        }

        $directory = $this->ensureRestoreKitDirectory();
        $kitName = pathinfo((string) $backupName, PATHINFO_FILENAME) . '-restore-kit.zip';
        $kitPath = $directory . DIRECTORY_SEPARATOR . $kitName;
        $zip = new \ZipArchive();

        if ($zip->open($kitPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new HttpException(500, 'Unable to create the restore kit archive.');
        }

        $backupSql = (string) @file_get_contents($backupPath);
        if ($backupSql === '') {
            $zip->close();
            @unlink($kitPath);
            throw new HttpException(500, 'The backup file is empty or unreadable.');
        }

        $zip->addFromString('restore.sql', $backupSql);
        $zip->addFromString('RESTORE-INSTRUCTIONS.txt', $this->restoreInstructions($backupName));
        $zip->addFromString('.env.restore.example', "ALLOW_DB_RESTORE=true\nAPP_ENV=production\n");
        $zip->close();

        return $this->backupDetails($kitPath);
    }

    public function restoreFromUpload(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new HttpException(500, 'Select a SQL backup file to restore.');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(500, 'The restore file upload failed.');
        }

        if (($file['size'] ?? 0) > self::MAX_RESTORE_SIZE) {
            throw new HttpException(500, 'Restore files must be 20MB or smaller.');
        }

        $originalName = (string) ($file['name'] ?? '');
        if (!preg_match('/\.sql$/i', $originalName)) {
            throw new HttpException(500, 'Only .sql backup files can be restored.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $sql = @file_get_contents($tmpName);
        if ($sql === false || trim($sql) === '') {
            throw new HttpException(500, 'The uploaded SQL file is empty or unreadable.');
        }

        $preRestoreBackup = $this->create(prefix: 'pre-restore');
        $statements = $this->splitStatements($sql);

        if ($statements === []) {
            throw new HttpException(500, 'No executable SQL statements were found in that file.');
        }

        $pdo = Database::connection();
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        return [
            'backup' => $preRestoreBackup,
            'statement_count' => count($statements),
            'source_name' => $originalName,
        ];
    }

    private function backupDetails(string $path): array
    {
        return [
            'name' => basename($path),
            'path' => $path,
            'size' => (int) (filesize($path) ?: 0),
            'modified_at' => date('Y-m-d H:i:s', (int) (filemtime($path) ?: time())),
        ];
    }

    private function ensureBackupDirectory(): string
    {
        $directory = storage_path('backups');

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new HttpException(500, 'Unable to prepare the backup directory.');
        }

        return $directory;
    }

    private function ensureRestoreKitDirectory(): string
    {
        $directory = $this->ensureBackupDirectory() . DIRECTORY_SEPARATOR . 'restore-kits';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new HttpException(500, 'Unable to prepare the restore kit directory.');
        }

        return $directory;
    }

    private function restoreInstructions(string $backupName): string
    {
        return implode(PHP_EOL, [
            'NovaPOS Offline Restore Kit',
            '===========================',
            '',
            'Included files:',
            '- restore.sql: the SQL snapshot to restore',
            '- .env.restore.example: restore maintenance flag example',
            '',
            'Recommended workflow:',
            '1. Copy this kit to the target environment.',
            '2. Extract the archive contents.',
            '3. In the target app, temporarily enable ALLOW_DB_RESTORE=true.',
            '4. Sign in as an administrator and open Settings > Backups.',
            '5. Upload restore.sql using the restore form.',
            '6. Confirm the pre-restore backup is created automatically.',
            '7. Disable ALLOW_DB_RESTORE again after the maintenance window.',
            '',
            'Source backup: ' . $backupName,
            'Generated at: ' . date('c'),
        ]) . PHP_EOL;
    }

    private function insertStatement(string $table, array $row): string
    {
        $columns = array_map(fn (string $column): string => $this->quotedIdentifier($column), array_keys($row));
        $values = array_map(fn (mixed $value): string => $this->quotedValue($value), array_values($row));

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s);',
            $this->quotedIdentifier($table),
            implode(', ', $columns),
            implode(', ', $values)
        );
    }

    private function quotedIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quotedValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return Database::connection()->quote((string) $value) ?: "''";
    }

    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $lines = preg_split('/\r\n|\n|\r/', $sql) ?: [];
        $filteredLines = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $filteredLines[] = $line;
        }

        $sql = implode("\n", $filteredLines);
        $statements = [];
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $previous = $index > 0 ? $sql[$index - 1] : '';

            if ($char === "'" && !$inDoubleQuote && $previous !== '\\') {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && $previous !== '\\') {
                $inDoubleQuote = !$inDoubleQuote;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
