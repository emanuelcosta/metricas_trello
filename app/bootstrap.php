<?php
declare(strict_types=1);

define('BASE_DIR', dirname(__DIR__));
define('UPLOADS_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads');
define('CONFIG_PATH', BASE_DIR . DIRECTORY_SEPARATOR . 'app_config.json');
define('UPLOADS_INDEX_PATH', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads_index.json');
define('DEFAULT_JSON_PATH', BASE_DIR . DIRECTORY_SEPARATOR . 'dados.json');

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, 4);
    $file = BASE_DIR . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
          . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
