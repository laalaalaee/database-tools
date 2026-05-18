<?php

namespace Pelican\DatabaseTools\Services;

use App\Models\Database;
use Exception;
use Illuminate\Support\Facades\Cache;

class DatabaseSizeService
{
    public function getSize(Database $database): ?int
    {
        $ttl = max(5, (int) config('database-tools.size_cache_seconds', 60));
        $cacheKey = sprintf('database-tools.size.%d', $database->id);

        return Cache::remember($cacheKey, now()->addSeconds($ttl), function () use ($database) {
            try {
                $connection = $database->host->buildConnection('information_schema');
                $result = $connection->selectOne(
                    'SELECT SUM(data_length + index_length) AS size FROM tables WHERE table_schema = ?',
                    [$database->database]
                );

                return (int) ($result->size ?? 0);
            } catch (Exception) {
                return null;
            }
        });
    }
}
