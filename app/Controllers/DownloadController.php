<?php
declare(strict_types=1);

namespace App\Controllers;

class DownloadController
{
    public function handle(): void
    {
        $shortUrl = isset($_GET['short_url']) ? trim((string)$_GET['short_url']) : '';
        if ($shortUrl === '' || !str_starts_with($shortUrl, 'https://trello.com/b/')) {
            http_response_code(400);
            echo 'shortUrl inválida.';
            return;
        }

        $fetchUrl = rtrim($shortUrl, '/') . '.json';
        $context  = stream_context_create([
            'http' => ['timeout' => 30, 'follow_location' => 1],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $data = @file_get_contents($fetchUrl, false, $context);
        if ($data === false) {
            http_response_code(502);
            echo 'Não foi possível buscar o arquivo do Trello.';
            return;
        }

        $slug     = preg_replace('/[^A-Za-z0-9]/', '', substr($shortUrl, strrpos($shortUrl, '/') + 1));
        $filename = 'trello_' . date('Y-m-d') . '_' . $slug . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        echo $data;
    }
}
