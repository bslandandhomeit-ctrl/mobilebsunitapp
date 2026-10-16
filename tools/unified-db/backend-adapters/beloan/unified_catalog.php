<?php
return array(
    'enabled' => env('UNIFIED_CATALOG_ENABLED', false),
    'database' => array(
        'driver' => 'mysql', 'host' => env('UNIFIED_DB_HOST', '127.0.0.1'),
        'port' => env('UNIFIED_DB_PORT', '3309'),
        'database' => env('UNIFIED_DB_DATABASE', 'bsunits_beloan_final'),
        'username' => env('UNIFIED_DB_USERNAME', 'unified_beloan_catalog_reader'),
        'password' => env('UNIFIED_DB_PASSWORD', ''),
        'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '', 'strict' => true
    )
);
