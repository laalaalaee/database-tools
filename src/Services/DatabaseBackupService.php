<?php

namespace Pelican\DatabaseTools\Services;

use App\Models\Database;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pelican\DatabaseTools\Models\DatabaseBackup;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class DatabaseBackupService
{
    public function createBackup(Database $database, ?User $user = null): DatabaseBackup
    {
        $tempDirectory = (new TemporaryDirectory())->create();
        $timestamp = now()->format('Ymd_His');
        $name = sprintf('%s-%s', $database->database, $timestamp);
        $fileName = Str::slug($name) . '.sql';
        $dumpPath = $tempDirectory->path($fileName);
        $disk = (string) config('database-tools.disk', 'local');
        $path = $this->buildStoragePath($database, $fileName);

        $backup = DatabaseBackup::query()->create([
            'database_id' => $database->id,
            'server_id' => $database->server_id,
            'created_by' => $user?->id,
            'name' => $name,
            'file_name' => $fileName,
            'disk' => $disk,
            'path' => $path,
            'status' => 'running',
        ]);

        try {
            $env = [];
            if (!empty($database->host->password)) {
                $env['MYSQL_PWD'] = $database->host->password;
            }

            $command = $this->buildDumpCommand($database, $dumpPath);
            $result = Process::timeout((int) config('database-tools.backup_timeout', 300))
                ->env($env)
                ->run($command);

            if ($result->failed()) {
                throw new Exception($result->errorOutput() ?: 'Database backup failed.');
            }

            $stream = fopen($dumpPath, 'rb');
            if (!$stream) {
                throw new Exception('Backup file could not be read.');
            }

            if (!Storage::disk($disk)->put($path, $stream)) {
                fclose($stream);

                throw new Exception('Backup file could not be stored.');
            }

            fclose($stream);

            $backup->update([
                'bytes' => filesize($dumpPath) ?: 0,
                'checksum' => hash_file('sha256', $dumpPath) ?: null,
                'status' => 'completed',
                'error' => null,
            ]);

            $this->pruneOldBackups($database);

            return $backup;
        } catch (Exception $exception) {
            $backup->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $tempDirectory->delete();
        }
    }

    public function restoreBackup(DatabaseBackup $backup): void
    {
        $tempDirectory = (new TemporaryDirectory())->create();
        $restorePath = $tempDirectory->path($backup->file_name);

        try {
            $stream = Storage::disk($backup->disk)->readStream($backup->path);
            if (!$stream) {
                throw new Exception('Backup file could not be read.');
            }

            $output = fopen($restorePath, 'wb');
            stream_copy_to_stream($stream, $output);
            fclose($output);
            fclose($stream);

            $env = [];
            if (!empty($backup->database->host->password)) {
                $env['MYSQL_PWD'] = $backup->database->host->password;
            }

            $command = $this->buildRestoreCommand($backup->database, $restorePath);
            $result = Process::timeout((int) config('database-tools.restore_timeout', 300))
                ->env($env)
                ->run(['bash', '-c', $command]);

            if ($result->failed()) {
                throw new Exception($result->errorOutput() ?: 'Database restore failed.');
            }
        } finally {
            $tempDirectory->delete();
        }
    }

    public function deleteBackup(DatabaseBackup $backup): void
    {
        Storage::disk($backup->disk)->delete($backup->path);
        $backup->delete();
    }

    public function getBackupForDatabase(Database $database, int|string $backupId): DatabaseBackup
    {
        return DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->whereKey($backupId)
            ->firstOrFail();
    }

    public function getCompletedBackupForDatabase(Database $database, int|string $backupId): DatabaseBackup
    {
        return DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->where('status', 'completed')
            ->whereKey($backupId)
            ->firstOrFail();
    }

    public function getDownloadUrl(DatabaseBackup $backup): string
    {
        $disk = Storage::disk($backup->disk);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($backup->path, now()->addMinutes(10));
            } catch (Exception) {
                //
            }
        }

        return route('database-tools.backups.download', [
            'server' => $backup->server->uuid_short,
            'database' => $backup->database_id,
            'backup' => $backup->id,
        ]);
    }

    private function buildDumpCommand(Database $database, string $outputPath): array
    {
        $host = $database->host;
        
        return [
            (string) config('database-tools.mysqldump_binary', 'mysqldump'),
            '--host=' . $host->host,
            '--port=' . $host->port,
            '--user=' . $host->username,
            '--databases',
            $database->database,
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--result-file=' . $outputPath,
        ];
    }

    private function buildRestoreCommand(Database $database, string $inputPath): string
    {
        $host = $database->host;
        $arguments = [
            '--host=' . $host->host,
            '--port=' . $host->port,
            '--user=' . $host->username,
            $database->database,
        ];

        $binary = escapeshellcmd((string) config('database-tools.mysql_binary', 'mysql'));
        $argumentString = implode(' ', array_map('escapeshellarg', $arguments));

        return sprintf('%s %s < %s', $binary, $argumentString, escapeshellarg($inputPath));
    }

    private function buildStoragePath(Database $database, string $fileName): string
    {
        $base = trim((string) config('database-tools.path', 'database-backups'), '/');

        return sprintf('%s/server-%d/%s/%s', $base, $database->server_id, $database->database, $fileName);
    }

    private function pruneOldBackups(Database $database): void
    {
        $limit = (int) config('database-tools.retention', 0);
        if ($limit <= 0) {
            return;
        }

        $backups = DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->orderByDesc('created_at')
            ->skip($limit)
            ->take(PHP_INT_MAX)
            ->get();

        foreach ($backups as $backup) {
            $this->deleteBackup($backup);
        }
    }
}
