<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Context;
use App\Models\AppConfig;
use App\Models\BoardImporter;
use App\Models\Database;
use App\Models\TrelloBoard;
use App\Models\UploadsIndex;

class UploadController
{
    public function handle(): void
    {
        if (!isset($_FILES['trello_json']) || !is_array($_FILES['trello_json'])) {
            Context::set('uploadError', 'Nenhum arquivo foi enviado.');
            return;
        }
        if (!isset($_FILES['trello_json']['error']) || $_FILES['trello_json']['error'] !== UPLOAD_ERR_OK) {
            Context::set('uploadError', 'Falha no upload do arquivo.');
            return;
        }

        $originalName = isset($_FILES['trello_json']['name']) ? (string)$_FILES['trello_json']['name'] : 'trello.json';
        $tmpName      = isset($_FILES['trello_json']['tmp_name']) ? (string)$_FILES['trello_json']['tmp_name'] : '';
        $extension    = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'json') {
            Context::set('uploadError', 'Envie um arquivo com extensão .json.');
            return;
        }
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Context::set('uploadError', 'Arquivo temporário de upload inválido.');
            return;
        }

        $rawUploaded = file_get_contents($tmpName);
        if ($rawUploaded === false || !is_array(json_decode($rawUploaded, true))) {
            Context::set('uploadError', 'O arquivo enviado não contém um JSON válido.');
            return;
        }

        $uploadsDir = UPLOADS_DIR;
        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0777, true) && !is_dir($uploadsDir)) {
            Context::set('uploadError', 'Não foi possível criar a pasta de uploads.');
            return;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        if (!is_string($safeName) || $safeName === '') {
            $safeName = 'trello';
        }

        $timestamp      = date('Y-m-d_H-i-s');
        $targetFileName = 'trello_' . $timestamp . '_' . $safeName . '.json';
        $targetPath     = $uploadsDir . DIRECTORY_SEPARATOR . $targetFileName;
        $suffix         = 1;
        while (is_file($targetPath)) {
            $targetFileName = 'trello_' . $timestamp . '_' . $safeName . '_' . $suffix . '.json';
            $targetPath     = $uploadsDir . DIRECTORY_SEPARATOR . $targetFileName;
            $suffix++;
        }

        if (!move_uploaded_file($tmpName, $targetPath)) {
            Context::set('uploadError', 'Não foi possível salvar o arquivo enviado.');
            return;
        }

        $config = AppConfig::load(CONFIG_PATH);
        if (empty($config)) {
            $config = ['latest_uploaded_file' => 'dados.json', 'updated_at' => gmdate('c')];
        }
        $config['latest_uploaded_file'] = 'uploads/' . $targetFileName;
        $config['updated_at']           = gmdate('c');

        if (!AppConfig::save(CONFIG_PATH, $config)) {
            Context::set('uploadError', 'Arquivo enviado, mas não foi possível atualizar app_config.json.');
            return;
        }
        if (!UploadsIndex::rebuild($uploadsDir, UPLOADS_INDEX_PATH)) {
            Context::set('uploadError', 'Arquivo enviado, mas não foi possível atualizar uploads_index.json.');
            return;
        }

        // Import JSON data into SQLite (silent — never blocks upload on DB error)
        try {
            if (Database::isAvailable()) {
                $raw = file_get_contents($targetPath);
                if ($raw !== false) {
                    $boardData = json_decode($raw, true);
                    if (is_array($boardData)) {
                        $board = new TrelloBoard($boardData);
                        (new BoardImporter(Database::connect()))->import($board, 'uploads/' . $targetFileName);
                    }
                }
            }
        } catch (\Throwable) {
            // DB import failure is non-fatal
        }

        $params    = ['upload' => 'ok'];
        $startDate = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
        $endDate   = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
        if ($startDate !== '') { $params['start_date'] = $startDate; }
        if ($endDate !== '')   { $params['end_date']   = $endDate; }

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params));
        exit;
    }
}
