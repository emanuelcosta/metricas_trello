<?php
declare(strict_types=1);

namespace App\Models;

class UploadsIndex
{
    public static function rebuild(string $uploadsDir, string $indexPath): bool
    {
        $entriesByTrelloId = [];
        if (is_dir($uploadsDir)) {
            $files = glob($uploadsDir . DIRECTORY_SEPARATOR . '*.json');
            if (is_array($files)) {
                usort($files, static function (string $a, string $b): int {
                    $ta = filemtime($a);
                    $tb = filemtime($b);
                    return ($tb ?: 0) <=> ($ta ?: 0);
                });

                foreach ($files as $filePath) {
                    $time = filemtime($filePath) ?: time();
                    $jsonUpdatedAtBr = null;
                    $trelloId = '';
                    $shortUrl = '';
                    $raw = file_get_contents($filePath);
                    if ($raw !== false) {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            if (isset($decoded['id']) && is_string($decoded['id'])) {
                                $trelloId = trim($decoded['id']);
                            }
                            if (isset($decoded['dateLastActivity']) && is_string($decoded['dateLastActivity'])) {
                                $ts = strtotime($decoded['dateLastActivity']);
                                if ($ts !== false) {
                                    $jsonUpdatedAtBr = date('d/m/Y H:i:s', $ts);
                                }
                            }
                            if (isset($decoded['shortUrl']) && is_string($decoded['shortUrl'])) {
                                $shortUrl = trim($decoded['shortUrl']);
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
                        'id_trello'             => $trelloId,
                        'short_url'             => $shortUrl,
                        'nome_arquivo'          => basename($filePath),
                        'data'                  => date('d/m/Y', $time),
                        'hora'                  => date('H:i:s', $time),
                        'data_atualizacao_json' => $jsonUpdatedAtBr,
                        '_sort_time'            => (int)$time,
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
            'indice'        => 'id_trello',
            'arquivos'      => $entriesByTrelloId,
        ];
        $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return file_put_contents($indexPath, $json) !== false;
    }

    public static function buildFileLabel(string $relativePath): string
    {
        if ($relativePath === 'dados.json') {
            return 'dados.json (arquivo base)';
        }
        $fileName = basename($relativePath);
        if (preg_match('/^trello_(\d{4}-\d{2}-\d{2})_(\d{2}-\d{2}-\d{2})_(.+)\.json$/', $fileName, $match) === 1) {
            return $match[1] . ' ' . str_replace('-', ':', $match[2]) . ' - ' . $match[3] . '.json';
        }
        if (preg_match('/^trello_(\d{8})_(\d{6})_(.+)\.json$/', $fileName, $match) === 1) {
            $date = substr($match[1], 0, 4) . '-' . substr($match[1], 4, 2) . '-' . substr($match[1], 6, 2);
            $time = substr($match[2], 0, 2) . ':' . substr($match[2], 2, 2) . ':' . substr($match[2], 4, 2);
            return $date . ' ' . $time . ' - ' . $match[3] . '.json';
        }
        return $fileName;
    }

    public static function getAvailableFiles(
        string $uploadsDir,
        string $indexPath,
        string $defaultJsonPath
    ): array {
        $availableFiles = [];
        if (is_file($defaultJsonPath)) {
            $availableFiles['dados.json'] = [
                'path'      => 'dados.json',
                'label'     => self::buildFileLabel('dados.json'),
                'short_url' => '',
            ];
        }

        $uploadsIndex   = AppConfig::load($indexPath);
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

            $date  = isset($entry['data']) ? trim((string)$entry['data']) : '';
            $time  = isset($entry['hora']) ? trim((string)$entry['hora']) : '';
            $label = $fileName;
            if ($date !== '' && $time !== '') {
                $label = $date . ' ' . $time . ' - ' . $fileName;
            } elseif ($date !== '') {
                $label = $date . ' - ' . $fileName;
            }

            $availableFiles[$relative] = [
                'path'      => $relative,
                'label'     => $label,
                'short_url' => isset($entry['short_url']) ? trim((string)$entry['short_url']) : '',
            ];
        }

        return $availableFiles;
    }
}
