<?php
declare(strict_types=1);

namespace App\Models;

class VelocityCalculator
{
    public float  $dailyVelocity           = 0.0;
    public int    $daysRemaining           = 0;
    public string $estimatedCompletionDate = '';

    public function __construct(DemandCalculator $calc, TrelloBoard $board)
    {
        $total     = $calc->totalDemands;
        $completed = $calc->completedDemands;

        if ($total <= 0 || $completed >= $total) {
            return;
        }

        $this->compute(
            $calc->getScopeByDate(),
            $calc->getDoneByDate(),
            $completed,
            $total,
            $board->dateLastActivity
        );
    }

    private function compute(
        array $scopeByDate,
        array $doneByDate,
        int $completed,
        int $total,
        ?string $boardLastActivity
    ): void {
        $firstTs = null;
        $lastTs  = null;

        foreach (array_keys($scopeByDate + $doneByDate) as $dateStr) {
            $ts = strtotime($dateStr);
            if ($firstTs === null || $ts < $firstTs) { $firstTs = $ts; }
            if ($lastTs === null  || $ts > $lastTs)  { $lastTs  = $ts; }
        }

        if ($lastTs === null && $boardLastActivity !== null) {
            $lastTs  = strtotime($boardLastActivity);
            $firstTs = $firstTs ?? $lastTs;
        }

        if ($lastTs === null || $firstTs === null) {
            return;
        }

        $businessDays = 0;
        $current = $firstTs;
        while ($current <= $lastTs) {
            $dow = (int)gmdate('w', $current);
            if ($dow !== 0 && $dow !== 6) {
                $businessDays++;
            }
            $current += 86400;
        }

        $velocity = $completed / max(1, $businessDays);
        if ($velocity > 5.0) {
            $velocity = 5.0;
        }
        $this->dailyVelocity = $velocity;

        if ($velocity > 0) {
            $this->daysRemaining = (int)ceil(($total - $completed) / $velocity);
            $futureDate = strtotime(gmdate('Y-m-d'));
            $daysAdded  = 0;
            while ($daysAdded < $this->daysRemaining) {
                $futureDate += 86400;
                $dow = (int)gmdate('w', $futureDate);
                if ($dow !== 0 && $dow !== 6) {
                    $daysAdded++;
                }
            }
            $this->estimatedCompletionDate = gmdate('d/m/Y', $futureDate);
        }
    }
}
