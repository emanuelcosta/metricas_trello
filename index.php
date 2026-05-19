<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\ConfigController;
use App\Controllers\DashboardController;
use App\Controllers\DownloadController;
use App\Controllers\UploadController;

$getAction  = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$postAction = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$method     = $_SERVER['REQUEST_METHOD'];

if ($getAction === 'download_trello') {
    (new DownloadController())->handle();
    exit;
}

if ($method === 'POST') {
    match ($postAction) {
        'save_list_config'   => (new ConfigController())->saveListConfig(),
        'refresh_list_names' => (new ConfigController())->refreshListNames(),
        'delete_file'        => (new ConfigController())->deleteFile(),
        default              => (new UploadController())->handle(),
    };
    // Controllers redirect on success; fall through on error to render with context
}

(new DashboardController())->handle();
