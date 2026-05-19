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
            <div class="col-md-8"><strong>Progresso do Projeto:</strong> <?php echo $projectProgress; ?></div>
            <?php if ($estimatedCompletionDate !== '' && !$hasDateFilter): ?>
            <div class="col-md-8">
                <strong>Previsão de Conclusão:</strong>
                <span class="badge bg-info"><?php echo htmlspecialchars($estimatedCompletionDate, ENT_QUOTES, 'UTF-8'); ?></span>
                <small class="text-muted">(velocidade: <?php echo round($dailyVelocity, 2); ?> demandas/dia útil)</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
