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

    }
}
