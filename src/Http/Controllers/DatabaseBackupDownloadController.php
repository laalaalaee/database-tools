<?php

namespace Pelican\DatabaseTools\Http\Controllers;

use App\Enums\SubuserPermission;
use App\Models\Database;
use App\Models\Server;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Pelican\DatabaseTools\Models\DatabaseBackup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupDownloadController
{
    public function __invoke(Server $server, Database $database, DatabaseBackup $backup): Response|StreamedResponse
    {
        abort_unless($database->server_id === $server->id, Response::HTTP_NOT_FOUND);
        abort_unless($backup->database_id === $database->id, Response::HTTP_NOT_FOUND);
        abort_unless($backup->status === 'completed', Response::HTTP_NOT_FOUND);
        abort_unless(user()?->can(SubuserPermission::BackupDownload, $server), Response::HTTP_FORBIDDEN);

        return Storage::disk($backup->disk)->download($backup->path, $backup->file_name);
    }
}
