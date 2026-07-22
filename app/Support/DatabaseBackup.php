<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackup
{
    /**
     * @return array{path: string, disk: string, bytes: int, pruned: int}
     */
    public function run(?string $connection = null): array
    {
        $connectionName = $connection
            ?? config('database_backup.connection')
            ?? config('database.default');

        $connectionName = is_string($connectionName) && $connectionName !== ''
            ? $connectionName
            : (string) config('database.default');

        $config = Config::get("database.connections.{$connectionName}");
        if (! is_array($config)) {
            throw new RuntimeException("Database connection [{$connectionName}] is not configured.");
        }

        $driver = (string) ($config['driver'] ?? '');
        $diskName = (string) config('database_backup.disk', 'local');
        $directory = trim((string) config('database_backup.path', 'backups'), '/');
        $timestamp = now()->format('Y-m-d_His');

        $disk = Storage::disk($diskName);
        if ($directory !== '' && ! $disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        $relativePath = match ($driver) {
            'sqlite' => $this->backupSqlite($config, $disk, $directory, $connectionName, $timestamp),
            'mysql', 'mariadb' => $this->backupMysql($config, $disk, $directory, $connectionName, $timestamp),
            default => throw new RuntimeException("Unsupported database driver for backup: [{$driver}]."),
        };

        $bytes = (int) $disk->size($relativePath);
        $pruned = $this->pruneOldBackups($disk, $directory);

        return [
            'path' => $relativePath,
            'disk' => $diskName,
            'bytes' => $bytes,
            'pruned' => $pruned,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backupSqlite(array $config, Filesystem $disk, string $directory, string $connectionName, string $timestamp): string
    {
        $database = (string) ($config['database'] ?? '');
        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('SQLite in-memory databases cannot be backed up to a file.');
        }

        if (! is_file($database)) {
            throw new RuntimeException("SQLite database file not found: {$database}");
        }

        $filename = $this->filename($connectionName, $timestamp, 'sqlite');
        $relativePath = $this->joinPath($directory, $filename);
        $contents = file_get_contents($database);
        if ($contents === false) {
            throw new RuntimeException("Failed to read SQLite database file: {$database}");
        }

        $disk->put($relativePath, $contents);

        return $relativePath;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function backupMysql(array $config, Filesystem $disk, string $directory, string $connectionName, string $timestamp): string
    {
        $mysqldump = (string) config('database_backup.mysqldump_path', 'mysqldump');
        $database = (string) ($config['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('MySQL database name is empty.');
        }

        $command = [
            $mysqldump,
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            $database,
        ];

        $process = new Process($command);
        $process->setTimeout(600);
        $password = (string) ($config['password'] ?? '');
        if ($password !== '') {
            $process->setEnv(['MYSQL_PWD' => $password]);
        }

        try {
            $process->mustRun();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'mysqldump failed: '.$exception->getMessage(),
                previous: $exception
            );
        }

        $sql = $process->getOutput();
        $useGzip = (bool) config('database_backup.gzip', true);
        $extension = $useGzip ? 'sql.gz' : 'sql';
        $filename = $this->filename($connectionName, $timestamp, $extension);
        $relativePath = $this->joinPath($directory, $filename);

        $payload = $useGzip ? gzencode($sql, 9) : $sql;
        if ($payload === false) {
            throw new RuntimeException('Failed to compress MySQL dump with gzip.');
        }

        $disk->put($relativePath, $payload);

        return $relativePath;
    }

    private function pruneOldBackups(Filesystem $disk, string $directory): int
    {
        $keep = max(1, (int) config('database_backup.keep', 4));
        $files = collect($disk->files($directory))
            ->filter(fn (string $path): bool => (bool) preg_match(
                '/(^|\/)mocchi-backup-.+\.(sqlite|sql|sql\.gz)$/',
                $path
            ))
            ->sortByDesc(fn (string $path): int => $disk->lastModified($path))
            ->values();

        $pruned = 0;
        foreach ($files->slice($keep) as $path) {
            $disk->delete($path);
            $pruned++;
        }

        return $pruned;
    }

    private function filename(string $connectionName, string $timestamp, string $extension): string
    {
        $safeConnection = preg_replace('/[^A-Za-z0-9_-]/', '_', $connectionName) ?: 'default';

        return "mocchi-backup-{$safeConnection}-{$timestamp}.{$extension}";
    }

    private function joinPath(string $directory, string $filename): string
    {
        return $directory === '' ? $filename : $directory.'/'.$filename;
    }
}
