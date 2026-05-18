<?php

namespace Pelican\DatabaseTools\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pelican\DatabaseTools\Http\Controllers\DatabaseBackupDownloadController;

class DatabaseToolsPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth.session'])
            ->prefix('server/{server:uuid_short}')
            ->name('database-tools.')
            ->group(function () {
                Route::get('/databases/{database}/database-backups/{backup}/download', DatabaseBackupDownloadController::class)
                    ->name('backups.download');
            });

        if (class_exists(\App\Models\Database::class)) {
            \App\Models\Database::deleting(function (\App\Models\Database $database) {
                $disk = (string) config('database-tools.disk', 'local');
                $base = trim((string) config('database-tools.path', 'database-backups'), '/');
                $directory = sprintf('%s/server-%d/%s', $base, $database->server_id, $database->database);
                \Illuminate\Support\Facades\Storage::disk($disk)->deleteDirectory($directory);

                if (\Illuminate\Support\Facades\Schema::hasTable('database_backups')) {
                    \Pelican\DatabaseTools\Models\DatabaseBackup::query()->where('database_id', $database->id)->delete();
                }
            });
        }

        if (class_exists(\App\Models\Server::class)) {
            \App\Models\Server::deleting(function (\App\Models\Server $server) {
                $base = trim((string) config('database-tools.path', 'database-backups'), '/');
                $directory = sprintf('%s/server-%d', $base, $server->id);
                \Illuminate\Support\Facades\Storage::disk((string) config('database-tools.disk', 'local'))->deleteDirectory($directory);
            });
        }
    }
}
