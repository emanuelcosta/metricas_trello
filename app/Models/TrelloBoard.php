<?php
declare(strict_types=1);

namespace App\Models;

class TrelloBoard
{
    public string $id;
    public string $name;
    public string $url;
    public ?string $dateLastActivity;
    public int $membersCount;
    public array $cards;
    public array $checklists;
    public array $actions;
    public array $lists;

    public function __construct(array $boardData)
    {
        if (isset($boardData['idBoard']) && !isset($boardData['lists'])) {
            $boardData = $this->normalizeCardFormat($boardData);
        }

        $this->id               = isset($boardData['id']) ? (string)$boardData['id'] : '';
        $this->name             = isset($boardData['name']) ? (string)$boardData['name'] : 'Board Trello';
        $this->url              = isset($boardData['url']) ? (string)$boardData['url'] : '#';
        $this->dateLastActivity = isset($boardData['dateLastActivity']) ? (string)$boardData['dateLastActivity'] : null;
        $this->membersCount     = isset($boardData['members']) && is_array($boardData['members']) ? count($boardData['members']) : 0;
        $this->cards            = isset($boardData['cards']) && is_array($boardData['cards']) ? $boardData['cards'] : [];
        $this->checklists       = isset($boardData['checklists']) && is_array($boardData['checklists']) ? $boardData['checklists'] : [];
        $this->actions          = isset($boardData['actions']) && is_array($boardData['actions']) ? $boardData['actions'] : [];
        $this->lists            = isset($boardData['lists']) && is_array($boardData['lists']) ? $boardData['lists'] : [];
    }

    public function getAvailableListIds(): array
    {
        $ids = [];
        foreach ($this->lists as $list) {
            $listId = isset($list['id']) ? (string)$list['id'] : '';
            if ($listId !== '') {
                $ids[$listId] = true;
            }
        }
        return $ids;
    }

    public function getBoardListsIndex(): array
    {
        $index = [];
        $pos   = 1;
        foreach ($this->lists as $list) {
            $listId = isset($list['id']) ? (string)$list['id'] : '';
            if ($listId === '') {
                continue;
            }
            $index[(string)$pos] = [
                'id'   => $listId,
                'name' => isset($list['name']) ? (string)$list['name'] : '',
            ];
            $pos++;
        }
        return $index;
    }

    public function getAutoCompletedListId(): string
    {
        foreach ($this->lists as $list) {
            $listId   = isset($list['id']) ? (string)$list['id'] : '';
            $listName = isset($list['name']) ? (string)$list['name'] : '';
            if ($listId !== '' && preg_match('/(conclu|done|finaliz|pronto|entreg)/i', $listName) === 1) {
                return $listId;
            }
        }
        return '';
    }

    private function normalizeCardFormat(array $card): array
    {
        $checklists = isset($card['checklists']) && is_array($card['checklists']) ? $card['checklists'] : [];
        return [
            'id'                => $card['idBoard'] ?? '',
            'name'              => $card['name'] ?? 'Unknown Board',
            'lists'             => [['id' => $card['idList'] ?? 'unknown', 'name' => 'Card List']],
            'cards'             => isset($card['id']) ? [$card] : [],
            'checklists'        => $checklists,
            'actions'           => isset($card['actions']) && is_array($card['actions']) ? $card['actions'] : [],
        ];
    }
}
