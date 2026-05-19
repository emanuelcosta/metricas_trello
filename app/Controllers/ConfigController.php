<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Context;
use App\Models\AppConfig;
use App\Models\BoardConfigRepository;
use App\Models\Database;
use App\Models\TrelloBoard;
use App\Models\UploadsIndex;

class ConfigController
{
    public function saveListConfig(): void
    {
        $config = AppConfig::load(CONFIG_PATH);
        if (empty($config)) {
            $config = ['latest_uploaded_file' => 'dados.json', 'updated_at' => gmdate('c')];
        }

        $board = $this->loadBoardFromPost();
        $availableListIds = $board !== null ? $board->getAvailableListIds() : [];
        $currentBoardId   = $board !== null ? $board->id : '';

        $postedPending   = isset($_POST['pending_list_ids']) && is_array($_POST['pending_list_ids']) ? $_POST['pending_list_ids'] : [];
        $postedCompleted = isset($_POST['completed_list_ids']) && is_array($_POST['completed_list_ids']) ? $_POST['completed_list_ids'] : [];
        $postedInProgress = isset($_POST['in_progress_list_ids']) && is_array($_POST['in_progress_list_ids']) ? $_POST['in_progress_list_ids'] : [];

        $newPendingListIds = [];
        foreach ($postedPending as $postedId) {
            $id = (string)$postedId;
            if ($id !== '' && isset($availableListIds[$id])) {
                $newPendingListIds[$id] = true;
            }
        }
        $newPendingListIds = array_keys($newPendingListIds);

        $newCompletedListIds = [];
        foreach ($postedCompleted as $postedId) {
            $id = (string)$postedId;
            if ($id !== '' && isset($availableListIds[$id])) {
                $newCompletedListIds[$id] = true;
            }
        }
        $newCompletedListIds = array_keys($newCompletedListIds);

        $newPendingListIds = array_values(array_filter($newPendingListIds, static function (string $id) use ($newCompletedListIds): bool {
            return !in_array($id, $newCompletedListIds, true);
        }));

        $newInProgressListIds = [];
        foreach ($postedInProgress as $postedId) {
            $id = (string)$postedId;
            if ($id !== '' && isset($availableListIds[$id])) {
                $newInProgressListIds[$id] = true;
            }
        }
        $newInProgressListIds = array_keys($newInProgressListIds);

        $config['demand_lists']['pending_list_ids']   = $newPendingListIds;
        $config['demand_lists']['completed_list_ids'] = $newCompletedListIds;
        $config['demand_lists']['completed_list_id']  = $newCompletedListIds[0] ?? '';
        $config['demand_lists']['in_progress_list_ids'] = $newInProgressListIds;
        $config['demand_lists_board_id'] = $currentBoardId;
        $config['updated_at'] = gmdate('c');

        if (!AppConfig::save(CONFIG_PATH, $config)) {
            Context::set('listConfigError', 'Não foi possível salvar a configuração de listas.');
            return;
        }

        // Persist list config in SQLite when board is imported
        try {
            if ($currentBoardId !== '' && Database::isAvailable()) {
                $repo = new BoardConfigRepository(Database::connect());
                if ($repo->boardExists($currentBoardId)) {
                    $repo->save($currentBoardId, $newPendingListIds, $newCompletedListIds, $newInProgressListIds);
                }
            }
        } catch (\Throwable) {
            // DB write failure is non-fatal; JSON config already saved
        }

        $params = ['config_lists' => 'ok'];
        $this->appendQueryPassthrough($params);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params));
        exit;
    }

    public function refreshListNames(): void
    {
        $config = AppConfig::load(CONFIG_PATH);
        if (empty($config)) {
            $config = ['latest_uploaded_file' => 'dados.json', 'updated_at' => gmdate('c')];
        }

        $board = $this->loadBoardFromPost();
        if ($board !== null) {
            $config['board_lists_index']     = $board->getBoardListsIndex();
            $config['demand_lists_board_id'] = $board->id;
        }
        $config['updated_at'] = gmdate('c');

        if (!AppConfig::save(CONFIG_PATH, $config)) {
            Context::set('updateListNamesError', 'Não foi possível atualizar os nomes das listas.');
            return;
        }

        $params = ['refresh_lists' => 'ok'];
        $this->appendQueryPassthrough($params);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params));
        exit;
    }

    public function deleteFile(): void
    {
        $fileNameRaw  = isset($_POST['file_name']) ? trim((string)$_POST['file_name']) : '';
        $fileNameSafe = basename($fileNameRaw);

        if ($fileNameSafe === '' || $fileNameSafe === 'dados.json') {
            Context::set('deleteError', 'Arquivo inválido para exclusão.');
            return;
        }

        $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $fileNameSafe;
        $relPath    = 'uploads/' . $fileNameSafe;

        if (!is_file($targetPath)) {
            Context::set('deleteError', 'Arquivo não encontrado.');
            return;
        }
        if (!unlink($targetPath)) {
            Context::set('deleteError', 'Não foi possível excluir o arquivo.');
            return;
        }

        $config          = AppConfig::load(CONFIG_PATH);
        $configuredLatest = isset($config['latest_uploaded_file'])
            ? str_replace('\\', '/', (string)$config['latest_uploaded_file'])
            : '';
        if ($configuredLatest === $relPath) {
            $config['latest_uploaded_file'] = 'dados.json';
            $config['updated_at']           = gmdate('c');
            AppConfig::save(CONFIG_PATH, $config);
        }

        $postSourceFile = isset($_POST['source_file']) ? trim((string)$_POST['source_file']) : '';
        $params         = ['deleted' => 'ok'];
        if ($postSourceFile !== '' && $postSourceFile !== $relPath) {
            $params['source_file'] = $postSourceFile;
        }
        $startDate = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
        $endDate   = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
        if ($startDate !== '') { $params['start_date'] = $startDate; }
        if ($endDate !== '')   { $params['end_date']   = $endDate; }

        UploadsIndex::rebuild(UPLOADS_DIR, UPLOADS_INDEX_PATH);

        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($params));
        exit;
    }

    private function loadBoardFromPost(): ?TrelloBoard
    {
        $sourceFile = isset($_POST['source_file']) ? trim((string)$_POST['source_file']) : '';
        if ($sourceFile === '') {
            $config     = AppConfig::load(CONFIG_PATH);
            $sourceFile = isset($config['latest_uploaded_file']) ? (string)$config['latest_uploaded_file'] : 'dados.json';
        }
        $jsonPath = BASE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($sourceFile, '/'));
        if (!is_file($jsonPath)) {
            $jsonPath = DEFAULT_JSON_PATH;
        }
        if (!is_file($jsonPath)) {
            return null;
        }
        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return null;
        }
        $boardData = json_decode($raw, true);
        if (!is_array($boardData)) {
            return null;
        }
        return new TrelloBoard($boardData);
    }

    private function appendQueryPassthrough(array &$params): void
    {
        $postSourceFile = isset($_POST['source_file']) ? trim((string)$_POST['source_file']) : '';
        $postStartDate  = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
        $postEndDate    = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
        if ($postSourceFile !== '') { $params['source_file'] = $postSourceFile; }
        if ($postStartDate !== '')  { $params['start_date']  = $postStartDate; }
        if ($postEndDate !== '')    { $params['end_date']    = $postEndDate; }
    }
}
