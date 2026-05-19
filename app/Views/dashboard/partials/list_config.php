<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Configuração de listas de demanda</h2>
        <div class="text-muted small mb-2">
            Board atual: <strong><?php echo htmlspecialchars($boardName, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <?php if ($listConfigSuccess): ?>
            <div class="alert alert-success py-2">Configuração de listas salva com sucesso.</div>
        <?php endif; ?>
        <?php if ($updateListNamesSuccess): ?>
            <div class="alert alert-success py-2">Nomes das listas atualizados com sucesso.</div>
        <?php endif; ?>
        <?php if ($listConfigError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($listConfigError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($updateListNamesError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($updateListNamesError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="save_list_config">
            <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="col-12">
                <label class="form-label">Listas que contarão como pendentes</label>
                <?php if (empty($boardListsIndex)): ?>
                    <div class="alert alert-warning py-2 small">
                        <strong>⚠️ Nenhuma lista encontrada</strong><br>
                        O arquivo JSON carregado não contém listas. Verifique se o arquivo está correto ou se é um export válido do Trello.
                    </div>
                <?php else: ?>
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
                <?php endif; ?>
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

            <div class="col-12">
                <label class="form-label">Listas exibidas em "Atividades em andamento"</label>
                <div class="form-text mb-2">Cartões dessas listas aparecem na tabela abaixo dos gráficos.</div>
                <div class="row g-2">
                    <?php foreach ($boardListsIndex as $indexKey => $listMeta): ?>
                        <?php $isInProgressChecked = in_array($listMeta['id'], $configuredInProgressListIds, true); ?>
                        <div class="col-12 col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="inprogress_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>" name="in_progress_list_ids[]" value="<?php echo htmlspecialchars($listMeta['id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isInProgressChecked ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="inprogress_<?php echo htmlspecialchars((string)$indexKey, ENT_QUOTES, 'UTF-8'); ?>">
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

        <form method="post" class="mt-3">
            <input type="hidden" name="action" value="refresh_list_names">
            <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="submit" title="Sincroniza os nomes das listas do arquivo JSON atual com a configuração salva">
                    <i class="fa-solid fa-arrows-rotate"></i> Sincronizar nomes das listas
                </button>
                <small class="text-muted">Use quando renomear listas no Trello</small>
            </div>
        </form>
    </div>
</div>
