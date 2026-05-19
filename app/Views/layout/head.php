<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Burndown e Burnup - <?php echo htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <style>
        .chart-wrapper { position: relative; height: 320px; }
        .cursor-pointer { cursor: pointer; }
        tr[aria-expanded="true"] .collapse-icon { transform: rotate(90deg); }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
