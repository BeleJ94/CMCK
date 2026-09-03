<?php

return [
    'host' => getenv('DAGRIL_DB_HOST') !== false ? getenv('DAGRIL_DB_HOST') : '127.0.0.1',
    'port' => getenv('DAGRIL_DB_PORT') !== false ? getenv('DAGRIL_DB_PORT') : '3306',
    'database' => getenv('DAGRIL_DB_DATABASE') !== false ? getenv('DAGRIL_DB_DATABASE') : 'cmck_milltrack',
    'username' => getenv('DAGRIL_DB_USERNAME') !== false ? getenv('DAGRIL_DB_USERNAME') : 'root',
    'password' => getenv('DAGRIL_DB_PASSWORD') !== false ? getenv('DAGRIL_DB_PASSWORD') : '',
    'charset' => 'utf8mb4',
];
