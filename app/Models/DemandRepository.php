<?php
declare(strict_types=1);

namespace App\Models;

class DemandRepository
{
    public function __construct(private \PDO $pdo) {}

    public function getScopeByDate(string $boardId, array $pendingListIds, array $completedListIds): array
    {
        $allListIds = array_unique(array_merge($pendingListIds, $completedListIds));
        if (empty($allListIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($allListIds), '?'));
        $sql = <<<SQL
            SELECT DATE(ci.completed_at) AS day, COUNT(*) AS cnt
            FROM check_items ci
            JOIN cards c ON c.id = ci.card_id
            WHERE c.list_id IN ($placeholders)
            GROUP BY day
            ORDER BY day
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($allListIds));
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            if (!empty($row['day'])) {
                $result[$row['day']] = (int)$row['cnt'];
            }
        }
        return $result;
    }

    public function getDoneByDate(string $boardId, array $completedListIds): array
    {
        if (empty($completedListIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($completedListIds), '?'));
        $sql = <<<SQL
            SELECT DATE(ci.completed_at) AS day, COUNT(*) AS cnt
            FROM check_items ci
            JOIN cards c ON c.id = ci.card_id
            WHERE c.list_id IN ($placeholders)
              AND ci.state = 'complete'
              AND ci.completed_at IS NOT NULL
            GROUP BY day
            ORDER BY day
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($completedListIds));
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            if (!empty($row['day'])) {
                $result[$row['day']] = (int)$row['cnt'];
            }
        }
        return $result;
    }

    public function getTotalDemands(string $boardId, array $pendingListIds, array $completedListIds): int
    {
        $allListIds = array_unique(array_merge($pendingListIds, $completedListIds));
        if (empty($allListIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($allListIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM check_items ci JOIN cards c ON c.id = ci.card_id WHERE c.list_id IN ($placeholders)"
        );
        $stmt->execute(array_values($allListIds));
        return (int)$stmt->fetchColumn();
    }

    public function getCompletedDemands(string $boardId, array $completedListIds): int
    {
        if (empty($completedListIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($completedListIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM check_items ci JOIN cards c ON c.id = ci.card_id WHERE c.list_id IN ($placeholders) AND ci.state = 'complete'"
        );
        $stmt->execute(array_values($completedListIds));
        return (int)$stmt->fetchColumn();
    }

    public function getInProgressCards(string $boardId, array $inProgressListIds): array
    {
        if (empty($inProgressListIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($inProgressListIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.name, c.list_id, c.short_link, c.date_last_activity
             FROM cards c WHERE c.list_id IN ($placeholders) ORDER BY c.date_last_activity DESC"
        );
        $stmt->execute(array_values($inProgressListIds));
        return $stmt->fetchAll();
    }

    public function getChecklistsForCards(array $cardIds): array
    {
        if (empty($cardIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($cardIds), '?'));
        $stmtCl = $this->pdo->prepare(
            "SELECT id, card_id, name FROM checklists WHERE card_id IN ($placeholders)"
        );
        $stmtCl->execute(array_values($cardIds));
        $checklists = $stmtCl->fetchAll();

        $clIds = array_column($checklists, 'id');
        if (empty($clIds)) {
            return [];
        }
        $ph2  = implode(',', array_fill(0, count($clIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, checklist_id, card_id, name, state FROM check_items WHERE checklist_id IN ($ph2)"
        );
        $stmt->execute(array_values($clIds));
        $items = $stmt->fetchAll();

        $itemsByChecklist = [];
        foreach ($items as $item) {
            $itemsByChecklist[$item['checklist_id']][] = $item;
        }

        $result = [];
        foreach ($checklists as $cl) {
            $result[$cl['card_id']][] = [
                'name'  => $cl['name'],
                'items' => array_map(fn(array $i) => [
                    'id'    => $i['id'],
                    'name'  => $i['name'],
                    'state' => $i['state'],
                ], $itemsByChecklist[$cl['id']] ?? []),
            ];
        }
        return $result;
    }

    public function getBoardMetadata(string $boardId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM boards WHERE id = ?');
        $stmt->execute([$boardId]);
        return $stmt->fetch() ?: [];
    }

    public function getListsForBoard(string $boardId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lists WHERE board_id = ? ORDER BY position');
        $stmt->execute([$boardId]);
        return $stmt->fetchAll();
    }
}
