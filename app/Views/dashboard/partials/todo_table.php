<div class="card shadow-sm mt-4 mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Demandas a fazer <span class="badge bg-secondary"><?php echo count($toDoCards); ?></span></h2>
        <?php if (empty($toDoCards)): ?>
            <p class="text-muted small mb-0">Nenhuma demanda nas listas configuradas como pendentes (fora das listas em andamento).</p>
        <?php else:
            $toDoWithCl    = array_values(array_filter($toDoCards, fn($c) => $c['check_total'] > 0));
            $toDoWithoutCl = array_values(array_filter($toDoCards, fn($c) => $c['check_total'] === 0));
        ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Cartão</th>
                        <th>Lista</th>
                        <th>Checklist</th>
                        <th>Última atividade</th>
                    </tr>
                </thead>
                <tbody>

                <?php if (!empty($toDoWithCl)): ?>
                <tr class="table-secondary">
                    <td colspan="4" class="py-1 px-3 small fw-semibold text-secondary">
                        <i class="fa-solid fa-list-check me-1"></i>
                        Com checklist
                        <span class="fw-normal">
                            — <?php echo count($toDoWithCl); ?> cartão<?php echo count($toDoWithCl) !== 1 ? 'ões' : ''; ?>,
                            <?php
                                $totalCiWith = array_sum(array_column($toDoWithCl, 'check_total'));
                                $doneCiWith  = array_sum(array_column($toDoWithCl, 'check_done'));
                            ?>
                            <?php echo $totalCiWith; ?> itens (<?php echo $doneCiWith; ?> concluídos)
                        </span>
                    </td>
                </tr>
                <?php foreach ($toDoWithCl as $tdCard):
                    $cardUrl    = $tdCard['short_link'] !== '' ? 'https://trello.com/c/' . $tdCard['short_link'] : '';
                    $collapseId = 'td-' . htmlspecialchars($tdCard['id'], ENT_QUOTES, 'UTF-8');
                    $pct        = (int)round(($tdCard['check_done'] / $tdCard['check_total']) * 100);
                    $checkLabel = $tdCard['check_done'] . '/' . $tdCard['check_total'] . ' (' . $pct . '%)';
                    $lastActFormatted = $tdCard['last_activity'] !== ''
                        ? htmlspecialchars(gmdate('d/m/Y H:i', strtotime($tdCard['last_activity'])), ENT_QUOTES, 'UTF-8') . ' UTC'
                        : '-';
                ?>
                <tr class="cursor-pointer"
                    onclick="var el=document.getElementById('<?php echo $collapseId; ?>'); var isOpen=el.classList.contains('show'); bootstrap.Collapse.getOrCreateInstance(el).toggle(); this.setAttribute('aria-expanded', isOpen ? 'false' : 'true');"
                    aria-expanded="false">
                    <td class="fw-medium">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-chevron-right fa-xs text-muted collapse-icon" style="transition:transform .2s;"></i>
                            <?php if ($cardUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($cardUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none" onclick="event.stopPropagation()">
                                <?php echo htmlspecialchars($tdCard['name'], ENT_QUOTES, 'UTF-8'); ?>
                                <i class="fa-solid fa-arrow-up-right-from-square fa-xs text-muted ms-1"></i>
                            </a>
                            <?php else: ?>
                            <span><?php echo htmlspecialchars($tdCard['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-muted small"><?php echo htmlspecialchars($tdCard['list_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px;min-width:60px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                            <span class="text-muted text-nowrap"><?php echo htmlspecialchars($checkLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.75rem;">
                            <?php echo $tdCard['check_total']; ?> <?php echo $tdCard['check_total'] !== 1 ? 'itens' : 'item'; ?> no total
                        </div>
                    </td>
                    <td class="text-muted small"><?php echo $lastActFormatted; ?></td>
                </tr>
                <tr>
                    <td colspan="4" class="p-0 border-0">
                        <div class="collapse" id="<?php echo $collapseId; ?>">
                            <div class="bg-light py-2 px-4">
                                <?php foreach ($tdCard['check_lists'] as $cl): ?>
                                <?php if (!empty($cl['name'])): ?>
                                <div class="fw-semibold small text-muted mb-1 mt-2">
                                    <i class="fa-regular fa-square-check me-1"></i><?php echo htmlspecialchars($cl['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                                <ul class="list-unstyled mb-1 ms-2">
                                    <?php foreach ($cl['items'] as $clItem):
                                        $isDone = $clItem['state'] === 'complete';
                                    ?>
                                    <li class="small py-1 d-flex align-items-start gap-2">
                                        <?php if ($isDone): ?>
                                        <i class="fa-solid fa-circle-check text-success mt-1 flex-shrink-0"></i>
                                        <span class="text-muted text-decoration-line-through"><?php echo htmlspecialchars($clItem['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php else: ?>
                                        <i class="fa-regular fa-circle mt-1 flex-shrink-0 text-secondary"></i>
                                        <span><?php echo htmlspecialchars($clItem['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($toDoWithoutCl)): ?>
                <tr class="table-secondary">
                    <td colspan="4" class="py-1 px-3 small fw-semibold text-secondary">
                        <i class="fa-regular fa-square me-1"></i>
                        Sem checklist
                        <span class="fw-normal">— <?php echo count($toDoWithoutCl); ?> cartão<?php echo count($toDoWithoutCl) !== 1 ? 'ões' : ''; ?></span>
                    </td>
                </tr>
                <?php foreach ($toDoWithoutCl as $tdCard):
                    $cardUrl = $tdCard['short_link'] !== '' ? 'https://trello.com/c/' . $tdCard['short_link'] : '';
                    $lastActFormatted = $tdCard['last_activity'] !== ''
                        ? htmlspecialchars(gmdate('d/m/Y H:i', strtotime($tdCard['last_activity'])), ENT_QUOTES, 'UTF-8') . ' UTC'
                        : '-';
                ?>
                <tr>
                    <td class="fw-medium">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($cardUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($cardUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                <?php echo htmlspecialchars($tdCard['name'], ENT_QUOTES, 'UTF-8'); ?>
                                <i class="fa-solid fa-arrow-up-right-from-square fa-xs text-muted ms-1"></i>
                            </a>
                            <?php else: ?>
                            <span><?php echo htmlspecialchars($tdCard['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-muted small"><?php echo htmlspecialchars($tdCard['list_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="small"><span class="text-muted">—</span></td>
                    <td class="text-muted small"><?php echo $lastActFormatted; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
        <?php
            $totalItems = array_sum(array_column($toDoCards, 'check_total'));
            $doneItems  = array_sum(array_column($toDoCards, 'check_done'));
        ?>
        <div class="mt-3 pt-2 border-top d-flex flex-wrap gap-3 text-muted small">
            <span><i class="fa-regular fa-credit-card me-1"></i><strong><?php echo count($toDoCards); ?></strong> cartão<?php echo count($toDoCards) !== 1 ? 'ões' : ''; ?></span>
            <?php if (!empty($toDoWithoutCl)): ?>
            <span><i class="fa-regular fa-square me-1"></i><strong><?php echo count($toDoWithoutCl); ?></strong> sem checklist</span>
            <?php endif; ?>
            <?php if ($totalItems > 0): ?>
            <span><i class="fa-solid fa-list-check me-1"></i><strong><?php echo $doneItems; ?></strong>/<?php echo $totalItems; ?> itens concluídos</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
