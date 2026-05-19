<?php
declare(strict_types=1);

namespace App\Models;

class BoardConfigRepository
{
    public function __construct(private \PDO $pdo) {}

    public function load(string $boardId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM board_list_config WHERE board_id = ?');
        $stmt->execute([$boardId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return [
                'pending_list_ids'     => [],
                'completed_list_ids'   => [],
                'in_progress_list_ids' => [],
            ];
        }
        return [
            'pending_list_ids'     => json_decode($row['pending_list_ids'], true) ?? [],
            'completed_list_ids'   => json_decode($row['completed_list_ids'], true) ?? [],
            'in_progress_list_ids' => json_decode($row['in_progress_list_ids'], true) ?? [],
        ];
    }

    public function save(string $boardId, array $pendingIds, array $completedIds, array $inProgressIds): void
    {
        $this->pdo->prepare(<<<SQL
            INSERT INTO board_list_config (board_id, pending_list_ids, completed_list_ids, in_progress_list_ids, updated_at)
            VALUES (:bid, :p, :c, :ip, DATETIME('now'))
            ON CONFLICT(board_id) DO UPDATE SET
                pending_list_ids     = excluded.pending_list_ids,
                completed_list_ids   = excluded.completed_list_ids,
                in_progress_list_ids = excluded.in_progress_list_ids,
                updated_at           = excluded.updated_at
        SQL)->execute([
            ':bid' => $boardId,
            ':p'   => json_encode(array_values($pendingIds), JSON_UNESCAPED_UNICODE),
            ':c'   => json_encode(array_values($completedIds), JSON_UNESCAPED_UNICODE),
            ':ip'  => json_encode(array_values($inProgressIds), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function boardExists(string $boardId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM boards WHERE id = ? LIMIT 1');
        $stmt->execute([$boardId]);
        return $stmt->fetchColumn() !== false;
    }
}
