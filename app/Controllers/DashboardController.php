<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Context;
use App\Models\AppConfig;
use App\Models\BoardConfigRepository;
use App\Models\Database;
use App\Models\DemandCalculator;
use App\Models\TrelloBoard;
use App\Models\UploadsIndex;
use App\Models\VelocityCalculator;

class DashboardController
{
    public function handle(): void
    {
        // ── Request params ───────────────────────────────────────────────────
        $filterStartInput   = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
        $filterEndInput     = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
        $sourceFileInput    = isset($_GET['source_file']) ? trim((string)$_GET['source_file']) : '';
        $hasQuickTodoParam  = array_key_exists('todo_list_id', $_GET);
        $hasQuickDoneParam  = array_key_exists('done_list_id', $_GET);
        $quickTodoListInput = $hasQuickTodoParam ? trim((string)$_GET['todo_list_id']) : '';
        $quickDoneListInput = $hasQuickDoneParam ? trim((string)$_GET['done_list_id']) : '';

        $uploadSuccess       = isset($_GET['upload']) && $_GET['upload'] === 'ok';
        $deleteSuccess       = isset($_GET['deleted']) && $_GET['deleted'] === 'ok';
        $listConfigSuccess   = isset($_GET['config_lists']) && $_GET['config_lists'] === 'ok';
        $updateListNamesSuccess = isset($_GET['refresh_lists']) && $_GET['refresh_lists'] === 'ok';

        $uploadError          = Context::get('uploadError');
        $listConfigError      = Context::get('listConfigError');
        $deleteError          = Context::get('deleteError');
        $updateListNamesError = Context::get('updateListNamesError');
        $sourceError          = null;

        // ── Config ──────────────────────────────────────────────────────────
        $config = AppConfig::load(CONFIG_PATH);
        if (empty($config)) {
            $config = ['latest_uploaded_file' => 'dados.json', 'updated_at' => gmdate('c')];
            AppConfig::save(CONFIG_PATH, $config);
        }

        // ── Available files ──────────────────────────────────────────────────
        UploadsIndex::rebuild(UPLOADS_DIR, UPLOADS_INDEX_PATH);
        $availableFiles = UploadsIndex::getAvailableFiles(UPLOADS_DIR, UPLOADS_INDEX_PATH, DEFAULT_JSON_PATH);

        // ── File selection ───────────────────────────────────────────────────
        $configuredRel  = isset($config['latest_uploaded_file']) ? (string)$config['latest_uploaded_file'] : 'dados.json';
        $configuredRel  = str_replace('\\', '/', $configuredRel);
        $selectedFileRel = $configuredRel;

        if ($sourceFileInput !== '') {
            $sourceFileInput = str_replace('\\', '/', $sourceFileInput);
            if (isset($availableFiles[$sourceFileInput])) {
                $selectedFileRel = $sourceFileInput;
            } else {
                $sourceError = 'O arquivo selecionado não existe na aplicação.';
            }
        }

        if (!isset($availableFiles[$selectedFileRel])) {
            $selectedFileRel = isset($availableFiles['dados.json']) ? 'dados.json' : '';
            if ($selectedFileRel === '' && !empty($availableFiles)) {
                $selectedFileRel = array_key_first($availableFiles);
            }
        }

        if ($selectedFileRel !== '' && $selectedFileRel !== $configuredRel) {
            $config['latest_uploaded_file'] = $selectedFileRel;
            $config['updated_at']           = gmdate('c');
            AppConfig::save(CONFIG_PATH, $config);
        }

        // ── Load JSON ────────────────────────────────────────────────────────
        $jsonPath = BASE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($selectedFileRel, '/'));
        if (!is_file($jsonPath)) {
            $jsonPath = DEFAULT_JSON_PATH;
        }
        if (!is_file($jsonPath)) {
            http_response_code(500);
            echo 'Nenhum arquivo JSON encontrado para gerar os gráficos.';
            return;
        }

        $rawJson = file_get_contents($jsonPath);
        if ($rawJson === false) {
            http_response_code(500);
            echo 'Não foi possível ler o arquivo JSON selecionado.';
            return;
        }

        $boardData = json_decode($rawJson, true);
        if (!is_array($boardData)) {
            http_response_code(500);
            echo 'JSON inválido no arquivo selecionado.';
            return;
        }

        $board = new TrelloBoard($boardData);

        // ── Date filter validation ────────────────────────────────────────────
        $filterError = null;
        if ($filterStartInput !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterStartInput)) {
            $filterError = 'Data inicial inválida.';
        }
        if ($filterError === null && $filterEndInput !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterEndInput)) {
            $filterError = 'Data final inválida.';
        }
        if ($filterError === null && $filterStartInput !== '' && $filterEndInput !== '' && $filterStartInput > $filterEndInput) {
            $filterError = 'A data inicial deve ser menor ou igual à data final.';
        }
        $hasDateFilter = $filterError === null && ($filterStartInput !== '' || $filterEndInput !== '');

        // ── List configuration ────────────────────────────────────────────────
        $availableListIds  = $board->getAvailableListIds();
        $boardListsIndex   = $board->getBoardListsIndex();
        $autoCompletedListId = $board->getAutoCompletedListId();
        $currentBoardId    = $board->id;

        $configChanged = false;
        if (!isset($config['board_lists_index']) || !is_array($config['board_lists_index']) || $config['board_lists_index'] !== $boardListsIndex) {
            $config['board_lists_index'] = $boardListsIndex;
            $configChanged = true;
        }
        if (!isset($config['demand_lists']) || !is_array($config['demand_lists'])) {
            $config['demand_lists'] = ['pending_list_ids' => [], 'completed_list_ids' => [], 'in_progress_list_ids' => []];
            $configChanged = true;
        }
        if (!isset($config['demand_fields']) || !is_array($config['demand_fields'])) {
            $config['demand_fields'] = ['todo_list_id' => '', 'done_list_id' => '', 'board_id' => ''];
            $configChanged = true;
        }

        // Overlay list config from SQLite when this board has been imported
        try {
            if ($currentBoardId !== '' && Database::isAvailable()) {
                $repo = new BoardConfigRepository(Database::connect());
                if ($repo->boardExists($currentBoardId)) {
                    $dbListConfig = $repo->load($currentBoardId);
                    $config['demand_lists']['pending_list_ids']     = $dbListConfig['pending_list_ids'];
                    $config['demand_lists']['completed_list_ids']   = $dbListConfig['completed_list_ids'];
                    $config['demand_lists']['in_progress_list_ids'] = $dbListConfig['in_progress_list_ids'];
                    $config['demand_lists_board_id'] = $currentBoardId;
                }
            }
        } catch (\Throwable) {
            // Fall back to JSON config silently
        }

        $configuredListsBoardId     = isset($config['demand_lists_board_id']) ? (string)$config['demand_lists_board_id'] : '';
        $isDifferentBoard           = $currentBoardId !== '' && $configuredListsBoardId !== $currentBoardId;
        $quickConfiguredBoardId     = isset($config['demand_fields']['board_id']) ? (string)$config['demand_fields']['board_id'] : '';
        $quickConfiguredTodoListId  = isset($config['demand_fields']['todo_list_id']) ? (string)$config['demand_fields']['todo_list_id'] : '';
        $quickConfiguredDoneListId  = isset($config['demand_fields']['done_list_id']) ? (string)$config['demand_fields']['done_list_id'] : '';

        if ($quickConfiguredTodoListId !== '' && !isset($availableListIds[$quickConfiguredTodoListId])) {
            $quickConfiguredTodoListId = '';
        }
        if ($quickConfiguredDoneListId !== '' && !isset($availableListIds[$quickConfiguredDoneListId])) {
            $quickConfiguredDoneListId = '';
        }

        $configuredPendingListIds = isset($config['demand_lists']['pending_list_ids']) && is_array($config['demand_lists']['pending_list_ids'])
            ? array_values(array_filter(array_map('strval', $config['demand_lists']['pending_list_ids']), static function (string $id) use ($availableListIds): bool {
                return isset($availableListIds[$id]);
            }))
            : [];

        $configuredCompletedListIds = [];
        if (isset($config['demand_lists']['completed_list_ids']) && is_array($config['demand_lists']['completed_list_ids'])) {
            foreach ($config['demand_lists']['completed_list_ids'] as $id) {
                $id = (string)$id;
                if ($id !== '' && isset($availableListIds[$id])) {
                    $configuredCompletedListIds[$id] = true;
                }
            }
        } elseif (isset($config['demand_lists']['completed_list_id'])) {
            $singleId = (string)$config['demand_lists']['completed_list_id'];
            if ($singleId !== '' && isset($availableListIds[$singleId])) {
                $configuredCompletedListIds[$singleId] = true;
            }
        }
        $configuredCompletedListIds = array_keys($configuredCompletedListIds);

        $configuredInProgressListIds = isset($config['demand_lists']['in_progress_list_ids']) && is_array($config['demand_lists']['in_progress_list_ids'])
            ? array_values(array_filter(array_map('strval', $config['demand_lists']['in_progress_list_ids']), static function (string $id) use ($availableListIds): bool {
                return isset($availableListIds[$id]);
            }))
            : [];

        if ($isDifferentBoard) {
            $configuredCompletedListIds  = $autoCompletedListId !== '' ? [$autoCompletedListId] : [];
            $configuredPendingListIds    = [];
            foreach ($availableListIds as $listId => $_) {
                if (!in_array($listId, $configuredCompletedListIds, true)) {
                    $configuredPendingListIds[] = $listId;
                }
            }
            $configuredInProgressListIds = $configuredPendingListIds;
            $configChanged = true;
        } else {
            if (empty($configuredCompletedListIds) && $autoCompletedListId !== '') {
                $configuredCompletedListIds = [$autoCompletedListId];
                $configChanged = true;
            }
            if (empty($configuredPendingListIds) && !empty($availableListIds)) {
                foreach ($availableListIds as $listId => $_) {
                    if (!in_array($listId, $configuredCompletedListIds, true)) {
                        $configuredPendingListIds[] = $listId;
                    }
                }
                $configChanged = true;
            }
            if (empty($configuredInProgressListIds) && !empty($configuredPendingListIds)) {
                $configuredInProgressListIds = $configuredPendingListIds;
                $configChanged = true;
            }
        }

        // ── Quick list params (GET) ───────────────────────────────────────────
        if ($hasQuickTodoParam || $hasQuickDoneParam) {
            $newTodoListId = ($quickTodoListInput !== '' && isset($availableListIds[$quickTodoListInput])) ? $quickTodoListInput : '';
            $newDoneListId = ($quickDoneListInput !== '' && isset($availableListIds[$quickDoneListInput])) ? $quickDoneListInput : '';
            if ($newTodoListId !== '' && $newTodoListId === $newDoneListId) {
                $listConfigError = 'A lista "A fazer" deve ser diferente da lista "Concluídas".';
            } else {
                $quickConfiguredTodoListId = $newTodoListId;
                $quickConfiguredDoneListId = $newDoneListId;
                $quickConfiguredBoardId    = $currentBoardId;
                $config['demand_fields']['todo_list_id'] = $quickConfiguredTodoListId;
                $config['demand_fields']['done_list_id'] = $quickConfiguredDoneListId;
                $config['demand_fields']['board_id']     = $quickConfiguredBoardId;
                $config['updated_at'] = gmdate('c');
                if (!AppConfig::save(CONFIG_PATH, $config)) {
                    $listConfigError = 'Não foi possível salvar a configuração rápida de listas.';
                } else {
                    $listConfigSuccess = true;
                }
            }
        }

        if ($configChanged) {
            $config['demand_lists']['pending_list_ids']   = $configuredPendingListIds;
            $config['demand_lists']['completed_list_ids'] = $configuredCompletedListIds;
            $config['demand_lists']['completed_list_id']  = $configuredCompletedListIds[0] ?? '';
            $config['demand_lists']['in_progress_list_ids'] = $configuredInProgressListIds;
            $config['demand_lists_board_id'] = $currentBoardId;
            if ($quickConfiguredBoardId === '' || $quickConfiguredBoardId !== $currentBoardId) {
                $quickConfiguredBoardId    = $currentBoardId;
                $quickConfiguredDoneListId = $configuredCompletedListIds[0] ?? '';
                $quickConfiguredTodoListId = $configuredPendingListIds[0] ?? '';
            }
            $config['demand_fields']['todo_list_id'] = $quickConfiguredTodoListId;
            $config['demand_fields']['done_list_id'] = $quickConfiguredDoneListId;
            $config['demand_fields']['board_id']     = $quickConfiguredBoardId;
            $config['updated_at'] = gmdate('c');
            AppConfig::save(CONFIG_PATH, $config);
        }

        $activePendingListIds   = $configuredPendingListIds;
        $activeCompletedListIds = $configuredCompletedListIds;
        if ($quickConfiguredBoardId === $currentBoardId) {
            if ($quickConfiguredTodoListId !== '') {
                $activePendingListIds = [$quickConfiguredTodoListId];
            }
            if ($quickConfiguredDoneListId !== '') {
                $activeCompletedListIds = [$quickConfiguredDoneListId];
            }
        }

        // ── Demand calculation ────────────────────────────────────────────────
        $calc = new DemandCalculator($board, $activePendingListIds, $activeCompletedListIds, $configuredPendingListIds, $configuredInProgressListIds);
        if ($hasDateFilter) {
            $calc->applyDateFilter($filterStartInput, $filterEndInput);
        }

        $displayTotalDemands     = $hasDateFilter ? $calc->periodTotalDemands     : $calc->totalDemands;
        $displayCompletedDemands = $hasDateFilter ? $calc->periodCompletedDemands : $calc->completedDemands;
        // Open demands: cartões sem checklist (=1) + itens incompletos de checklists
        // em listas pendentes ∪ em andamento (não afetado pelo filtro de datas)
        $openDemands = $calc->openDemandsCount;
        $projectProgress         = $displayTotalDemands > 0
            ? round(($displayCompletedDemands / $displayTotalDemands) * 100, 2) . '%'
            : '0%';

        // ── Velocity / forecast ───────────────────────────────────────────────
        $velocity = !$hasDateFilter ? new VelocityCalculator($calc, $board) : null;

        // ── In-progress cards ─────────────────────────────────────────────────
        $inProgressListIdsMap = [];
        foreach ($configuredInProgressListIds as $id) {
            $inProgressListIdsMap[$id] = true;
        }

        $listNamesById = [];
        foreach ($boardListsIndex as $listMeta) {
            if (isset($listMeta['id'], $listMeta['name'])) {
                $listNamesById[$listMeta['id']] = $listMeta['name'];
            }
        }

        $checklistsGroupedByCard = [];
        foreach ($board->checklists as $checklist) {
            $clCardId = isset($checklist['idCard']) ? (string)$checklist['idCard'] : '';
            if ($clCardId === '') {
                continue;
            }
            $clItems = [];
            foreach (isset($checklist['checkItems']) && is_array($checklist['checkItems']) ? $checklist['checkItems'] : [] as $clItem) {
                $clItems[] = [
                    'id'    => isset($clItem['id']) ? (string)$clItem['id'] : '',
                    'name'  => isset($clItem['name']) ? trim((string)$clItem['name']) : '',
                    'state' => isset($clItem['state']) ? (string)$clItem['state'] : 'incomplete',
                ];
            }
            $checklistsGroupedByCard[$clCardId][] = [
                'name'  => isset($checklist['name']) ? trim((string)$checklist['name']) : '',
                'items' => $clItems,
            ];
        }

        $checkItemsByCard     = $calc->getCheckItemsByCard();
        $checkItemsDataByCard = $calc->getCheckItemsDataByCard();

        $inProgressCards = [];
        foreach ($board->cards as $card) {
            $cardId            = isset($card['id']) ? (string)$card['id'] : '';
            $currentCardListId = isset($card['idList']) ? (string)$card['idList'] : '';
            if ($cardId === '' || $currentCardListId === '' || !isset($inProgressListIdsMap[$currentCardListId])) {
                continue;
            }
            $checkTotal = $checkItemsByCard[$cardId] ?? 0;
            $checkDone  = 0;
            if ($checkTotal > 0 && isset($checkItemsDataByCard[$cardId])) {
                foreach ($checkItemsDataByCard[$cardId] as $item) {
                    if (isset($item['state']) && $item['state'] === 'complete') {
                        $checkDone++;
                    }
                }
            }
            $inProgressCards[] = [
                'id'            => $cardId,
                'name'          => isset($card['name']) ? trim((string)$card['name']) : '',
                'list_name'     => $listNamesById[$currentCardListId] ?? $currentCardListId,
                'short_link'    => isset($card['shortLink']) ? trim((string)$card['shortLink']) : '',
                'last_activity' => isset($card['dateLastActivity']) ? (string)$card['dateLastActivity'] : '',
                'check_total'   => $checkTotal,
                'check_done'    => $checkDone,
                'check_lists'   => $checklistsGroupedByCard[$cardId] ?? [],
            ];
        }

        usort($inProgressCards, static function (array $a, array $b): int {
            $ta = $a['last_activity'] !== '' ? strtotime($a['last_activity']) : 0;
            $tb = $b['last_activity'] !== '' ? strtotime($b['last_activity']) : 0;
            return $tb <=> $ta;
        });

        // ── To-do cards (pending mas não em andamento) ────────────────────────
        $toDoListIds = [];
        foreach ($configuredPendingListIds as $id) {
            if (!isset($inProgressListIdsMap[$id])) {
                $toDoListIds[$id] = true;
            }
        }

        $toDoCards = [];
        foreach ($board->cards as $card) {
            $cardId            = isset($card['id']) ? (string)$card['id'] : '';
            $currentCardListId = isset($card['idList']) ? (string)$card['idList'] : '';
            if ($cardId === '' || $currentCardListId === '' || !isset($toDoListIds[$currentCardListId])) {
                continue;
            }
            $checkTotal = $checkItemsByCard[$cardId] ?? 0;
            $checkDone  = 0;
            if ($checkTotal > 0 && isset($checkItemsDataByCard[$cardId])) {
                foreach ($checkItemsDataByCard[$cardId] as $item) {
                    if (isset($item['state']) && $item['state'] === 'complete') {
                        $checkDone++;
                    }
                }
            }
            $toDoCards[] = [
                'id'            => $cardId,
                'name'          => isset($card['name']) ? trim((string)$card['name']) : '',
                'list_name'     => $listNamesById[$currentCardListId] ?? $currentCardListId,
                'short_link'    => isset($card['shortLink']) ? trim((string)$card['shortLink']) : '',
                'last_activity' => isset($card['dateLastActivity']) ? (string)$card['dateLastActivity'] : '',
                'check_total'   => $checkTotal,
                'check_done'    => $checkDone,
                'check_lists'   => $checklistsGroupedByCard[$cardId] ?? [],
            ];
        }

        usort($toDoCards, static function (array $a, array $b): int {
            $ta = $a['last_activity'] !== '' ? strtotime($a['last_activity']) : 0;
            $tb = $b['last_activity'] !== '' ? strtotime($b['last_activity']) : 0;
            return $tb <=> $ta;
        });

        // ── Clear-filter URL ──────────────────────────────────────────────────
        $clearFilterParams = $selectedFileRel !== '' ? ['source_file' => $selectedFileRel] : [];
        $clearFilterUrl    = strtok($_SERVER['REQUEST_URI'], '?');
        if (!empty($clearFilterParams)) {
            $clearFilterUrl .= '?' . http_build_query($clearFilterParams);
        }

        // ── Render ────────────────────────────────────────────────────────────
        $viewData = [
            'boardName'             => $board->name,
            'boardId'               => $board->id,
            'boardUrl'              => $board->url,
            'lastActivity'          => $board->dateLastActivity,
            'membersCount'          => $board->membersCount,
            'cardsCount'            => count($board->cards),
            'currentFileName'       => basename($jsonPath),
            'availableFiles'        => $availableFiles,
            'selectedFileRel'       => $selectedFileRel,
            'boardListsIndex'       => $boardListsIndex,
            'configuredPendingListIds'    => $configuredPendingListIds,
            'configuredCompletedListIds'  => $configuredCompletedListIds,
            'configuredInProgressListIds' => $configuredInProgressListIds,
            'filterStartInput'      => $filterStartInput,
            'filterEndInput'        => $filterEndInput,
            'filterError'           => $filterError,
            'hasDateFilter'         => $hasDateFilter,
            'clearFilterUrl'        => $clearFilterUrl,
            'displayTotalDemands'   => $displayTotalDemands,
            'displayCompletedDemands' => $displayCompletedDemands,
            'openDemands'           => $openDemands,
            'projectProgress'       => $projectProgress,
            'estimatedCompletionDate' => $velocity?->estimatedCompletionDate ?? '',
            'dailyVelocity'         => $velocity?->dailyVelocity ?? 0.0,
            'chartLabels'           => $calc->chartLabels,
            'chartScopeSeries'      => $calc->chartScopeSeries,
            'chartDoneSeries'       => $calc->chartDoneSeries,
            'chartRemainingSeries'  => $calc->chartRemainingSeries,
            'inProgressCards'       => $inProgressCards,
            'toDoCards'             => $toDoCards,
            'uploadSuccess'         => $uploadSuccess,
            'uploadError'           => $uploadError,
            'deleteSuccess'         => $deleteSuccess,
            'deleteError'           => $deleteError,
            'sourceError'           => $sourceError,
            'listConfigSuccess'     => $listConfigSuccess,
            'listConfigError'       => $listConfigError,
            'updateListNamesSuccess' => $updateListNamesSuccess,
            'updateListNamesError'  => $updateListNamesError,
        ];

        extract($viewData);
        require BASE_DIR . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'dashboard' . DIRECTORY_SEPARATOR . 'index.php';
    }
}
