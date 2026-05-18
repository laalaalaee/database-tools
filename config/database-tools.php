<?php

return [
    'disk' => env('DATABASE_TOOLS_DISK', 'local'),
    'path' => env('DATABASE_TOOLS_PATH', 'database-backups'),
    'size_cache_seconds' => (int) env('DATABASE_TOOLS_SIZE_CACHE', 60),
    'mysqldump_binary' => env('DATABASE_TOOLS_MYSQLDUMP', 'mysqldump'),
    'mysql_binary' => env('DATABASE_TOOLS_MYSQL', 'mysql'),
    'backup_timeout' => (int) env('DATABASE_TOOLS_BACKUP_TIMEOUT', 300),
    'restore_timeout' => (int) env('DATABASE_TOOLS_RESTORE_TIMEOUT', 300),
    'retention' => (int) env('DATABASE_TOOLS_RETENTION', 10),
];
