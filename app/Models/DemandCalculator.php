<?php
declare(strict_types=1);

namespace App\Models;

class DemandCalculator
{
    private array $checkItemsByCard     = [];
    private array $checkItemsDataByCard = [];
    private array $cardCreatedAt        = [];
    private array $cardFirstActionAt    = [];
    private array $cardDoneAt           = [];
    private array $checkItemCompletedAt = [];
    private array $scopeByDate          = [];
    private array $doneByDate           = [];

    public int $totalDemands     = 0;
    public int $completedDemands = 0;
    public int $openDemandsCount = 0;

    public array $labels          = [];
    public array $dateKeys        = [];
    public array $scopeSeries     = [];
    public array $doneSeries      = [];
    public array $remainingSeries = [];

    public array $chartLabels          = [];
    public array $chartScopeSeries     = [];
    public array $chartDoneSeries      = [];
    public array $chartRemainingSeries = [];
    public int   $periodTotalDemands     = 0;
    public int   $periodCompletedDemands = 0;

    public function __construct(
        private TrelloBoard $board,
        private array $activePendingListIds,
        private array $activeCompletedListIds,
        private array $configuredPendingListIds,
        private array $configuredInProgressListIds = []
    ) {
        $this->groupChecklists();
        $this->processActions();
        $this->calculateDemands();
        $this->computeOpenDemands();
        $this->buildChartSeries();

        $this->chartLabels          = $this->labels;
        $this->chartScopeSeries     = $this->scopeSeries;
        $this->chartDoneSeries      = $this->doneSeries;
        $this->chartRemainingSeries = $this->remainingSeries;
        $this->periodTotalDemands     = $this->totalDemands;
        $this->periodCompletedDemands = $this->completedDemands;
    }

    public function applyDateFilter(string $startDate, string $endDate): void
    {
        if (empty($this->dateKeys)) {
            return;
        }

        $selectedIndexes = [];
        foreach ($this->dateKeys as $index => $dateKey) {
            $afterStart = $startDate === '' || $dateKey >= $startDate;
            $beforeEnd  = $endDate === '' || $dateKey <= $endDate;
            if ($afterStart && $beforeEnd) {
                $selectedIndexes[] = $index;
            }
        }

        $this->chartLabels          = [];
        $this->chartScopeSeries     = [];
        $this->chartDoneSeries      = [];
        $this->chartRemainingSeries = [];

        foreach ($selectedIndexes as $index) {
            $this->chartLabels[]          = $this->labels[$index];
            $this->chartScopeSeries[]     = $this->scopeSeries[$index];
            $this->chartDoneSeries[]      = $this->doneSeries[$index];
            $this->chartRemainingSeries[] = $this->remainingSeries[$index];
        }

        if (!empty($selectedIndexes)) {
            $firstIndex  = $selectedIndexes[0];
            $lastIndex   = $selectedIndexes[count($selectedIndexes) - 1];
            $scopeBefore = $firstIndex > 0 ? $this->scopeSeries[$firstIndex - 1] : 0;
            $doneBefore  = $firstIndex > 0 ? $this->doneSeries[$firstIndex - 1] : 0;
            $this->periodTotalDemands     = max(0, $this->scopeSeries[$lastIndex] - $scopeBefore);
            $this->periodCompletedDemands = max(0, $this->doneSeries[$lastIndex] - $doneBefore);
        } else {
            $this->periodTotalDemands     = 0;
            $this->periodCompletedDemands = 0;
        }
    }

    public function getCheckItemsByCard(): array     { return $this->checkItemsByCard; }
    public function getCheckItemsDataByCard(): array { return $this->checkItemsDataByCard; }
    public function getScopeByDate(): array          { return $this->scopeByDate; }
    public function getDoneByDate(): array           { return $this->doneByDate; }

    private function groupChecklists(): void
    {
        foreach ($this->board->checklists as $checklist) {
            $cardId = isset($checklist['idCard']) ? (string)$checklist['idCard'] : '';
            if ($cardId === '') {
                continue;
            }
            if (!isset($this->checkItemsByCard[$cardId])) {
                $this->checkItemsByCard[$cardId]     = 0;
                $this->checkItemsDataByCard[$cardId] = [];
            }
            $items = isset($checklist['checkItems']) && is_array($checklist['checkItems']) ? $checklist['checkItems'] : [];
            foreach ($items as $item) {
                $this->checkItemsByCard[$cardId]++;
                $this->checkItemsDataByCard[$cardId][] = $item;
            }
        }
    }

    private function processActions(): void
    {
        $actions = $this->board->actions;
        usort($actions, static function (array $a, array $b): int {
            $ad = isset($a['date']) ? strtotime((string)$a['date']) : 0;
            $bd = isset($b['date']) ? strtotime((string)$b['date']) : 0;
            return $ad <=> $bd;
        });

        $doneListIds = [];
        foreach ($this->activeCompletedListIds as $id) {
            if ($id !== '') {
                $doneListIds[$id] = true;
            }
        }

        foreach ($actions as $action) {
            $type   = isset($action['type']) ? (string)$action['type'] : '';
            $date   = isset($action['date']) ? (string)$action['date'] : '';
            if ($date === '') {
                continue;
            }

            $cardId = isset($action['data']['card']['id']) ? (string)$action['data']['card']['id'] : '';
            if ($cardId !== '' && !isset($this->cardFirstActionAt[$cardId])) {
                $this->cardFirstActionAt[$cardId] = $date;
            }

            if ($type === 'createCard' && $cardId !== '' && !isset($this->cardCreatedAt[$cardId])) {
                $this->cardCreatedAt[$cardId] = $date;
            }

            if ($type === 'updateCard' && $cardId !== '') {
                $after  = isset($action['data']['listAfter']['id']) ? (string)$action['data']['listAfter']['id'] : '';
                $before = isset($action['data']['listBefore']['id']) ? (string)$action['data']['listBefore']['id'] : '';
                if ($after !== '' && isset($doneListIds[$after]) && !isset($doneListIds[$before]) && !isset($this->cardDoneAt[$cardId])) {
                    $this->cardDoneAt[$cardId] = $date;
                }
            }

            if ($type === 'createCard' && $cardId !== '' && !isset($this->cardDoneAt[$cardId])) {
                $listId = isset($action['data']['list']['id']) ? (string)$action['data']['list']['id'] : '';
                if ($listId !== '' && isset($doneListIds[$listId])) {
                    $this->cardDoneAt[$cardId] = $date;
                }
            }

            if ($type === 'updateCheckItemStateOnCard') {
                $checkItemId = isset($action['data']['checkItem']['id']) ? (string)$action['data']['checkItem']['id'] : '';
                $state       = isset($action['data']['checkItem']['state']) ? (string)$action['data']['checkItem']['state'] : '';
                if ($checkItemId !== '' && $state === 'complete' && !isset($this->checkItemCompletedAt[$checkItemId])) {
                    $this->checkItemCompletedAt[$checkItemId] = $date;
                }
            }
        }
    }

    private function calculateDemands(): void
    {
        $allowedListIds = [];
        foreach ($this->configuredPendingListIds as $id) {
            $allowedListIds[$id] = true;
        }
        $doneListIds = [];
        foreach ($this->activeCompletedListIds as $id) {
            if ($id !== '') {
                $doneListIds[$id]     = true;
                $allowedListIds[$id]  = true;
            }
        }
        $hasAllowedListFilter = !empty($allowedListIds);

        foreach ($this->board->cards as $card) {
            $cardId = isset($card['id']) ? (string)$card['id'] : '';
            if ($cardId === '') {
                continue;
            }
            $currentCardListId = isset($card['idList']) ? (string)$card['idList'] : '';
            $isInFilteredList  = !$hasAllowedListFilter
                || ($currentCardListId !== '' && isset($allowedListIds[$currentCardListId]));

            $cardLastActivity = isset($card['dateLastActivity'])
                ? (string)$card['dateLastActivity']
                : ($this->board->dateLastActivity ?? '');
            $baseCreatedAt = $this->cardCreatedAt[$cardId]
                ?? ($this->cardFirstActionAt[$cardId] ?? $cardLastActivity);

            $checkItemsCount = $this->checkItemsByCard[$cardId] ?? 0;
            if ($checkItemsCount === 0) {
                continue;
            }

            foreach ($this->checkItemsDataByCard[$cardId] ?? [] as $item) {
                $this->totalDemands++;
                $itemId = isset($item['id']) ? (string)$item['id'] : '';

                if ($isInFilteredList && $baseCreatedAt !== '') {
                    $key = gmdate('Y-m-d', strtotime($baseCreatedAt));
                    $this->scopeByDate[$key] = ($this->scopeByDate[$key] ?? 0) + 1;
                }

                if (isset($item['state']) && $item['state'] === 'complete') {
                    $this->completedDemands++;
                    if ($isInFilteredList) {
                        $doneAt = $this->checkItemCompletedAt[$itemId] ?? $cardLastActivity;
                        if ($doneAt !== '') {
                            $doneKey = gmdate('Y-m-d', strtotime($doneAt));
                            $this->doneByDate[$doneKey] = ($this->doneByDate[$doneKey] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        if (empty($this->scopeByDate) && !empty($this->doneByDate)) {
            $this->scopeByDate = $this->doneByDate;
        } elseif (empty($this->doneByDate) && !empty($this->scopeByDate)) {
            $this->doneByDate = [];
        }
    }

    private function computeOpenDemands(): void
    {
        // Listas de interesse: pendentes ∪ em andamento
        $openListIds = [];
        foreach ($this->configuredPendingListIds as $id) {
            $openListIds[$id] = true;
        }
        foreach ($this->configuredInProgressListIds as $id) {
            $openListIds[$id] = true;
        }

        if (empty($openListIds)) {
            return;
        }

        foreach ($this->board->cards as $card) {
            $cardId = isset($card['id']) ? (string)$card['id'] : '';
            $listId = isset($card['idList']) ? (string)$card['idList'] : '';
            if ($cardId === '' || !isset($openListIds[$listId])) {
                continue;
            }

            $checkItemsCount = $this->checkItemsByCard[$cardId] ?? 0;
            if ($checkItemsCount === 0) {
                // Cartão sem checklist conta como 1 demanda em aberto
                $this->openDemandsCount++;
            } else {
                // Conta apenas os itens incompletos do checklist
                foreach ($this->checkItemsDataByCard[$cardId] as $item) {
                    if (!isset($item['state']) || $item['state'] !== 'complete') {
                        $this->openDemandsCount++;
                    }
                }
            }
        }
    }

    private function buildChartSeries(): void
    {
        $allDates = array_unique(array_merge(array_keys($this->scopeByDate), array_keys($this->doneByDate)));
        sort($allDates);

        if (empty($allDates)) {
            return;
        }

        $current    = new \DateTimeImmutable($allDates[0]);
        $end        = new \DateTimeImmutable($allDates[count($allDates) - 1]);
        $scopeAccum = 0;
        $doneAccum  = 0;

        while ($current <= $end) {
            $key = $current->format('Y-m-d');
            $this->dateKeys[]  = $key;
            $this->labels[]    = $current->format('d/m/Y');
            $scopeAccum       += $this->scopeByDate[$key] ?? 0;
            $doneAccum        += $this->doneByDate[$key] ?? 0;
            if ($doneAccum > $scopeAccum) {
                $doneAccum = $scopeAccum;
            }
            $this->scopeSeries[]     = $scopeAccum;
            $this->doneSeries[]      = $doneAccum;
            $this->remainingSeries[] = $scopeAccum - $doneAccum;
            $current = $current->modify('+1 day');
        }
    }
}
