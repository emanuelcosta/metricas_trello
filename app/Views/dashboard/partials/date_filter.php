<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5 mb-3">Filtro de atividades por data</h2>
        <?php if ($filterError !== null): ?>
            <div class="alert alert-danger py-2"><?php echo htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="source_file" value="<?php echo htmlspecialchars($selectedFileRel, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Data inicial</label>
                <input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">Data final</label>
                <input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Filtrar</button>
            </div>
            <div class="col-md-2 d-grid">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($clearFilterUrl, ENT_QUOTES, 'UTF-8'); ?>">Limpar</a>
            </div>
        </form>
        <?php if ($hasDateFilter): ?>
            <div class="mt-2 text-muted small">
                Período aplicado:
                <strong><?php echo $filterStartInput !== '' ? htmlspecialchars($filterStartInput, ENT_QUOTES, 'UTF-8') : 'início'; ?></strong>
                até
                <strong><?php echo $filterEndInput !== '' ? htmlspecialchars($filterEndInput, ENT_QUOTES, 'UTF-8') : 'hoje'; ?></strong>
            </div>
        <?php endif; ?>
    </div>
</div>
