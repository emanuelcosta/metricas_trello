<?php
$viewsDir = BASE_DIR . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR;
$partialsDir = $viewsDir . 'dashboard' . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR;
?>
<?php require $viewsDir . 'layout' . DIRECTORY_SEPARATOR . 'head.php'; ?>
<?php require $partialsDir . 'upload_form.php'; ?>
<?php require $partialsDir . 'files_list.php'; ?>
<?php require $partialsDir . 'list_config.php'; ?>
<?php require $partialsDir . 'date_filter.php'; ?>
<?php require $partialsDir . 'stats_summary.php'; ?>
<?php require $partialsDir . 'charts.php'; ?>
<?php require $partialsDir . 'in_progress_table.php'; ?>
<?php require $partialsDir . 'todo_table.php'; ?>
<?php require $viewsDir . 'layout' . DIRECTORY_SEPARATOR . 'foot.php'; ?>
<?php require $partialsDir . 'charts_js.php'; ?>
