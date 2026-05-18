<?php

namespace Pelican\DatabaseTools\Models;

use App\Models\Database;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseBackup extends Model
{
    protected $fillable = [
        'database_id',
        'server_id',
        'created_by',
        'name',
        'file_name',
        'disk',
        'path',
        'bytes',
        'checksum',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'int',
        ];
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(Database::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        $size = $this->bytes ? convert_bytes_to_readable($this->bytes) : '0 B';
        $time = $this->created_at ? $this->created_at->format('Y-m-d H:i') : 'Unknown Time';

        return sprintf('%s (%s) - %s', $this->name, $size, $time);
    }
}
