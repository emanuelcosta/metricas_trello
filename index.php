<?php
declare(strict_types=1);

$baseDir = __DIR__;
$defaultJsonPath = $baseDir . DIRECTORY_SEPARATOR . 'dados.json';
$uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
$configPath = $baseDir . DIRECTORY_SEPARATOR . 'app_config.json';
$uploadsIndexPath = $baseDir . DIRECTORY_SEPARATOR . 'uploads_index.json';

function loadConfig(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveConfig(string $path, array $config): bool
{
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json) !== false;
}

function buildFileLabel(string $relativePath): string
{
    if ($relativePath === 'dados.json') {
        return 'dados.json (arquivo base)';
    }

    $fileName = basename($relativePath);
    if (preg_match('/^trello_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})_(.+)\.json$/', $fileName, $match) === 1) {
        $date = $match[1];
        $time = str_replace('-', ':', $match[2]);
        return $date . ' ' . $time . ' - ' . $match[3] . '.json';
    }

    if (preg_match('/^trello_(\d{8})_(\d{6})_(.+)\.json$/', $fileName, $match) === 1) {
        $date = substr($match[1], 0, 4) . '-' . substr($match[1], 4, 2) . '-' . substr($match[1], 6, 2);
        $time = substr($match[2], 0, 2) . ':' . substr($match[2], 2, 2) . ':' . substr($match[2], 4, 2);
        return $date . ' ' . $time . ' - ' . $match[3] . '.json';
    }

    return $fileName;
}

function saveUploadsIndex(string $uploadsDir, string $indexPath): bool
{
    $entriesByTrelloId = [];
    if (is_dir($uploadsDir)) {
        $files = glob($uploadsDir . DIRECTORY_SEPARATOR . '*.json');
        if (is_array($files)) {
            usort($files, static function (string $a, string $b): int {
                $ta = filemtime($a);
                $tb = filemtime($b);
                if ($ta === false) {
                    $ta = 0;
                }
                if ($tb === false) {
                    $tb = 0;
                }
                return $tb <=> $ta;
            });

            foreach ($files as $filePath) {
                $time = filemtime($filePath);
                if ($time === false) {
                    $time = time();
                }

                $jsonUpdatedAtBr = null;
                $trelloId = '';
                $raw = file_get_contents($filePath);
                if ($raw !== false) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && isset($decoded['id']) && is_string($decoded['id'])) {
                        $trelloId = trim($decoded['id']);
                    }
                    if (is_array($decoded) && isset($decoded['dateLastActivity']) && is_string($decoded['dateLastActivity'])) {
                        $jsonUpdatedAt = strtotime($decoded['dateLastActivity']);
                        if ($jsonUpdatedAt !== false) {
                            $jsonUpdatedAtBr = date('d/m/Y H:i:s', $jsonUpdatedAt);
                        }
                    }
                }

                if ($trelloId === '') {
                    $trelloId = 'sem_id_trello';
                }
                if (!isset($entriesByTrelloId[$trelloId])) {
                    $entriesByTrelloId[$trelloId] = [];
                }

                $entriesByTrelloId[$trelloId][] = [
                    'id_trello' => $trelloId,
                    'nome_arquivo' => basename($filePath),
                    'data' => date('d/m/Y', $time),
                    'hora' => date('H:i:s', $time),
                    'data_atualizacao_json' => $jsonUpdatedAtBr,
                    '_sort_time' => (int)$time,
                ];
            }
        }
    }

    foreach ($entriesByTrelloId as &$entries) {
        usort($entries, static function (array $a, array $b): int {
            return $b['_sort_time'] <=> $a['_sort_time'];
        });
        foreach ($entries as &$entry) {
            unset($entry['_sort_time']);
        }
        unset($entry);
    }
    unset($entries);

    $content = [
        'atualizado_em' => date('d/m/Y H:i:s'),
        'indice' => 'id_trello',
        'arquivos' => $entriesByTrelloId,
    ];
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    return file_put_contents($indexPath, $json) !== false;
}

$uploadError = null;
$listConfigError = null;
$filterStartInput = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$filterEndInput = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
$sourceFileInput = isset($_GET['source_file']) ? trim((string)$_GET['source_file']) : '';
$hasQuickTodoParam = array_key_exists('todo_list_id', $_GET);
$hasQuickDoneParam = array_key_exists('done_list_id', $_GET);
$quickTodoListInput = $hasQuickTodoParam ? trim((string)$_GET['todo_list_id']) : '';
$quickDoneListInput = $hasQuickDoneParam ? trim((string)$_GET['done_list_id']) : '';
$sourceError = null;
$listConfigSuccess = isset($_GET['config_lists']) && $_GET['config_lists'] === 'ok';
$uploadSuccess = isset($_GET['upload']) && $_GET['upload'] === 'ok';

$config = loadConfig($configPath);
if (empty($config)) {
    $config = [
        'latest_uploaded_file' => 'dados.json',
        'updated_at' => gmdate('c'),
    ];
    saveConfig($configPath, $config);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'save_list_config')) {
    if (!isset($_FILES['trello_json']) || !is_array($_FILES['trello_json'])) {
        $uploadError = 'Nenhum arquivo foi enviado.';
    } elseif (!isset($_FILES['trello_json']['error']) || $_FILES['trello_json']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Falha no upload do arquivo.';
    } else {
        $originalName = isset($_FILES['trello_json']['name']) ? (string)$_FILES['trello_json']['name'] : 'trello.json';
        $tmpName = isset($_FILES['trello_json']['tmp_name']) ? (string)$_FILES['trello_json']['tmp_name'] : '';
        $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension !== 'json') {
            $uploadError = 'Envie um arquivo com extensão .json.';
        } elseif ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $uploadError = 'Arquivo temporário de upload inválido.';
        } else {
            $rawUploaded = file_get_contents($tmpName);
            $parsed = $rawUploaded !== false ? json_decode($rawUploaded, true) : null;
            if (!is_array($parsed)) {
                $uploadError = 'O arquivo enviado não contém um JSON válido.';
            } else {
                if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0777, true) && !is_dir($uploadsDir)) {
                    $uploadError = 'Não foi possível criar a pasta de uploads.';
                } else {
                    $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
                    if (!is_string($safeName) || $safeName === '') {
                        $safeName = 'trello';
                    }

                    $timestamp = date('Y-m-d_H-i-s');
                    $targetFileName = 'trello_' . $timestamp . '_' . $safeName . '.json';
                    $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $targetFileName;
                    $suffix = 1;
                    while (is_file($targetPath)) {
                        $targetFileName = 'trello_' . $timestamp . '_' . $safeName . '_' . $suffix . '.json';
                        $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $targetFileName;
                        $suffix++;
                    }

                    if (!move_uploaded_file($tmpName, $targetPath)) {
                        $uploadError = 'Não foi possível salvar o arquivo enviado.';
                    } else {
                        $config['latest_uploaded_file'] = 'uploads/' . $targetFileName;
                        $config['updated_at'] = gmdate('c');
                        if (!saveConfig($configPath, $config)) {
                            $uploadError = 'Arquivo enviado, mas não foi possível atualizar app_config.json.';
                        } elseif (!saveUploadsIndex($uploadsDir, $uploadsIndexPath)) {
                            $uploadError = 'Arquivo enviado, mas não foi possível atualizar uploads_index.json.';
                        } else {
                            $redirectParams = ['upload' => 'ok'];
                            $postStart = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
                            $postEnd = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
                            if ($postStart !== '') {
                                $redirectParams['start_date'] = $postStart;
                            }
                            if ($postEnd !== '') {
                                $redirectParams['end_date'] = $postEnd;
                            }
                            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($redirectParams));
                            exit;
                        }
                    }
                }
            }
        }
    }
}

saveUploadsIndex($uploadsDir, $uploadsIndexPath);

$availableFiles = [];
if (is_file($defaultJsonPath)) {
    $availableFiles['dados.json'] = [
        'path' => 'dados.json',
        'label' => buildFileLabel('dados.json'),
    ];
}

$uploadsIndex = loadConfig($uploadsIndexPath);
$indexedUploads = [];
if (isset($uploadsIndex['arquivos'])) {
    $indexedNode = $uploadsIndex['arquivos'];
    if (is_array($indexedNode) && isset($indexedNode[0]) && is_array($indexedNode[0])) {
        $indexedUploads = $indexedNode;
    } elseif (is_array($indexedNode)) {
        foreach ($indexedNode as $trelloId => $group) {
            if (is_array($group) && isset($group[0]) && is_array($group[0])) {
                foreach ($group as $entry) {
                    if (!isset($entry['id_trello']) || $entry['id_trello'] === '') {
                        $entry['id_trello'] = (string)$trelloId;
                    }
                    $indexedUploads[] = $entry;
                }
            } elseif (is_array($group) && isset($group['nome_arquivo'])) {
                if (!isset($group['id_trello']) || $group['id_trello'] === '') {
                    $group['id_trello'] = (string)$trelloId;
                }
                $indexedUploads[] = $group;
            }
        }
    }
}

foreach ($indexedUploads as $entry) {
    $fileName = isset($entry['nome_arquivo']) ? trim((string)$entry['nome_arquivo']) : '';
    if ($fileName === '') {
        continue;
    }
    $relative = 'uploads/' . $fileName;
    $absolute = $uploadsDir . DIRECTORY_SEPARATOR . $fileName;
    if (!is_file($absolute)) {
        continue;
    }

    $date = isset($entry['data']) ? trim((string)$entry['data']) : '';
    $time = isset($entry['hora']) ? trim((string)$entry['hora']) : '';
    $label = $fileName;
    if ($date !== '' && $time !== '') {
        $label = $date . ' ' . $time . ' - ' . $fileName;
    } elseif ($date !== '') {
        $label = $date . ' - ' . $fileName;
    }

    $availableFiles[$relative] = [
        'path' => $relative,
        'label' => $label,
    ];
}

$configuredRel = isset($config['latest_uploaded_file']) ? (string)$config['latest_uploaded_file'] : 'dados.json';
$configuredRel = str_replace('\\', '/', $configuredRel);
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
    $config['updated_at'] = gmdate('c');
    saveConfig($configPath, $config);
}

$jsonPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($selectedFileRel, '/'));

if (!is_file($jsonPath)) {
    $jsonPath = $defaultJsonPath;
}

if (!is_file($jsonPath)) {
    http_response_code(500);
    echo 'Nenhum arquivo JSON encontrado para gerar os gráficos.';
    exit;
}

$rawJson = file_get_contents($jsonPath);
if ($rawJson === false) {
    http_response_code(500);
    echo 'Não foi possível ler o arquivo JSON selecionado.';
    exit;
}

$board = json_decode($rawJson, true);
if (!is_array($board)) {
    http_response_code(500);
    echo 'JSON inválido no arquivo selecionado.';
    exit;
}

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

$cards = isset($board['cards']) && is_array($board['cards']) ? $board['cards'] : [];
$checklists = isset($board['checklists']) && is_array($board['checklists']) ? $board['checklists'] : [];
$actions = isset($board['actions']) && is_array($board['actions']) ? $board['actions'] : [];
$lists = isset($board['lists']) && is_array($board['lists']) ? $board['lists'] : [];

$availableListIds = [];
$boardListsIndex = [];
$autoCompletedListId = '';
$currentBoardId = isset($board['id']) ? (string)$board['id'] : '';
$listPosition = 1;
foreach ($lists as $list) {
    $listId = isset($list['id']) ? (string)$list['id'] : '';
    $listName = isset($list['name']) ? (string)$list['name'] : '';
    if ($listId === '') {
        continue;
    }
    $availableListIds[$listId] = true;
    $boardListsIndex[(string)$listPosition] = [
        'id' => $listId,
        'name' => $listName,
    ];
    if ($autoCompletedListId === '' && preg_match('/(conclu|done|finaliz|pronto|entreg)/i', $listName) === 1) {
        $autoCompletedListId = $listId;
    }
    $listPosition++;
}

$configChanged = false;
if (!isset($config['board_lists_index']) || !is_array($config['board_lists_index']) || $config['board_lists_index'] !== $boardListsIndex) {
    $config['board_lists_index'] = $boardListsIndex;
    $configChanged = true;
}

if (!isset($config['demand_lists']) || !is_array($config['demand_lists'])) {
    $config['demand_lists'] = [
        'pending_list_ids' => [],
        'completed_list_ids' => [],
    ];
    $configChanged = true;
}
if (!isset($config['demand_fields']) || !is_array($config['demand_fields'])) {
    $config['demand_fields'] = [
        'todo_list_id' => '',
        'done_list_id' => '',
        'board_id' => '',
    ];
    $configChanged = true;
}

$configuredListsBoardId = isset($config['demand_lists_board_id']) ? (string)$config['demand_lists_board_id'] : '';
$isDifferentBoard = $currentBoardId !== '' && $configuredListsBoardId !== $currentBoardId;
$quickConfiguredBoardId = isset($config['demand_fields']['board_id']) ? (string)$config['demand_fields']['board_id'] : '';
$quickConfiguredTodoListId = isset($config['demand_fields']['todo_list_id']) ? (string)$config['demand_fields']['todo_list_id'] : '';
$quickConfiguredDoneListId = isset($config['demand_fields']['done_list_id']) ? (string)$config['demand_fields']['done_list_id'] : '';
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
    $singleCompletedId = (string)$config['demand_lists']['completed_list_id'];
    if ($singleCompletedId !== '' && isset($availableListIds[$singleCompletedId])) {
        $configuredCompletedListIds[$singleCompletedId] = true;
    }
}
$configuredCompletedListIds = array_keys($configuredCompletedListIds);

if ($isDifferentBoard) {
    $configuredCompletedListIds = $autoCompletedListId !== '' ? [$autoCompletedListId] : [];
    $configuredPendingListIds = [];
    foreach ($availableListIds as $listId => $_) {
        if (!in_array($listId, $configuredCompletedListIds, true)) {
            $configuredPendingListIds[] = $listId;
        }
    }
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
}

if ($hasQuickTodoParam || $hasQuickDoneParam) {
    $newTodoListId = ($quickTodoListInput !== '' && isset($availableListIds[$quickTodoListInput])) ? $quickTodoListInput : '';
    $newDoneListId = ($quickDoneListInput !== '' && isset($availableListIds[$quickDoneListInput])) ? $quickDoneListInput : '';
    if ($newTodoListId !== '' && $newTodoListId === $newDoneListId) {
        $listConfigError = 'A lista "A fazer" deve ser diferente da lista "Concluídas".';
    } else {
        $quickConfiguredTodoListId = $newTodoListId;
        $quickConfiguredDoneListId = $newDoneListId;
        $quickConfiguredBoardId = $currentBoardId;
        $config['demand_fields']['todo_list_id'] = $quickConfiguredTodoListId;
        $config['demand_fields']['done_list_id'] = $quickConfiguredDoneListId;
        $config['demand_fields']['board_id'] = $quickConfiguredBoardId;
        $config['updated_at'] = gmdate('c');
        if (!saveConfig($configPath, $config)) {
            $listConfigError = 'Não foi possível salvar a configuração rápida de listas.';
        } else {
            $listConfigSuccess = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_list_config') {
    $postedPending = isset($_POST['pending_list_ids']) && is_array($_POST['pending_list_ids']) ? $_POST['pending_list_ids'] : [];
    $postedCompleted = isset($_POST['completed_list_ids']) && is_array($_POST['completed_list_ids']) ? $_POST['completed_list_ids'] : [];
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

    $config['demand_lists']['pending_list_ids'] = $newPendingListIds;
    $config['demand_lists']['completed_list_ids'] = $newCompletedListIds;
    $config['demand_lists']['completed_list_id'] = $newCompletedListIds[0] ?? '';
    $config['demand_lists_board_id'] = $currentBoardId;
    $config['updated_at'] = gmdate('c');
    if (!saveConfig($configPath, $config)) {
        $listConfigError = 'Não foi possível salvar a configuração de listas.';
    } else {
        $redirectParams = ['config_lists' => 'ok'];
        $postSourceFile = isset($_POST['source_file']) ? trim((string)$_POST['source_file']) : '';
        if ($postSourceFile !== '') {
            $redirectParams['source_file'] = $postSourceFile;
        }
        $postStartDate = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
        if ($postStartDate !== '') {
            $redirectParams['start_date'] = $postStartDate;
        }
        $postEndDate = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';
        if ($postEndDate !== '') {
            $redirectParams['end_date'] = $postEndDate;
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($redirectParams));
        exit;
    }
}

if ($configChanged) {
    $config['demand_lists']['pending_list_ids'] = $configuredPendingListIds;
    $config['demand_lists']['completed_list_ids'] = $configuredCompletedListIds;
    $config['demand_lists']['completed_list_id'] = $configuredCompletedListIds[0] ?? '';
    $config['demand_lists_board_id'] = $currentBoardId;
    if ($quickConfiguredBoardId === '' || $quickConfiguredBoardId !== $currentBoardId) {
        $quickConfiguredBoardId = $currentBoardId;
        $quickConfiguredDoneListId = $configuredCompletedListIds[0] ?? '';
        $quickConfiguredTodoListId = $configuredPendingListIds[0] ?? '';
    }
    $config['demand_fields']['todo_list_id'] = $quickConfiguredTodoListId;
    $config['demand_fields']['done_list_id'] = $quickConfiguredDoneListId;
    $config['demand_fields']['board_id'] = $quickConfiguredBoardId;
    $config['updated_at'] = gmdate('c');
    saveConfig($configPath, $config);
}

$activePendingListIds = $configuredPendingListIds;
$activeCompletedListIds = $configuredCompletedListIds;
if ($quickConfiguredBoardId === $currentBoardId) {
    if ($quickConfiguredTodoListId !== '') {
        $activePendingListIds = [$quickConfiguredTodoListId];
    }
    if ($quickConfiguredDoneListId !== '') {
        $activeCompletedListIds = [$quickConfiguredDoneListId];
    }
}

$doneListIds = [];
foreach ($activeCompletedListIds as $completedId) {
    if ($completedId !== '') {
        $doneListIds[$completedId] = true;
    }
}

$checkItemsByCard = [];
$checkItemsDataByCard = [];
foreach ($checklists as $checklist) {
    $cardId = isset($checklist['idCard']) ? (string)$checklist['idCard'] : '';
    if ($cardId === '') {
        continue;
    }

    if (!isset($checkItemsByCard[$cardId])) {
        $checkItemsByCard[$cardId] = 0;
        $checkItemsDataByCard[$cardId] = [];
    }

    $items = isset($checklist['checkItems']) && is_array($checklist['checkItems']) ? $checklist['checkItems'] : [];
    foreach ($items as $item) {
        $checkItemsByCard[$cardId]++;
        $checkItemsDataByCard[$cardId][] = $item;
    }
}

usort($actions, static function (array $a, array $b): int {
    $ad = isset($a['date']) ? strtotime((string)$a['date']) : 0;
    $bd = isset($b['date']) ? strtotime((string)$b['date']) : 0;
    return $ad <=> $bd;
});

$cardCreatedAt = [];
$cardFirstActionAt = [];
$cardDoneAt = [];
$checkItemCompletedAt = [];

foreach ($actions as $action) {
    $type = isset($action['type']) ? (string)$action['type'] : '';
    $date = isset($action['date']) ? (string)$action['date'] : '';
    if ($date === '') {
        continue;
    }

    $cardId = isset($action['data']['card']['id']) ? (string)$action['data']['card']['id'] : '';
    if ($cardId !== '' && !isset($cardFirstActionAt[$cardId])) {
        $cardFirstActionAt[$cardId] = $date;
    }

    if ($type === 'createCard' && $cardId !== '' && !isset($cardCreatedAt[$cardId])) {
        $cardCreatedAt[$cardId] = $date;
    }

    if ($type === 'updateCard' && $cardId !== '') {
        $after = isset($action['data']['listAfter']['id']) ? (string)$action['data']['listAfter']['id'] : '';
        $before = isset($action['data']['listBefore']['id']) ? (string)$action['data']['listBefore']['id'] : '';
        if ($after !== '' && isset($doneListIds[$after]) && !isset($doneListIds[$before]) && !isset($cardDoneAt[$cardId])) {
            $cardDoneAt[$cardId] = $date;
        }
    }

    if ($type === 'createCard' && $cardId !== '' && !isset($cardDoneAt[$cardId])) {
        $listId = isset($action['data']['list']['id']) ? (string)$action['data']['list']['id'] : '';
        if ($listId !== '' && isset($doneListIds[$listId])) {
            $cardDoneAt[$cardId] = $date;
        }
    }

    if ($type === 'updateCheckItemStateOnCard') {
        $checkItemId = isset($action['data']['checkItem']['id']) ? (string)$action['data']['checkItem']['id'] : '';
        $state = isset($action['data']['checkItem']['state']) ? (string)$action['data']['checkItem']['state'] : '';
        if ($checkItemId !== '' && $state === 'complete' && !isset($checkItemCompletedAt[$checkItemId])) {
            $checkItemCompletedAt[$checkItemId] = $date;
        }
    }
}

$scopeByDate = [];
$doneByDate = [];
$totalDemands = 0;
$completedDemands = 0;
$allowedListIds = [];
foreach ($configuredPendingListIds as $pendingListId) {
    $allowedListIds[$pendingListId] = true;
}
foreach ($doneListIds as $doneListId => $_doneFlag) {
    $allowedListIds[$doneListId] = true;
}
$hasAllowedListFilter = !empty($allowedListIds);

foreach ($cards as $card) {
    $cardId = isset($card['id']) ? (string)$card['id'] : '';
    if ($cardId === '') {
        continue;
    }
    $currentCardListId = isset($card['idList']) ? (string)$card['idList'] : '';
    if ($hasAllowedListFilter && ($currentCardListId === '' || !isset($allowedListIds[$currentCardListId]))) {
        continue;
    }

    $cardLastActivity = isset($card['dateLastActivity']) ? (string)$card['dateLastActivity'] : (isset($board['dateLastActivity']) ? (string)$board['dateLastActivity'] : '');
    $baseCreatedAt = isset($cardCreatedAt[$cardId]) ? $cardCreatedAt[$cardId] : (isset($cardFirstActionAt[$cardId]) ? $cardFirstActionAt[$cardId] : $cardLastActivity);

    $checkItemsCount = isset($checkItemsByCard[$cardId]) ? (int)$checkItemsByCard[$cardId] : 0;
    if ($checkItemsCount > 0) {
        $items = isset($checkItemsDataByCard[$cardId]) ? $checkItemsDataByCard[$cardId] : [];
        foreach ($items as $item) {
            $totalDemands++;
            $itemId = isset($item['id']) ? (string)$item['id'] : '';

            if ($baseCreatedAt !== '') {
                $key = gmdate('Y-m-d', strtotime($baseCreatedAt));
                if (!isset($scopeByDate[$key])) {
                    $scopeByDate[$key] = 0;
                }
                $scopeByDate[$key]++;
            }

            $isComplete = isset($item['state']) && $item['state'] === 'complete';
            if ($isComplete) {
                $completedDemands++;
                $doneAt = isset($checkItemCompletedAt[$itemId]) ? $checkItemCompletedAt[$itemId] : $cardLastActivity;
                if ($doneAt !== '') {
                    $doneKey = gmdate('Y-m-d', strtotime($doneAt));
                    if (!isset($doneByDate[$doneKey])) {
                        $doneByDate[$doneKey] = 0;
                    }
                    $doneByDate[$doneKey]++;
                }
            }
        }
        continue;
    }

    $totalDemands++;
    if ($baseCreatedAt !== '') {
        $key = gmdate('Y-m-d', strtotime($baseCreatedAt));
        if (!isset($scopeByDate[$key])) {
            $scopeByDate[$key] = 0;
        }
        $scopeByDate[$key]++;
    }

    $listId = $currentCardListId;
    $isComplete = $listId !== '' && isset($doneListIds[$listId]);
    if ($isComplete) {
        $completedDemands++;
        $doneAt = isset($cardDoneAt[$cardId]) ? $cardDoneAt[$cardId] : $cardLastActivity;
        if ($doneAt !== '') {
            $doneKey = gmdate('Y-m-d', strtotime($doneAt));
            if (!isset($doneByDate[$doneKey])) {
                $doneByDate[$doneKey] = 0;
            }
            $doneByDate[$doneKey]++;
        }
    }
}

if (empty($scopeByDate) && !empty($doneByDate)) {
    $scopeByDate = $doneByDate;
} elseif (empty($doneByDate) && !empty($scopeByDate)) {
    $doneByDate = [];
}

$allDates = array_unique(array_merge(array_keys($scopeByDate), array_keys($doneByDate)));
sort($allDates);

$labels = [];
$dateKeys = [];
$scopeSeries = [];
$doneSeries = [];
$remainingSeries = [];
$scopeAccum = 0;
$doneAccum = 0;

if (!empty($allDates)) {
    $current = new DateTimeImmutable($allDates[0]);
    $end = new DateTimeImmutable($allDates[count($allDates) - 1]);
    while ($current <= $end) {
        $key = $current->format('Y-m-d');
        $dateKeys[] = $key;
        $labels[] = $current->format('d/m/Y');
        $scopeAccum += isset($scopeByDate[$key]) ? (int)$scopeByDate[$key] : 0;
        $doneAccum += isset($doneByDate[$key]) ? (int)$doneByDate[$key] : 0;
        if ($doneAccum > $scopeAccum) {
            $doneAccum = $scopeAccum;
        }
        $scopeSeries[] = $scopeAccum;
        $doneSeries[] = $doneAccum;
        $remainingSeries[] = $scopeAccum - $doneAccum;
        $current = $current->modify('+1 day');
    }
}

$chartLabels = $labels;
$chartScopeSeries = $scopeSeries;
$chartDoneSeries = $doneSeries;
$chartRemainingSeries = $remainingSeries;
$periodTotalDemands = $totalDemands;
$periodCompletedDemands = $completedDemands;

if ($hasDateFilter && !empty($dateKeys)) {
    $selectedIndexes = [];
    foreach ($dateKeys as $index => $dateKey) {
        $afterStart = $filterStartInput === '' || $dateKey >= $filterStartInput;
        $beforeEnd = $filterEndInput === '' || $dateKey <= $filterEndInput;
        if ($afterStart && $beforeEnd) {
            $selectedIndexes[] = $index;
        }
    }

    $chartLabels = [];
    $chartScopeSeries = [];
    $chartDoneSeries = [];
    $chartRemainingSeries = [];

    foreach ($selectedIndexes as $index) {
        $chartLabels[] = $labels[$index];
        $chartScopeSeries[] = $scopeSeries[$index];
        $chartDoneSeries[] = $doneSeries[$index];
        $chartRemainingSeries[] = $remainingSeries[$index];
    }

    if (!empty($selectedIndexes)) {
        $firstIndex = $selectedIndexes[0];
        $lastIndex = $selectedIndexes[count($selectedIndexes) - 1];
        $scopeBefore = $firstIndex > 0 ? $scopeSeries[$firstIndex - 1] : 0;
        $doneBefore = $firstIndex > 0 ? $doneSeries[$firstIndex - 1] : 0;
        $periodTotalDemands = max(0, $scopeSeries[$lastIndex] - $scopeBefore);
        $periodCompletedDemands = max(0, $doneSeries[$lastIndex] - $doneBefore);
    } else {
        $periodTotalDemands = 0;
        $periodCompletedDemands = 0;
    }
}

$boardName = isset($board['name']) ? (string)$board['name'] : 'Board Trello';
$boardUrl = isset($board['url']) ? (string)$board['url'] : '#';
$boardId = isset($board['id']) ? (string)$board['id'] : '-';
$lastActivity = isset($board['dateLastActivity']) ? (string)$board['dateLastActivity'] : null;
$membersCount = isset($board['members']) && is_array($board['members']) ? count($board['members']) : 0;
$cardsCount = count($cards);
$displayTotalDemands = $hasDateFilter ? $periodTotalDemands : $totalDemands;
$displayCompletedDemands = $hasDateFilter ? $periodCompletedDemands : $completedDemands;
$openDemands = max(0, $displayTotalDemands - $displayCompletedDemands);
$currentFileName = basename($jsonPath);
$clearFilterParams = [];
if ($selectedFileRel !== '') {
    $clearFilterParams['source_file'] = $selectedFileRel;
}
$clearFilterUrl = strtok($_SERVER['REQUEST_URI'], '?');
if (!empty($clearFilterParams)) {
    $clearFilterUrl .= '?' . http_build_query($clearFilterParams);
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Burndown e Burnup - <?php echo htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <style>
        .chart-wrapper {
            position: relative;
            height: 320px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Upload de JSON atualizado</h2>
            <?php if ($uploadSuccess): ?>
                <div class="alert alert-success py-2">Upload concluído com sucesso.</div>
            <?php endif; ?>
            <?php if ($uploadError !== null): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                <div class="col-md-9">
                    <label for="trello_json" class="form-label">Arquivo JSON do Trello</label>
                    <input class="form-control" type="file" id="trello_json" name="trello_json" accept=".json" required>
                </div>
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary" type="submit">Enviar arquivo</button>
                </div>
            </form>
            <div class="mt-2 text-muted small">
                Arquivo atual em uso: <strong><?php echo htmlspecialchars($currentFileName, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Ler arquivo existente</h2>
            <?php if ($sourceError !== null): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($sourceError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-12">
                    <label for="source_file" class="form-label">Arquivo JSON disponível</label>
                    <select class="form-select" id="source_file" name="source_file">
                        <?php foreach ($availableFiles as $fileMeta): ?>
                            <option value="<?php echo htmlspecialchars($fileMeta['path'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $fileMeta['path'] === $selectedFileRel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($fileMeta['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
               
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col-md-4 d-grid">
                    <button class="btn btn-primary" type="submit">Usar arquivo selecionado</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Configuração de listas de demanda</h2>
            <div class="text-muted small mb-2">
                Board atual: <strong><?php echo htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <?php if ($listConfigSuccess): ?>
                <div class="alert alert-success py-2">Configuração de listas salva com sucesso.</div>
            <?php endif; ?>
            <?php if ($listConfigError !== null): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($listConfigError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="save_list_config">
                <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="col-12">
                    <label class="form-label">Listas que contarão como pendentes</label>
                    <div class="row g-2">
                        <?php foreach ($boardListsIndex as $indexKey => $listMeta): ?>
                            <?php $isPendingChecked = in_array($listMeta['id'], $configuredPendingListIds, true); ?>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="pending_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>" name="pending_list_ids[]" value="<?php echo htmlspecialchars($listMeta['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isPendingChecked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="pending_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>">
                                        [<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>] <?php echo htmlspecialchars($listMeta['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Listas que contarão como concluídas</label>
                    <div class="row g-2">
                        <?php foreach ($boardListsIndex as $indexKey => $listMeta): ?>
                            <?php $isCompletedChecked = in_array($listMeta['id'], $configuredCompletedListIds, true); ?>
                            <div class="col-12 col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="completed_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>" name="completed_list_ids[]" value="<?php echo htmlspecialchars($listMeta['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isCompletedChecked ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="completed_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>">
                                        [<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>] <?php echo htmlspecialchars($listMeta['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12 col-md-3 d-grid align-self-end">
                    <button class="btn btn-primary" type="submit">Salvar configuração</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Filtro de atividades por data</h2>
            <?php if ($filterError !== null): ?>
                <div class="alert alert-danger py-2"><?php echo htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Data inicial</label>
                    <input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Data final</label>
                    <input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                </div>
                <div class="col-md-2 d-grid">
                    <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($clearFilterUrl, ENT_QUOTES, 'UTF-8'); ?>">Limpar</a>
                </div>
            </form>
            <?php if ($hasDateFilter): ?>
                <div class="mt-2 text-muted small">
                    Período aplicado:
                    <strong><?php echo $filterStartInput !== '' ? htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8') : 'início'; ?></strong>
                    até
                    <strong><?php echo $filterEndInput !== '' ? htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8') : 'hoje'; ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h1 class="h3 mb-3"><?php echo htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="row g-3">
                <div class="col-md-4"><strong>ID do board:</strong> <?php echo htmlspecialchars($boardId, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="col-md-4"><strong>Última atividade:</strong> <?php echo $lastActivity ? htmlspecialchars(gmdate('d/m/Y H:i', strtotime($lastActivity)), ENT_QUOTES, 'UTF-8') . ' UTC' : '-'; ?></div>
                <div class="col-md-4"><strong>Membros:</strong> <?php echo $membersCount; ?></div>
                <div class="col-md-4"><strong>Cartões:</strong> <?php echo $cardsCount; ?></div>
                <div class="col-md-4"><strong>Demandas totais<?php echo $hasDateFilter ? ' (período)' : ''; ?>:</strong> <?php echo $displayTotalDemands; ?></div>
                <div class="col-md-4"><strong>Demandas concluídas<?php echo $hasDateFilter ? ' (período)' : ''; ?>:</strong> <?php echo $displayCompletedDemands; ?></div>
                <div class="col-md-4"><strong>Demandas em aberto:</strong> <?php echo $openDemands; ?></div>
                <div class="col-md-8"><strong>URL:</strong> <a href="<?php echo htmlspecialchars($boardUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($boardUrl, ENT_QUOTES, 'UTF-8'); ?></a></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Burnup</h2>
                    <div class="chart-wrapper">
                        <canvas id="burnupChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Burndown</h2>
                    <div class="chart-wrapper">
                        <canvas id="burndownChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/chartjs/chart.umd.min.js"></script>
<script>
const labels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const scopeSeries = <?php echo json_encode($chartScopeSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const doneSeries = <?php echo json_encode($chartDoneSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const remainingSeries = <?php echo json_encode($chartRemainingSeries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function compressSeries(sourceLabels, sourceArrays, maxPoints) {
    if (sourceLabels.length <= maxPoints) {
        return { labels: sourceLabels, arrays: sourceArrays };
    }

    const indexes = [];
    const steps = maxPoints - 1;
    const maxIndex = sourceLabels.length - 1;
    for (let i = 0; i <= steps; i++) {
        indexes.push(Math.round((i / steps) * maxIndex));
    }

    const uniqueIndexes = [...new Set(indexes)];
    return {
        labels: uniqueIndexes.map((index) => sourceLabels[index]),
        arrays: sourceArrays.map((arr) => uniqueIndexes.map((index) => arr[index]))
    };
}

const compressed = compressSeries(labels, [scopeSeries, doneSeries, remainingSeries], 20);
const chartLabels = compressed.labels;
const chartScopeSeries = compressed.arrays[0];
const chartDoneSeries = compressed.arrays[1];
const chartRemainingSeries = compressed.arrays[2];

new Chart(document.getElementById('burnupChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Escopo total (demandas)',
                data: chartScopeSeries,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.65)',
                borderWidth: 1
            },
            {
                label: 'Concluídas',
                data: chartDoneSeries,
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,0.65)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});

new Chart(document.getElementById('burndownChart'), {
    type: 'bar',
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: 'Demandas restantes',
                data: chartRemainingSeries,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.65)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
            y: { beginAtZero: true, ticks: { precision: 0 } }
        }
    }
});
</script>
</body>
</html>
