<?php
declare(strict_types=1);

namespace App\Models;

class BoardImporter
{
    public function __construct(private \PDO $pdo) {}

    public function import(TrelloBoard $board, string $importedFile = ''): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->upsertBoard($board, $importedFile);
            $this->upsertLists($board);
            $this->upsertCards($board);
            $this->upsertChecklists($board);
            $this->upsertActions($board);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function upsertBoard(TrelloBoard $board, string $importedFile): void
    {
        $this->pdo->prepare(<<<SQL
            INSERT INTO boards (id, name, url, short_url, date_last_activity, members_count, imported_file, imported_at)
            VALUES (:id, :name, :url, :su, :dla, :mc, :file, DATETIME('now'))
            ON CONFLICT(id) DO UPDATE SET
                name               = excluded.name,
                url                = excluded.url,
                short_url          = excluded.short_url,
                date_last_activity = excluded.date_last_activity,
                members_count      = excluded.members_count,
                imported_file      = excluded.imported_file,
                imported_at        = excluded.imported_at
        SQL)->execute([
            ':id'   => $board->id,
            ':name' => $board->name,
            ':url'  => $board->url,
            ':su'   => '',
            ':dla'  => $board->dateLastActivity !== null ? $this->toSqlite($board->dateLastActivity) : null,
            ':mc'   => $board->membersCount,
            ':file' => $importedFile,
        ]);
    }

    private function upsertLists(TrelloBoard $board): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO lists (id, board_id, name, position)
            VALUES (:id, :bid, :name, :pos)
            ON CONFLICT(id) DO UPDATE SET
                name     = excluded.name,
                position = excluded.position
        SQL);
        $pos = 1;
        foreach ($board->lists as $list) {
            $id = isset($list['id']) ? (string)$list['id'] : '';
            if ($id === '') { continue; }
            $stmt->execute([
                ':id'   => $id,
                ':bid'  => $board->id,
                ':name' => isset($list['name']) ? (string)$list['name'] : '',
                ':pos'  => $pos++,
            ]);
        }
    }

    private function upsertCards(TrelloBoard $board): void
    {
        $stmt = $this->pdo->prepare(<<<SQL
            INSERT INTO cards (id, board_id, list_id, name, short_link, date_last_activity, created_at, done_at)
            VALUES (:id, :bid, :lid, :name, :sl, :dla, :ca, :da)
            ON CONFLICT(id) DO UPDATE SET
                list_id            = excluded.list_id,
                name               = excluded.name,
                short_link         = excluded.short_link,
                date_last_activity = excluded.date_last_activity,
                created_at         = excluded.created_at,
                done_at            = excluded.done_at
        SQL);
        foreach ($board->cards as $card) {
            $id  = isset($card['id']) ? (string)$card['id'] : '';
            $lid = isset($card['idList']) ? (string)$card['idList'] : '';
            if ($id === '' || $lid === '') { continue; }
            $stmt->execute([
                ':id'   => $id,
                ':bid'  => $board->id,
                ':lid'  => $lid,
                ':name' => isset($card['name']) ? (string)$card['name'] : '',
                ':sl'   => isset($card['shortLink']) ? (string)$card['shortLink'] : '',
                ':dla'  => isset($card['dateLastActivity']) ? $this->toSqlite((string)$card['dateLastActivity']) : null,
                ':ca'   => null,
                ':da'   => null,
            ]);
        }
    }

    private function upsertChecklists(TrelloBoard $board): void
    {
        $stmtCl = $this->pdo->prepare(<<<SQL
            INSERT INTO checklists (id, card_id, name)
            VALUES (:id, :cid, :name)
            ON CONFLICT(id) DO UPDATE SET name = excluded.name
        SQL);
        $stmtCi = $this->pdo->prepare(<<<SQL
            INSERT INTO check_items (id, checklist_id, card_id, name, state, completed_at)
            VALUES (:id, :clid, :cid, :name, :state, :cat)
            ON CONFLICT(id) DO UPDATE SET
                name         = excluded.name,
                state        = excluded.state,
                completed_at = excluded.completed_at
        SQL);

        foreach ($board->checklists as $cl) {
            $clId   = isset($cl['id']) ? (string)$cl['id'] : '';
            $cardId = isset($cl['idCard']) ? (string)$cl['idCard'] : '';
            if ($clId === '' || $cardId === '') { continue; }

            $stmtCl->execute([
                ':id'   => $clId,
                ':cid'  => $cardId,
                ':name' => isset($cl['name']) ? (string)$cl['name'] : '',
            ]);

            foreach (isset($cl['checkItems']) && is_array($cl['checkItems']) ? $cl['checkItems'] : [] as $item) {
                $itemId = isset($item['id']) ? (string)$item['id'] : '';
                if ($itemId === '') { continue; }
                $state = isset($item['state']) && $item['state'] === 'complete' ? 'complete' : 'incomplete';
                $stmtCi->execute([
                    ':id'    => $itemId,
                    ':clid'  => $clId,
                    ':cid'   => $cardId,
                    ':name'  => isset($item['name']) ? (string)$item['name'] : '',
                    ':state' => $state,
                    ':cat'   => null,
                ]);
            }
        }
    }

    private function upsertActions(TrelloBoard $board): void
    {
        $stmtAction = $this->pdo->prepare(<<<SQL
            INSERT INTO actions (id, board_id, card_id, type, date, data)
            VALUES (:id, :bid, :cid, :type, :date, :data)
            ON CONFLICT(id) DO UPDATE SET
                type = excluded.type,
                date = excluded.date,
                data = excluded.data
        SQL);
        $stmtCiDone = $this->pdo->prepare(
            'UPDATE check_items SET completed_at = :d WHERE id = :id AND (completed_at IS NULL OR completed_at > :d)'
        );
        $stmtCardDone = $this->pdo->prepare(
            'UPDATE cards SET done_at = :d WHERE id = :id AND done_at IS NULL'
        );

        foreach ($board->actions as $action) {
            $actionId = isset($action['id']) ? (string)$action['id'] : '';
            if ($actionId === '') { continue; }

            $cardId = isset($action['data']['card']['id']) ? (string)$action['data']['card']['id'] : null;
            $type   = isset($action['type']) ? (string)$action['type'] : '';
            $date   = isset($action['date']) ? $this->toSqlite((string)$action['date']) : null;
            $data   = isset($action['data'])
                ? json_encode($action['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            $stmtAction->execute([
                ':id'   => $actionId,
                ':bid'  => $board->id,
                ':cid'  => $cardId,
                ':type' => $type,
                ':date' => $date,
                ':data' => $data,
            ]);

            if ($type === 'updateCheckItemStateOnCard' && $date !== null) {
                $checkItemId = isset($action['data']['checkItem']['id']) ? (string)$action['data']['checkItem']['id'] : '';
                $state       = isset($action['data']['checkItem']['state']) ? (string)$action['data']['checkItem']['state'] : '';
                if ($checkItemId !== '' && $state === 'complete') {
                    $stmtCiDone->execute([':d' => $date, ':id' => $checkItemId]);
                }
            }

            if ($type === 'updateCard' && $cardId !== null && $date !== null) {
                $afterListId = isset($action['data']['listAfter']['id']) ? (string)$action['data']['listAfter']['id'] : '';
                if ($afterListId !== '') {
                    $stmtCardDone->execute([':d' => $date, ':id' => $cardId]);
                }
            }
        }
    }

    private function toSqlite(string $iso): string
    {
        $ts = strtotime($iso);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : $iso;
    }
}
