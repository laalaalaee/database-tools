<?php

namespace Pelican\DatabaseTools;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Filament\Components\Tables\Columns\BytesColumn;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Server\Resources\Databases\DatabaseResource;
use App\Models\Database;
use App\Services\Databases\DatabaseManagementService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use Pelican\DatabaseTools\Models\DatabaseBackup;
use Pelican\DatabaseTools\Services\DatabaseBackupService;
use Pelican\DatabaseTools\Services\DatabaseSizeService;

class DatabaseToolsPlugin implements Plugin
{
    public function getId(): string
    {
        return 'database-tools';
    }

    public function register(Panel $panel): void
    {
        DatabaseResource::modifyTable(function (Table $table) {
            return $table
                ->columns([
                    TextColumn::make('host')
                        ->label(trans('server/database.host'))
                        ->state(fn (Database $database) => $database->address())
                        ->badge(),
                    TextColumn::make('database')
                        ->label(trans('server/database.database')),
                    BytesColumn::make('size')
                        ->label('Size')
                        ->state(fn (Database $database, DatabaseSizeService $service) => $service->getSize($database)),
                    TextColumn::make('username')
                        ->label(trans('server/database.username')),
                    TextColumn::make('remote')
                        ->label(trans('server/database.remote')),
                    DateTimeColumn::make('created_at')
                        ->label(trans('server/database.created_at'))
                        ->sortable(),
                ])
                ->recordActions([
                    ActionGroup::make([
                        Action::make('database_backup')
                            ->label('Create Backup')
                            ->icon(TablerIcon::DatabaseExport)
                            ->color('primary')
                            ->authorize(fn (Database $database) => user()?->can(SubuserPermission::BackupCreate, $database->server))
                            ->action(function (Database $database, DatabaseBackupService $service) {
                                if (!self::databaseBackupsTableExists()) {
                                    Notification::make()
                                        ->title('Database Tools not installed')
                                        ->body('Run: php artisan migrate --path=plugins/database-tools/database/migrations')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                try {
                                    $service->createBackup($database, user());

                                    Notification::make()
                                        ->title('Backup created')
                                        ->success()
                                        ->send();
                                } catch (Exception $exception) {
                                    Notification::make()
                                        ->title('Backup failed')
                                        ->body($exception->getMessage())
                                        ->danger()
                                        ->send();

                                    report($exception);
                                }
                            }),
                        Action::make('database_backup_download')
                            ->label('Download Backup')
                            ->icon(TablerIcon::Download)
                            ->color('primary')
                            ->authorize(fn (Database $database) => user()?->can(SubuserPermission::BackupDownload, $database->server))
                            ->schema([
                                Select::make('backup_id')
                                    ->label('Backup')
                                    ->options(fn (Database $database) => self::completedBackupOptions($database))
                                    ->required(),
                            ])
                            ->action(function (array $data, Database $database, DatabaseBackupService $service) {
                                if (!self::databaseBackupsTableExists()) {
                                    Notification::make()
                                        ->title('Database Tools not installed')
                                        ->body('Run: php artisan migrate --path=plugins/database-tools/database/migrations')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $backup = $service->getCompletedBackupForDatabase($database, $data['backup_id']);

                                return redirect()->to($service->getDownloadUrl($backup));
                            })
                            ->visible(fn (Database $database) => self::hasCompletedBackups($database)),
                        Action::make('database_backup_restore')
                            ->label('Restore Backup')
                            ->icon(TablerIcon::DatabaseImport)
                            ->color('warning')
                            ->requiresConfirmation()
                            ->authorize(fn (Database $database) => user()?->can(SubuserPermission::BackupRestore, $database->server))
                            ->schema([
                                Select::make('backup_id')
                                    ->label('Backup')
                                    ->options(fn (Database $database) => self::completedBackupOptions($database))
                                    ->required(),
                            ])
                            ->action(function (array $data, Database $database, DatabaseBackupService $service) {
                                if (!self::databaseBackupsTableExists()) {
                                    Notification::make()
                                        ->title('Database Tools not installed')
                                        ->body('Run: php artisan migrate --path=plugins/database-tools/database/migrations')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $backup = $service->getCompletedBackupForDatabase($database, $data['backup_id']);

                                try {
                                    $service->restoreBackup($backup);

                                    Notification::make()
                                        ->title('Database restored')
                                        ->success()
                                        ->send();
                                } catch (Exception $exception) {
                                    Notification::make()
                                        ->title('Restore failed')
                                        ->body($exception->getMessage())
                                        ->danger()
                                        ->send();

                                    report($exception);
                                }
                            })
                            ->visible(fn (Database $database) => self::hasCompletedBackups($database)),
                        Action::make('database_backup_delete')
                            ->label('Delete Backup')
                            ->icon(TablerIcon::Trash)
                            ->color('danger')
                            ->requiresConfirmation()
                            ->authorize(fn (Database $database) => user()?->can(SubuserPermission::BackupDelete, $database->server))
                            ->schema([
                                Select::make('backup_id')
                                    ->label('Backup')
                                    ->options(fn (Database $database) => self::allBackupOptions($database))
                                    ->required(),
                            ])
                            ->action(function (array $data, Database $database, DatabaseBackupService $service) {
                                if (!self::databaseBackupsTableExists()) {
                                    Notification::make()
                                        ->title('Database Tools not installed')
                                        ->body('Run: php artisan migrate --path=plugins/database-tools/database/migrations')
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                $backup = $service->getBackupForDatabase($database, $data['backup_id']);

                                $service->deleteBackup($backup);

                                Notification::make()
                                    ->title('Backup deleted')
                                    ->success()
                                    ->send();
                            })
                            ->visible(fn (Database $database) => self::databaseBackupsTableExists()
                                && DatabaseBackup::query()->where('database_id', $database->id)->exists()),
                    ]),
                    ViewAction::make()
                        ->modalHeading(fn (Database $database) => trans('server/database.viewing', ['database' => $database->database])),
                    DeleteAction::make()
                        ->successNotificationTitle(null)
                        ->using(function (Database $database, DatabaseManagementService $service) {
                            try {
                                $service->delete($database);

                                Notification::make()
                                    ->title(trans('server/database.delete_notification', ['database' => $database->database]))
                                    ->success()
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->title(trans('server/database.delete_notification_fail', ['database' => $database->database]))
                                    ->danger()
                                    ->send();

                                report($exception);
                            }
                        }),
                ])
                ->poll('15s');
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /** @return array<int, string> */
    private static function completedBackupOptions(Database $database): array
    {
        if (!self::databaseBackupsTableExists()) {
            return [];
        }

        return DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get()
            ->mapWithKeys(fn (DatabaseBackup $backup) => [$backup->id => $backup->label()])
            ->all();
    }

    /** @return array<int, string> */
    private static function allBackupOptions(Database $database): array
    {
        if (!self::databaseBackupsTableExists()) {
            return [];
        }

        return DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->orderByDesc('created_at')
            ->get()
            ->mapWithKeys(fn (DatabaseBackup $backup) => [$backup->id => $backup->label()])
            ->all();
    }

    private static function hasCompletedBackups(Database $database): bool
    {
        if (!self::databaseBackupsTableExists()) {
            return false;
        }

        return DatabaseBackup::query()
            ->where('database_id', $database->id)
            ->where('status', 'completed')
            ->exists();
    }

    private static function databaseBackupsTableExists(): bool
    {
        return Schema::hasTable('database_backups');
    }
}
