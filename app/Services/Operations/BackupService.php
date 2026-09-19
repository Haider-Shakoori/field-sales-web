<?php

namespace App\Services\Operations;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupService
{
    public function create(?string $label = null, bool $includeMedia = true): array
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('Field Sales production backups currently require the MySQL connection.');
        }

        $backupRoot = rtrim((string) config('operations.backup.directory'), DIRECTORY_SEPARATOR);
        $this->ensureDirectory($backupRoot);

        $safeLabel = $label
            ? '-'.Str::slug($label)
            : '';

        $backupName = now('UTC')->format('Ymd_His').$safeLabel.'-'.Str::lower(Str::random(6));
        $backupDirectory = $backupRoot.DIRECTORY_SEPARATOR.$backupName;
        $this->ensureDirectory($backupDirectory);

        try {
            $databaseFile = $this->dumpDatabase($backupDirectory);
            $mediaFile = $includeMedia ? $this->archivePublicStorage($backupDirectory) : null;
            $manifestFile = $this->writeManifest($backupDirectory, $databaseFile, $mediaFile);
            $this->pruneExpiredBackups($backupRoot);

            return [
                'directory' => $backupDirectory,
                'database' => $databaseFile,
                'media' => $mediaFile,
                'manifest' => $manifestFile,
            ];
        } catch (\Throwable $exception) {
            $this->deleteDirectory($backupDirectory);

            throw $exception;
        }
    }

    private function dumpDatabase(string $backupDirectory): string
    {
        $connection = config('database.connections.mysql');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database name and username must be configured before a backup can run.');
        }

        $sqlPath = $backupDirectory.DIRECTORY_SEPARATOR.'database.sql';
        $handle = fopen($sqlPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to create the database backup file.');
        }

        @chmod($sqlPath, 0660);

        $process = new Process([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--triggers',
            '--no-tablespaces',
            '--add-drop-database',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--databases',
            $database,
        ], base_path(), ['MYSQL_PWD' => $password]);

        $process->setTimeout(1800);

        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    $written = fwrite($handle, $buffer);

                    if ($written === false || $written !== strlen($buffer)) {
                        throw new RuntimeException('Unable to write the database backup stream.');
                    }
                }
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            @unlink($sqlPath);

            throw new RuntimeException('mysqldump failed. Check database credentials and server backup tooling.');
        }

        $gzipPath = $sqlPath.'.gz';
        $this->gzipFile($sqlPath, $gzipPath);
        @unlink($sqlPath);

        return $gzipPath;
    }

    private function archivePublicStorage(string $backupDirectory): ?string
    {
        $storageDirectory = storage_path('app/public');

        if (! is_dir($storageDirectory)) {
            return null;
        }

        $archivePath = $backupDirectory.DIRECTORY_SEPARATOR.'public-storage.tar.gz';
        $process = new Process([
            'tar',
            '-czf',
            $archivePath,
            '-C',
            storage_path('app'),
            'public',
        ]);
        $process->setTimeout(1800);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Public storage archive failed. Check tar availability and filesystem permissions.');
        }

        @chmod($archivePath, 0660);

        return $archivePath;
    }

    private function writeManifest(
        string $backupDirectory,
        string $databaseFile,
        ?string $mediaFile,
    ): string {
        $manifestPath = $backupDirectory.DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = [
            'created_at_utc' => now('UTC')->toIso8601String(),
            'application' => (string) config('app.name'),
            'environment' => (string) config('app.env'),
            'release' => $this->releaseIdentifier(),
            'database' => [
                'file' => basename($databaseFile),
                'sha256' => $this->checksum($databaseFile),
            ],
            'public_storage' => $mediaFile ? [
                'file' => basename($mediaFile),
                'sha256' => $this->checksum($mediaFile),
            ] : null,
        ];

        $written = file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
            LOCK_EX,
        );

        if ($written === false) {
            throw new RuntimeException('Unable to write backup manifest.');
        }

        @chmod($manifestPath, 0660);

        return $manifestPath;
    }

    private function releaseIdentifier(): ?string
    {
        $releaseFile = base_path('.release');

        if (is_file($releaseFile)) {
            $release = trim((string) file_get_contents($releaseFile));

            return $release !== '' ? $release : null;
        }

        $release = trim((string) env('FIELD_SALES_RELEASE'));

        return $release !== '' ? $release : null;
    }

    private function pruneExpiredBackups(string $backupRoot): void
    {
        $retentionDays = max(1, (int) config('operations.backup.retention_days', 14));
        $cutoff = now('UTC')->subDays($retentionDays)->getTimestamp();
        $directories = glob($backupRoot.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $modifiedAt = filemtime($directory);

            if ($modifiedAt !== false && $modifiedAt < $cutoff) {
                $this->deleteDirectory($directory);
            }
        }
    }

    private function gzipFile(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');

        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }

            if (is_resource($output)) {
                gzclose($output);
            }

            throw new RuntimeException('Unable to initialize database backup compression.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Unable to read database backup during compression.');
                }

                if ($chunk !== '') {
                    $written = gzwrite($output, $chunk);

                    if ($written === false || $written !== strlen($chunk)) {
                        throw new RuntimeException('Unable to write compressed database backup data.');
                    }
                }
            }
        } finally {
            fclose($input);
            gzclose($output);
        }

        @chmod($destination, 0660);
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate backup checksum.');
        }

        return $checksum;
    }

    private function ensureDirectory(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 02770, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create backup directory.');
        }

        @chmod($path, 02770);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }
}
